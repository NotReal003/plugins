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

namespace libMMO\utils;

use Closure;
use Generator;
use libMMO\MMOPlugin;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use SOFe\AwaitGenerator\Await;

class AwaitUtils
{
    /**
     * Attempt to create an "OR" condition of two closures, the function will return two
     * closures array, and each one of them will be returned as its input in closure variable.
     * If any closures were executed after any one of them were executed, it will be ignored.
     *
     * @param Closure $closure
     * @return Closure[]
     */
    public static function createOrCallback(Closure $closure): array
    {
        return [
            static function (...$data) use ($closure) {
                $closure([0, $data]);
            }, static function (...$data) use ($closure) {
                $closure([1, $data]);
            }
        ];
    }

    /**
     * Attempt to wait for the player to spawned. This is useful when the callback
     * needs to be executed after the player has been spawned in the server.
     *
     * @param Player $player
     * @param Closure $callback
     */
    public static function waitPlayerSpawned(Player $player, Closure $callback): void
    {
        Await::f2c(function () use ($player, $callback): Generator {
            while (!$player->spawned) {
                MMOPlugin::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(yield), 1);
                yield Await::ONCE;

                if (!$player->isConnected()) {
                    return;
                }
            }

            $callback();
        });
    }
}