<?php
/**
 *        ______         _   _
 *       |  ____|       | | (_)
 *  __  _| |__ __ _  ___| |_ _  ___  _ __  ___
 *  \ \/ /  __/ _` |/ __| __| |/ _ \| '_ \/ __|
 *   >  <| | | (_| | (__| |_| | (_) | | | \__ \
 *  /_/\_\_|  \__,_|\___|\__|_|\___/|_| |_|___/
 *
 * Copyright (C) 2016-2021 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author larryTheCoder
 */

declare(strict_types=1);

namespace factions\koth\task;

use factions\koth\Koth;
use libminigames\Arena;
use libMMO\MMOPlugin;
use pocketmine\scheduler\Task;
use pocketmine\Server;

class CountDownTask extends Task
{

    /** @var Koth */
    private Koth $arena;
    /** @var int */
    private int $countdown = 60;

    public function __construct(Koth $koth)
    {
        $this->arena = $koth;
        $this->arena->setStatus(Arena::STATUS_STARTING);
    }

    public function onRun(): void
    {
        if ($this->countdown > 0) {
            if ($this->countdown % 10 === 0 || $this->countdown <= 5) {
                Server::getInstance()->broadcastMessage(MMOPlugin::getPrefix() . 'KOTH is starting in ' . $this->countdown . ' seconds! Join the game with /koth join!');
            }

            $this->countdown--;
            return;
        } else if (($playerCount = count($this->arena->getPlayers())) <= 1) {
            $this->arena->endMatch();
        } else {
            Server::getInstance()->broadcastMessage(MMOPlugin::getPrefix() . 'A game of KOTH with ' . $playerCount . ' players has started.');

            $this->arena->getPlugin()->getScheduler()->scheduleRepeatingTask(new KothGameTask($this->arena), 20);
        }

        $this->getHandler()->cancel();
    }
}