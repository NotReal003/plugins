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

namespace factions\commands;

use factions\task\ThreadSnipeTask;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use NetherGames\NGEssentials\thread\NGThreadPool;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;

/**
 * This is a debug program to check thread memory usage, used during ext-vanillagenerator development.
 *
 * @package factions\commands
 */
class ThreadMemoryCommand extends BaseCommand
{

    public function __construct(MMOPlugin $owningPlugin)
    {
        parent::__construct("threadsnipe", $owningPlugin);

        $this->setDescription("Developer thread memory snipe command.");
        $this->setPermission("nethergames.developer");
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        if (!$this->testPermission($sender)) {
            return true;
        }

        Await::f2c(function () use ($sender) {
            $sender->sendMessage(TextFormat::RED . "Please wait while the command tries to fetch all running workers memory usage.");

            $serverPool = Server::getInstance()->getAsyncPool();
            foreach ($serverPool->getRunningWorkers() as $id => $worker) {
                $serverPool->submitTaskToWorker(new ThreadSnipeTask(yield Await::RESOLVE_MULTI, $id), $id);
            }

            $serverMemory = yield Await::ALL;

            $serverPool = NGThreadPool::getInstance();
            foreach ($serverPool->getRunningWorkers() as $id => $worker) {
                $serverPool->submitTaskToWorker(new ThreadSnipeTask(yield Await::RESOLVE_MULTI, $id), $id);
            }

            $ngMemory = yield Await::ALL;

            $sender->sendMessage(TextFormat::GREEN . "--- " . TextFormat::RESET . "Server thread" . TextFormat::GREEN . " ---");
            foreach ($serverMemory as $data) {
                $sender->sendMessage(TextFormat::GOLD . "Worker #$data[1] [{$data[0][1]}]: " . TextFormat::YELLOW . round($data[0][0] / 1e+6, 3) . "MB");
            }

            foreach ($ngMemory as $data) {
                $sender->sendMessage(TextFormat::GOLD . "NGWorker #$data[1] [{$data[0][1]}]: " . TextFormat::YELLOW . round($data[0][0] / 1e+6, 3) . "MB");
            }
        });

        return true;
    }
}