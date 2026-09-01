<?php
/**
 *        ______         _   _
 *       |  ____|       | | (_)
 *  __  _| |__ __ _  ___| |_ _  ___  _ __  ___
 *  \ \/ /  __/ _` |/ __| __| |/ _ \| '_ \/ __|
 *   >  <| | | (_| | (__| |_| | (_) | | | \__ \
 *  /_/\_\_|  \__,_|\___|\__|_|\___/|_| |_|___/
 *
 * Copyright (C) 2016-2021 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author larryTheCoder
 */

declare(strict_types=1);

namespace factions\faction\claims;

use Closure;
use factions\faction\object\Faction;
use factions\faction\object\OfflineFaction;
use factions\utils\Area;
use factions\utils\BaseClass;
use factions\utils\Database;
use Generator;
use libMMO\MMOPlugin;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\world\World;
use SOFe\AwaitGenerator\Await;
use Throwable;

/**
 * Claim area system, this system will claim all adjacent chunks in a given coordinates.
 *
 * @package factions\faction
 */
class ClaimManager extends BaseClass
{
    // Now claims are "per-world" based, meaning that the player can have as many claims they want in all servers
    // as long that they are bound by their strength. A faction with the power of 250 will receive total claims of 10,
    // and they can get more territory by grinding more power by 150, every 150 power increased, they will be granted with
    // 2 territory power.

    public const CLAIM_ERROR = 0;
    public const CLAIM_CLASHING_OWN = 1;
    public const CLAIM_CLASHING_FACTION = 2;
    public const CLAIM_CLASHING_WARZONE = 3;
    public const CLAIM_LIMIT_REACHED = 4;
    public const CLAIM_OK = 5;

    public const PER_CHUNK_SIZE_LIMIT = 20;
    public const DEFAULT_MIN_CLAIMS = 6;
    public const CLAIMS_PER_STRENGTH = 6;

    /** @var Claim[] */
    private array $chunkToClaimIndex = []; // chunkHash -> Claim - TODO: Replace this with BST tree

    public function __construct(MMOPlugin $instance)
    {
        parent::__construct($instance);

        Await::f2c(function (): Generator {
            Database::executeSelect(Database::CLAIMS_GET_DATA, [
                'server_id' => $this->getPlugin()->getEssentials()->getServerManager()->getServerRegion()
            ], yield, yield Await::REJECT);

            $rows = yield Await::ONCE;

            foreach ($rows as ['faction_id' => $factionId, 'chunk_hash' => $hash, 'faction_name' => $factionName, 'strength' => $strength]) {
                $this->chunkToClaimIndex[$hash] = new Claim($hash, $factionId, $factionName, $strength);
            }

            $this->getPlugin()->getLogger()->info(TextFormat::GREEN . "Successfully loaded claims objects.");
        }, catches: Database::getFailClosure());

        Database::getMySQLDatabase()->waitAll();
    }

    public function purgeClaims(int $factionId): void
    {
        $claims = $this->getClaimsByFaction($factionId);

        foreach ($claims as $hash => $claim) {
            unset($this->chunkToClaimIndex[$hash]);
        }
    }

    public function tryAndClaimPosition(Faction $faction, Position $position, ?Closure $onSuccess = null): void
    {
        Await::f2c(function () use ($faction, $position): Generator {
            if ($position->getWorld()->getFolderName() !== 'wild') {
                return self::CLAIM_ERROR;
            }

            self::positionToChunk($position, $chunkX, $chunkZ);
            $chunkHash = World::chunkHash($chunkX, $chunkZ);
            if (Area::isChunkInWarzone($chunkX, $chunkZ)) {
                return self::CLAIM_CLASHING_WARZONE;
            }

            if (isset($this->chunkToClaimIndex[$chunkHash])) {
                if ($this->chunkToClaimIndex[$chunkHash]->getFactionId() === $faction->getFactionId()) {
                    return self::CLAIM_CLASHING_OWN;
                }

                return self::CLAIM_CLASHING_FACTION;
            }

			if (count($this->getClaimsByFaction($faction)) >= $this->getClaimLimit($faction)) {
				return self::CLAIM_LIMIT_REACHED;
			}

            Database::executeInsert(Database::CLAIMS_ADD_DATA, [
                'faction_id' => $faction->getFactionId(),
                'server_id' => $this->getPlugin()->getEssentials()->getServerManager()->getServerRegion(),
                'chunk_hash' => $chunkHash
            ], yield Await::RESOLVE_MULTI, yield Await::REJECT);

            [, $affectedRows] = yield Await::ONCE;

            if ($affectedRows > 0) {
				// totalClaims + 1 because we added new claims data into the database.
				if ((count($this->getClaimsByFaction($faction)) + 1) > $this->getClaimLimit($faction)) {
					Database::executeChange(Database::CLAIMS_DELETE_DATA, [
						'server_id' => $this->getPlugin()->getEssentials()->getServerManager()->getServerRegion(),
						'chunk_hash' => $chunkHash
					], yield, yield Await::REJECT);

					yield Await::ONCE;

					return self::CLAIM_LIMIT_REACHED;
				}

                $this->chunkToClaimIndex[$chunkHash] = new Claim($chunkHash, $faction->getFactionId(), $faction->getFactionName(), $faction->getStrength());

                return self::CLAIM_OK;
            }

            return self::CLAIM_CLASHING_FACTION;
        }, $onSuccess, function (Throwable $error) use ($onSuccess) {
            $this->getPlugin()->getLogger()->logException($error);

            if ($onSuccess !== null) {
                $onSuccess(self::CLAIM_ERROR);
            }
        });
    }

