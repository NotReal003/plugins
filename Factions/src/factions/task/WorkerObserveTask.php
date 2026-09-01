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

use GlobalLogger;
use pocketmine\scheduler\AsyncPoolWorkerEntry;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use SOFe\AwaitGenerator\Await;

/**
 * A task responsible to monitor the server's thread pool workers memory usage.
 * It has come to mind that the current JIT bug is "unfixable" ""yet"", for now, if my logic is correct, if
 * the thread is killed, all the memory will be released and a new one will be clean from memory.
 *
 * @package factions\task
 */
class WorkerObserveTask extends Task
{
    private const MAXIMUM_MEMORY_THRESHOLD = 2.2e+8; // 220MB

    /**  @noinspection PhpStatementHasEmptyBodyInspection */
    public function onRun(): void
    {
        Await::f2c(function () {
            $serverPool = Server::getInstance()->getAsyncPool();
            foreach ($serverPool->getRunningWorkers() as $id => $worker) {
                $serverPool->submitTaskToWorker(new ThreadSnipeTask(yield Await::RESOLVE_MULTI, $id), $id);
            }

            $threadData = yield Await::ALL;

            foreach ($threadData as $worker) {
                $workerMemory = $worker[0][0];
                $workerId = $worker[1];

                if ($workerMemory >= self::MAXIMUM_MEMORY_THRESHOLD) {
                    GlobalLogger::get()->emergency("Worker #$workerId - Memory threshold limit reached, killing worker and starting a new worker.");

                    while ($serverPool->collectTasksFromWorker($workerId)) {
                        // NOOP
                    }

                    GlobalLogger::get()->emergency("Worker #$workerId - Starting worker.");

                    (function () use ($workerId) {
                        /** @var AsyncPoolWorkerEntry $entry */
                        $entry = $this->workers[$workerId];

                        $entry->worker->quit();
                        $this->eventLoop->removeNotifier($entry->sleeperNotifierId);
                        unset($this->workers[$workerId]);

                        $this->getWorker($workerId);
                    })->call($serverPool);

                    GlobalLogger::get()->emergency("Worker #$workerId - Worker memory reset.");
                }
            }
        });
    }
}