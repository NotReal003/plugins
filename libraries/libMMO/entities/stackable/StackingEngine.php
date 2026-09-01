<?php
/**
 *   _ _ _     __  __ __  __  ____
 *  | (_) |   |  \/  |  \/  |/ __ \
 *  | |_| |__ | \  / | \  / | |  | |
 *  | | | '_ \| |\/| | |\/| | |  | |
 *  | | | |_) | |  | | |  | | |__| |
 *  |_|_|_.__/|_|  |_|_|  |_|\____/
 *
 * Copyright (C) 2016-2024 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder, Studgi
 */

declare(strict_types=1);

namespace libMMO\entities\stackable;

use pocketmine\entity\Entity;
use pocketmine\math\AxisAlignedBB;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use pocketmine\world\World;
use RuntimeException;

class StackingEngine
{
    /** @var int */
    public static int $maxStackingEntities = 250;

    /**
     * Search for any entity matches with class in an area of 15 blocks radius.
     *
     * @param Position $position
     * @param string $entityClass
     * @return StackableInterface|Entity|null
     *
     * @phpstan-template TEntity of Entity
     * @phpstan-param class-string<TEntity> $entityClass
     */
    public static function searchForStack(Position $position, string $entityClass): StackableInterface|Entity|null
    {
        // This will explicitly check if the entity is the correct instance of our target entity.
        $nearest = $position->getWorld()->getNearestEntity($position, 15, $entityClass);
        if ($nearest instanceof StackableInterface && $nearest->getStackedAmount() < self::$maxStackingEntities) {
            return $nearest;
        }

        return null;
    }

    /**
     * @param StackableInterface $interface
     */
    public static function alphaPruningStack(StackableInterface $interface): void
    {
        if (!($interface instanceof Entity)) {
            throw new RuntimeException("Specified stackable interface must be instanceof Entity");
        } else if ($interface->getStackedAmount() >= self::$maxStackingEntities) {
            return;
        }

        $bb = $interface->getBoundingBox()->expandedCopy(15, 15, 15);

        $alphaEntity = $interface;
        $betaEntities = [];

        foreach (self::getNearbyEntities($interface->getWorld(), $bb, $interface) as $entity) {
            if (!($entity instanceof StackableInterface) || $entity->isClosed() || $entity->getCustomName() !== $interface->getCustomName() || $entity->getStackedAmount() >= self::$maxStackingEntities) {
                continue;
            }

            if ($alphaEntity->getStackedAmount() >= $entity->getStackedAmount()) {
                $betaEntities[] = $entity;
            } else if ($alphaEntity->getStackedAmount() < $entity->getStackedAmount()) {
                $betaEntities[] = $alphaEntity;
                $alphaEntity = $entity;
            }
        }

        foreach ($betaEntities as $entity) {
            if ($alphaEntity->getStackedAmount() < self::$maxStackingEntities) {
                $stackingAmount = $entity->getStackedAmount();

                $totalStack = $alphaEntity->getStackedAmount() + $stackingAmount;
                if ($totalStack < self::$maxStackingEntities) {
                    $alphaEntity->stack($stackingAmount);

                    $entity->close();
                } else if ($totalStack >= self::$maxStackingEntities) {
                    $residue = $totalStack - self::$maxStackingEntities;

                    $alphaEntity->stack($stackingAmount - $residue);
                    $entity->stack($residue, StackableInterface::MODE_SET_VALUE);
                }
            }
        }
    }

    /**
     * Returns all entities whose bounding boxes intersect the given bounding box, excluding the given entity.
     * This will not take y-axis into search.
     *
     * @return Entity[]
     * @phpstan-return array<int, Entity>
     */
    public static function getNearbyEntities(World $world, AxisAlignedBB $bb, ?Entity $entity = null): array
    {
        $nearby = [];

        $minX = ((int)floor($bb->minX - 2)) >> Chunk::COORD_BIT_SIZE;
        $maxX = ((int)floor($bb->maxX + 2)) >> Chunk::COORD_BIT_SIZE;
        $minZ = ((int)floor($bb->minZ - 2)) >> Chunk::COORD_BIT_SIZE;
        $maxZ = ((int)floor($bb->maxZ + 2)) >> Chunk::COORD_BIT_SIZE;

        for ($x = $minX; $x <= $maxX; ++$x) {
            for ($z = $minZ; $z <= $maxZ; ++$z) {
                if (!$world->isChunkLoaded($x, $z)) {
                    continue;
                }
                foreach ($world->getChunkEntities($x, $z) as $ent) {
                    if ($ent !== $entity && self::intersectsWith($ent->boundingBox, $bb)) {
                        $nearby[] = $ent;
                    }
                }
            }
        }

        return $nearby;
    }

    public static function intersectsWith(AxisAlignedBB $bb0, AxisAlignedBB $bb1, float $epsilon = 0.001): bool
    {
        if ($bb1->maxX - $bb0->minX > $epsilon and $bb0->maxX - $bb1->minX > $epsilon) {
            return $bb1->maxZ - $bb0->minZ > $epsilon and $bb0->maxZ - $bb1->minZ > $epsilon;
        }
        return false;
    }
}