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
declare(strict_types=1);

namespace skyblock\challenges\actions;

use libMMO\challenges\ChallengeAction;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use skyblock\challenges\SkyblockChallengeSet;
use skyblock\SkyBlock;

class KillStreakAction extends ChallengeAction
{

    public function toDisplayString(): string
    {
        return TextFormat::YELLOW . 'Get a ' . TextFormat::GOLD . $this->getGoal() . TextFormat::YELLOW . ' kill streak';
    }

    public function shouldIncreaseProgress(?object $object): bool
    {
        if ($object instanceof PlayerDeathEvent) {
            $player = $object->getPlayer();
            $cause = $player->getLastDamageCause();
            if ($cause instanceof EntityDamageByEntityEvent) {
                $damager = $cause->getDamager();
                if ($damager instanceof Player) {
                    foreach (SkyBlock::getInstance()->getPlayerChallengeManager()->getActiveChallenges($damager) as $challenge) {
                        $challenge->increaseProgress($damager, SkyblockChallengeSet::KILL_STREAK);
                    }
                }
            }

            return false;
        }

        return true;
    }

    public function getActionType(): int
    {
        return SkyblockChallengeSet::KILL_STREAK;
    }
}