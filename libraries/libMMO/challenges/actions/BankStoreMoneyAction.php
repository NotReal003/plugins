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
use function number_format;

class BankStoreMoneyAction extends ChallengeAction
{
    public function getActionType(): int
    {
        return ChallengeSet::BANK_STORE_MONEY;
    }

    public function toDisplayString(): string
    {
        return TextFormat::YELLOW . 'Deposit ' . TextFormat::GOLD . '$' . number_format($this->getGoal()) . TextFormat::YELLOW . ' into the bank';
    }

    public function shouldIncreaseProgress(?object $object): bool
    {
        return true;
    }

    public function equals(ChallengeAction $action): bool
    {
        return $this->getGoal() === $action->getGoal() && $action instanceof self;
    }
}