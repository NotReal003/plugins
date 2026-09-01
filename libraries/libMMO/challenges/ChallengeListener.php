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

namespace libMMO\challenges;

use libMMO\utils\BaseClass;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\Listener;
use pocketmine\inventory\PlayerInventory;
use pocketmine\player\Player;

abstract class ChallengeListener extends BaseClass implements Listener
{
    /**
     * @param EntityDamageByEntityEvent $event
     * @priority MONITOR
     */
    public function onEntityDamage(EntityDamageByEntityEvent $event): void
    {
        $damager = $event->getDamager();
        $entity = $event->getEntity();
        if (($damager instanceof Player) && $entity->getHealth() <= $event->getFinalDamage()) {
            foreach ($this->getPlugin()->getPlayerChallengeManager()->getActiveChallenges($damager) as $challenge) {
                $challenge->increaseProgress($damager, ChallengeSet::KILL_ENTITY, $entity);
            }
        }
    }

    /**
     * @param BlockBreakEvent $event
     * @priority MONITOR
     */
    public function onBreak(BlockBreakEvent $event): void
    {
        foreach ($this->getPlugin()->getPlayerChallengeManager()->getActiveChallenges($event->getPlayer()) as $challenge) {
            $challenge->increaseProgress($event->getPlayer(), ChallengeSet::BREAK_BLOCKS, $event);
        }
    }

    /**
     * @param EntityItemPickupEvent $event
     * @priority MONITOR
     */
    public function onItemPickup(EntityItemPickupEvent $event): void
    {
        $inventory = $event->getInventory();

        if ($inventory instanceof PlayerInventory) {
            $player = $inventory->getHolder();
            if ($player instanceof Player) {
                foreach ($this->getPlugin()->getPlayerChallengeManager()->getActiveChallenges($player) as $challenge) {
                    $challenge->increaseProgress($player, ChallengeSet::ITEM_PICKUP, $event->getOrigin(), $event->getItem()->getCount());
                }
            }
        }
    }
}