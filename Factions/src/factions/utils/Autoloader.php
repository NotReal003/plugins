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

namespace factions\utils;

use NetherGames\NGEssentials\thread\NGThreadPool;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pmmp\thread\Thread as NativeThread;
use RuntimeException;
use function dirname;
use function is_file;
use function pocketmine\critical_error;

class Autoloader extends AsyncTask
{
    public static function initAutoloader(): void
    {
        $bootstrap = dirname(__FILE__, 4) . '/vendor/autoload.php';
        if (is_file($bootstrap)) {
            require_once($bootstrap);

            if (NativeThread::getCurrentThread() === null) { // check if we are in the main thread
                $serverPool = Server::getInstance()->getAsyncPool();
                $serverPool->addWorkerStartHook(function (int $workerId) use ($serverPool): void {
                    $serverPool->submitTaskToWorker(new Autoloader(), $workerId);
                });

                $serverPool = NGThreadPool::getInstance();
                $serverPool->addWorkerStartHook(function (int $workerId) use ($serverPool): void {
                    $serverPool->submitTaskToWorker(new Autoloader(), $workerId);
                });
            }
        } else {
            critical_error("Composer autoloader not found at " . $bootstrap);
            critical_error("Please install/update Composer dependencies or use provided builds.");

            throw new RuntimeException("No composer autoloader were found.");
        }
    }

    public function onRun(): void
    {
        self::initAutoloader();
    }
}