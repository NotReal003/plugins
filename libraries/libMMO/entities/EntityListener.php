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

namespace libMMO\entities;

use libMMO\utils\BaseListener;
use pocketmine\entity\projectile\Arrow;
use pocketmine\event\entity\ItemEntityDropEvent;
use pocketmine\event\entity\ProjectileHitBlockEvent;

class EntityListener extends BaseListener
{
    /**
     * @param ItemEntityDropEvent $event
     *
     * @priority MONITOR
     */
    public function onItemEntityDrop(ItemEntityDropEvent $event): void
    {
        $oldEntity = $event->getEntity();

        if (!$oldEntity->isFlaggedForDespawn()) {
            $newEntity = new OptimizedItemEntity($oldEntity->getLocation(), $oldEntity->getItem());
            $newEntity->setPickupDelay($oldEntity->getPickupDelay());
            $newEntity->setMotion($oldEntity->getMotion());
            $newEntity->spawnToAll();
        }

        $event->cancel();
    }

    /**
     * @param ProjectileHitBlockEvent $event
     *
     * @priority MONITOR
     */
    public function onProjectileHitBlock(ProjectileHitBlockEvent $event): void
    {
        $entity = $event->getEntity();

        if ($entity instanceof Arrow) {
            $this->getPlugin()->getEntityManager()->queueForDespawn($entity, 5);
        }
    }
}