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

namespace factions\task;

use factions\Factions;
use GlobalLogger;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\timings\TimingsHandler;

class AutoTimings extends Task
{
    /** @var int */
    private int $time;
    /** @var ConsoleCommandSender|null */
    private ?ConsoleCommandSender $consoleSender = null;

    public function onRun(): void
    {
        $this->fetchConsole();

        if ($this->consoleSender === null) {
            return;
        }

        $server = Factions::getInstance()->getServer();
        if (!TimingsHandler::isEnabled()) {
            TimingsHandler::setEnabled();
        } else if ((time() - $this->time) > 10) {
            $server->dispatchCommand($this->consoleSender, 'timings paste');

            GlobalLogger::get()->warning("Backlog of lag found, timings pasted");
        }

        TimingsHandler::reload();

        $this->time = time();
    }

    public function fetchConsole(): void
    {
        if ($this->consoleSender === null) {
            $consoles = Server::getInstance()->getBroadcastChannelSubscribers(Server::BROADCAST_CHANNEL_ADMINISTRATIVE);

            foreach ($consoles as $console) {
                if ($console instanceof ConsoleCommandSender) {
                    $this->consoleSender = $console;
                    break;
                }
            }
        }
    }
}