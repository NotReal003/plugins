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
use libMMO\challenges\placeholder\LockData;
use pocketmine\utils\TextFormat;
use function in_array;

class GraveCollectAction extends ChallengeAction
{
    /** @var string[][] */
    private static array $duplicateLock = [];

    public function shouldIncreaseProgress(?object $object): bool
    {
        if ($object instanceof LockData) {
            if (isset(self::$duplicateLock[$object->getPlayerName()])) {
                if (in_array($object->getDataField(), self::$duplicateLock[$object->getPlayerName()], true)) {
                    return false;
                }

                self::$duplicateLock[$object->getPlayerName()][] = $object->getDataField();
            } else {
                self::$duplicateLock[$object->getPlayerName()] = [$object->getDataField()];
            }
        }

        return true;
    }

    public function toDisplayString(): string
    {
        return TextFormat::YELLOW . 'Collect inventories of ' . TextFormat::GOLD . $this->getGoal() . TextFormat::YELLOW . ' players';
    }

    public function getActionType(): int
    {
        return ChallengeSet::GRAVE_COLLECT;
    }
}