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
use factions\Factions;
use factions\utils\object\FactionLocation;
use LogicException;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\World;

final class Claim
{
    /** @var Vector3 */
    private Vector3 $firstPosition;
    /** @var Vector3 */
    private Vector3 $secondPosition;

    public function __construct(
        private int    $claimHash,
        private int    $factionId,
        private string $factionName,
        private int    $factionStrength,
    )
    {
        World::getXZ($this->claimHash, $x, $z);

        $xRelative = $x * ClaimManager::PER_CHUNK_SIZE_LIMIT;
        $zRelative = $z * ClaimManager::PER_CHUNK_SIZE_LIMIT;

        $this->firstPosition = new Vector3($xRelative, 0, $zRelative);
        $this->secondPosition = new Vector3($xRelative + ClaimManager::PER_CHUNK_SIZE_LIMIT, 256, $zRelative + ClaimManager::PER_CHUNK_SIZE_LIMIT);
    }

    public function getFactionId(): int
    {
        return $this->factionId;
    }

    public function canAccess(Player $player): bool
    {
        $playerData = Factions::getInstance()->getPlayerData();
        if (($faction = $playerData->getFaction($player)) === null) {
            return false;
        }

        return $faction->getFactionId() === $this->factionId || $faction->isFactionAlly($this->factionId);
    }

    public function setStrength(int $factionStrength): void
    {
        $this->factionStrength = $factionStrength;
    }

    public function getStrength(): int
    {
        $faction = Factions::getInstance()->getFactionManager()->getFaction($this->factionId);

        if ($faction !== null) {
            return $this->factionStrength = $faction->getStrength();
        }

        return $this->factionStrength;
    }

    public function getFaction(Closure $results): void
    {
        Factions::getInstance()->getFactionManager()->loadFactionById($this->factionId, function (?Faction $faction) use ($results): void {
            if ($faction === null) {
                throw new LogicException("Requested claim for non-existent faction ($this->factionId).");
            }

            $results($faction);
        });
    }

    /**
     * @param Position|FactionLocation $position
     * @return bool
     */
    public function isInClaim(Position|FactionLocation $position): bool
    {
        if ($position instanceof FactionLocation) {
            $worldName = $position->getWorldName();
        } else {
            $worldName = $position->getWorld()->getFolderName();
        }

        $minX = min($this->firstPosition->getFloorX(), $this->secondPosition->getFloorX());
        $maxX = max($this->firstPosition->getFloorX(), $this->secondPosition->getFloorX());
        $minZ = min($this->firstPosition->getFloorZ(), $this->secondPosition->getFloorZ());
        $maxZ = max($this->firstPosition->getFloorZ(), $this->secondPosition->getFloorZ());

        return $minX <= $position->getFloorX() && $maxX >= $position->getFloorX() && $minZ <= $position->getFloorZ() && $maxZ >= $position->getFloorZ() && $worldName === 'wild';
    }

    public function getFactionName(): string
    {
        return $this->factionName;
    }

    public function changeClaimOwned(Faction $faction): void
    {
        $this->factionId = $faction->getFactionId();
    }
}