    public function overClaimArea(Position $pos, Faction $faction, ?Closure $onSuccess = null): void
    {
        Await::f2c(function () use ($pos, $faction): Generator {
            if ($pos->getWorld()->getFolderName() !== 'wild') {
                return self::CLAIM_ERROR;
            }

            self::positionToChunk($pos, $x, $z);
            $chunkHash = World::chunkHash($x, $z);
            if (!isset($this->chunkToClaimIndex[$chunkHash])) {
                return self::CLAIM_CLASHING_FACTION;
            }

            Database::executeChange(Database::CLAIMS_OVERCLAIM_DATA, [
                'faction_id' => $faction->getFactionId(),
                'server_id' => $this->getPlugin()->getEssentials()->getServerManager()->getServerRegion(),
                'chunk_hash' => $chunkHash
            ], yield, yield Await::REJECT);

            $affectedRows = yield Await::ONCE;

            if ($affectedRows > 0) {
                $this->chunkToClaimIndex[$chunkHash]->changeClaimOwned($faction);

                return self::CLAIM_OK;
            }

            return self::CLAIM_CLASHING_FACTION;
        }, $onSuccess, function (Throwable $error) use ($onSuccess) {
            $this->getPlugin()->getLogger()->logException($error);

            if ($onSuccess !== null) {
                $onSuccess(self::CLAIM_ERROR);
            }
        });
    }

    public function removeClaim(Position $pos, Closure $onComplete): void
    {
        Await::f2c(function () use ($pos): Generator {
            if ($pos->getWorld()->getFolderName() !== 'wild') {
                return self::CLAIM_ERROR;
            }

            self::positionToChunk($pos, $x, $z);
            $chunkHash = World::chunkHash($x, $z);
            if (!isset($this->chunkToClaimIndex[$chunkHash])) {
                return self::CLAIM_CLASHING_FACTION;
            }

            Database::executeChange(Database::CLAIMS_DELETE_DATA, [
                'server_id' => $this->getPlugin()->getEssentials()->getServerManager()->getServerRegion(),
                'chunk_hash' => $chunkHash
            ], yield, yield Await::REJECT);

            $affectedRows = yield Await::ONCE;

            if ($affectedRows > 0) {
                unset($this->chunkToClaimIndex[$chunkHash]);

                return self::CLAIM_OK;
            }

            return self::CLAIM_CLASHING_FACTION;
        }, $onComplete, function (Throwable $error) use ($onComplete) {
            $this->getPlugin()->getLogger()->logException($error);
            $onComplete(self::CLAIM_ERROR);
        });
    }

    /**
     * @param Faction|OfflineFaction|int $faction
     * @return Claim[] The claims of the given faction info.
     */
    public function getClaimsByFaction(Faction|OfflineFaction|int $faction): array
    {
        if ($faction instanceof Faction || $faction instanceof OfflineFaction) {
            $factionId = $faction->getFactionId();
        } else {
            $factionId = $faction;
        }

        return array_filter($this->chunkToClaimIndex, function (Claim $claim) use ($factionId): bool {
            return $claim->getFactionId() === $factionId;
        });
    }

    public function getClaimInPosition(Position $pos): ?Claim
    {
        if ($pos->getWorld()->getFolderName() !== 'wild') {
            return null;
        }

        self::positionToChunk($pos, $x, $z);
        return $this->chunkToClaimIndex[World::chunkHash($x, $z)] ?? null;
    }

    public function getClaimLimit(Faction $faction): int
    {
        $strength = $faction->getStrength() - 250;

        return $strength > 0 ? (self::DEFAULT_MIN_CLAIMS + (int)(floor($strength / 150) * self::CLAIMS_PER_STRENGTH)) : 0;
    }

    /**
     * Convert the given position into a chunk coordinates, the chunk in size can change in variation to the
     * PER_CHUNK_SIZE_LIMIT constant.
     *
     * @param Vector3 $position
     * @param int|null $x The x-cord relative to the chunk
     * @param int|null $z The z-cord relative to the chunk
     * @param int|null $rx The relative coordinate in the given chunk for x-cord
     * @param int|null $rz The relative coordinate in the given chunk for z-cord
     */
    public static function positionToChunk(Vector3 $position, ?int &$x, ?int &$z, ?int &$rx = 0, ?int &$rz = 0): void
    {
        $dx = $position->getX() / self::PER_CHUNK_SIZE_LIMIT;
        $dz = $position->getZ() / self::PER_CHUNK_SIZE_LIMIT;

        $x = (int)floor($dx);
        $z = (int)floor($dz);

        $rx = (int)(fmod($dx, 1) * self::PER_CHUNK_SIZE_LIMIT);
        $rz = (int)(fmod($dx, 1) * self::PER_CHUNK_SIZE_LIMIT);
    }
}