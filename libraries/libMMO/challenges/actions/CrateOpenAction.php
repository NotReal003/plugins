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

namespace libMMO\challenges\actions;

use libMMO\challenges\ChallengeAction;
use libMMO\challenges\ChallengeSet;
use pocketmine\utils\TextFormat;

class CrateOpenAction extends ChallengeAction
{

    public function toDisplayString(): string
    {
        return TextFormat::YELLOW . 'Open ' . TextFormat::GOLD . $this->getGoal() . TextFormat::YELLOW . ' crates';
    }

    public function shouldIncreaseProgress(?object $object): bool
    {
        return true;
    }

    public function getActionType(): int
    {
        return ChallengeSet::CRATE_OPEN;
    }
}