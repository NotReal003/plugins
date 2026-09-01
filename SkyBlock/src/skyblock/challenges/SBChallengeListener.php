<?php
/**
 *         _____ _          _     _            _
 *        / ____| |        | |   | |          | |
 *  __  _| (___ | | ___   _| |__ | | ___   ___| | __
 *  \ \/ /\___ \| |/ / | | | '_ \| |/ _ \ / __| |/ /
 *   >  < ____) |   <| |_| | |_) | | (_) | (__|   <
 *  /_/\_\_____/|_|\_\\__, |_.__/|_|\___/ \___|_|\_\
 *                     __/ |
 *                    |___/
 *
 * Copyright (C) 2016-2022 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew
 *
 */

namespace skyblock\challenges;

use libMMO\challenges\ChallengeListener;
use libMMO\challenges\ChallengeSet;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;
use skyblock\entities\boss\Boss;
use skyblock\entities\boss\BossMinion;

class SBChallengeListener extends ChallengeListener
{
    public function onEntityDamage(EntityDamageByEntityEvent $event): void
    {
        $damager = $event->getDamager();
        $entity = $event->getEntity();

        if (($damager instanceof Player) && $entity->getHealth() <= $event->getFinalDamage()) {
            if ($entity instanceof Boss && !($entity instanceof BossMinion)) {
                foreach ($this->getPlugin()->getPlayerChallengeManager()->getActiveChallenges($damager) as $challenge) {
                    $challenge->increaseProgress($damager, ChallengeSet::KILL_ENTITY, $entity);
                }
            }

            foreach ($this->getPlugin()->getPlayerChallengeManager()->getActiveChallenges($damager) as $challenge) {
                $challenge->increaseProgress($damager, ChallengeSet::KILL_ENTITY, $entity);
            }
        }
    }
}