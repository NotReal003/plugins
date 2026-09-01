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

namespace skyblock\challenges\actions;

use libMMO\challenges\ChallengeAction;
use pocketmine\utils\TextFormat;
use skyblock\challenges\SkyblockChallengeSet;

class BossKillAction extends ChallengeAction
{
    public function shouldIncreaseProgress(?object $object): bool
    {
        return true;
    }

    public function getActionType(): int
    {
        return SkyblockChallengeSet::KILL_BOSS;
    }

    public function toDisplayString(): string
    {
        return TextFormat::YELLOW . 'Defeat ' . TextFormat::GOLD . $this->getGoal() . TextFormat::YELLOW . ' boss enemies';
    }
}