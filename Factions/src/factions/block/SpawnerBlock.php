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

namespace factions\block;

use factions\item\CustomItemManager;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier as BID;
use pocketmine\block\BlockToolType;
use pocketmine\block\BlockTypeIds as Ids;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\MonsterSpawner as PMMonsterSpawnerBase;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\item\ToolTier;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\World;

class SpawnerBlock extends PMMonsterSpawnerBase
{
    public const SPAWN_RADIUS = 4;
    public const SPAWN_Y_NEGATIVE = 2;
    public const SPAWN_Y_POSITIVE = 5;
    public const SPAWN_INTERVAL_MIN = 4;
    public const SPAWN_INTERVAL_MAX = 10;
    public const SPAWN_RETRIES_MAX = 5;

    /** @var bool[] */
    private static array $noSchedule = [];

    /**
     * Backoff delayed block update when there is already one scheduled. Player were able to
     * continuously schedule an update if they break the tile and place them as quickly as possible. This prevents
     * that from happening.
     */
    public static function scheduleBlockUpdate(World $world, Vector3 $pos, int $delay): void
    {
        if (!isset(self::$noSchedule[World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ())])) {
            self::$noSchedule[World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ())] = true;

            $world->scheduleDelayedBlockUpdate($pos, $delay);
        }
    }

    public function __construct()
    {
        parent::__construct(new BID(Ids::MONSTER_SPAWNER, SpawnerTile::class), "Monster Spawner", new BlockTypeInfo(new BlockBreakInfo(5.0, BlockToolType::PICKAXE, ToolTier::WOOD->getHarvestLevel())));
    }

    public function onScheduledUpdate(): void
    {
        $pos = $this->getPosition();
        $world = $pos->getWorld();

        unset(self::$noSchedule[World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ())]);

        $spawnerTile = $world->getTile($pos);
        if (!($spawnerTile instanceof SpawnerTile) || !$spawnerTile->isValid()) {
            return;
        }

        // Do not spawn any entities if there is no players nearby, but let the server unload the chunk for us.
        if (empty($world->getViewersForPosition($pos))) {
            return;
        }

        // Skip processing when we already have an entity to stack.
        if ($spawnerTile->stackEntity() !== null) {
            self::scheduleBlockUpdate($world, $pos, mt_rand(self::SPAWN_INTERVAL_MIN, self::SPAWN_INTERVAL_MAX) * 20);
            return;
        }

        // Spawner tile spawning method. First of all, we will have to make a random of xz coordinates in which it should spawn.
        // I need to check for y-axis as well, first find the xz coordinates, then scan y coordinates where MIN_Y < y < MAX_Y.
        // After that, we have to check if the position is okay to spawn if not, go to next

        $minY = $pos->getFloorY() - self::SPAWN_Y_NEGATIVE - 5;
        $baseY = max(0, $minY);
        $adjust = $baseY === 0 ? abs($minY) : 0;
        $maxY = $pos->getFloorY() + self::SPAWN_Y_POSITIVE + $adjust;

        for ($tries = 0; $tries < self::SPAWN_RETRIES_MAX; ++$tries) {
            $x = (int)floor($pos->getFloorX() + ((mt_rand() / mt_getrandmax()) * self::SPAWN_RADIUS));
            $z = (int)floor($pos->getFloorZ() + ((mt_rand() / mt_getrandmax()) * self::SPAWN_RADIUS));

            // Scan 1: From current y to minY
            $scanFound = 0;
            for ($y = $pos->getFloorY(); $y > $baseY; $y--) {
                if ($world->getBlockAt($x, $y, $z)->getTypeId() === VanillaBlocks::AIR()->getTypeId()) {
                    // Check if two block below is air.
                    if (++$scanFound > 1) {
                        break;
                    }
                } else {
                    $scanFound = 0;
                }
            }

            // Scan 2: From current y to maxY, only do that if the first attempt was unsuccessful.
            if ($scanFound < 2) {
                $candidate = 0;
                $scanFound = 0;
                for ($y = $pos->getFloorY() - 1; $maxY > $y; $y++) {
                    if ($world->getBlockAt($x, $y, $z)->getTypeId() === VanillaBlocks::AIR()->getTypeId()) {
                        if ($scanFound === 0) {
                            $candidate = $y;
                        }

                        // Check if two block above is air.
                        if (++$scanFound > 1) {
                            break;
                        }
                    } else {
                        $scanFound = 0;
                    }
                }

                $y = $candidate;
            }

            if ($scanFound < 2) {
                continue;
            }

            if ($spawnerTile->spawn($world, Position::fromObject(new Vector3($x, $y, $z), $world)) !== null) {
                break;
            }
        }

        self::scheduleBlockUpdate($world, $pos, mt_rand(self::SPAWN_INTERVAL_MIN, self::SPAWN_INTERVAL_MAX) * 20);
    }

    public function getDrops(Item $item): array
    {
        $spawnerTile = $this->getPosition()->getWorld()->getTile($this->getPosition());
        if (!($spawnerTile instanceof SpawnerTile) || !$spawnerTile->isValid()) {
            return [];
        }

        return [CustomItemManager::getSpawnerItem($spawnerTile->getSpawnerEntity())];
    }

    protected function getXpDropAmount(): int
    {
        return 0;
    }
}