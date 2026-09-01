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

namespace libMMO\utils\async;

use GlobalLogger;
use libMMO\utils\async\event\ScheduledBlockUpdateEvent;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\timings\TimingsHandler;
use pocketmine\world\World;

/**
 * Moves scheduled block updates and entity ticking out of the world's own tick and into a budgeted task.
 *
 * Despite the name nothing here runs on another thread. "Async" means the work is decoupled from the
 * world tick, not concurrent with it: each pass spends at most EXECUTION_THRESHOLD nanoseconds, and
 * whatever is left over is carried to the next tick instead of stalling the server.
 *
 * The trade is latency for a stable tick rate. Work that cannot keep up accumulates, so both queues are
 * bounded — block updates older than the garbage threshold are discarded, and the entity backlog drops
 * its oldest tick once it grows past MAX_ENTITY_TICK_BACKLOG.
 */
class AsyncWorldTicker extends Task
{
    /** Queued block updates older than this many ticks (200 = 10s at 20 TPS) are dropped, not replayed. */
    public const GARBAGE_BLOCK_TICK_THRESHOLD = 200;
    /** Time budget per pass, in nanoseconds, to match hrtime(). 5e+6 ns = 5ms, a quarter of a tick. */
    public const EXECUTION_THRESHOLD = 5e+6;
    /** Pending entity tick buckets tolerated before the oldest is dropped to cap memory growth. */
    public const MAX_ENTITY_TICK_BACKLOG = 35;

    /** @var AsyncWorldTicker|null */
    private static ?AsyncWorldTicker $ticker = null;

    /**
     * Keyed [tick the updates are due][world folder name].
     *
     * @var AsyncBlockUpdateEntry[][]
     */
    private array $blockUpdates = [];
    /** @var Entity[][] */
    private array $entitiesTicker = [];
    /** @var Entity[] */
    public array $updateEntities = [];

    /** @var TimingsHandler */
    private TimingsHandler $scheduledBlockUpdates;
    /** @var TimingsHandler */
    private TimingsHandler $entitiesTickUpdate;
    /** @var TimingsHandler */
    private TimingsHandler $entitiesSorting;

    private int $garbageBlocksThreshold;
    private int $executionThreshold;

    private bool $enableBlockUpdates;
    private bool $enableEntityUpdates;

    public function __construct(array $settings = [
        'enable_block_async_updates' => true,
        'enable_entities_async_updates' => true,
        'update_block_garbage_limit' => self::GARBAGE_BLOCK_TICK_THRESHOLD,
        'execution_threshold' => self::EXECUTION_THRESHOLD
    ])
    {
        $this->garbageBlocksThreshold = $settings['update_block_garbage_limit'] ?? self::GARBAGE_BLOCK_TICK_THRESHOLD;
        $this->executionThreshold = (int)($settings['execution_threshold'] ?? self::EXECUTION_THRESHOLD);
        $this->enableBlockUpdates = $settings['enable_block_async_updates'] ?? false;
        $this->enableEntityUpdates = $settings['enable_entities_async_updates'] ?? false;

        self::$ticker = $this;

        $this->scheduledBlockUpdates = new TimingsHandler("Async Scheduled Block Updates");
        $this->entitiesSorting = new TimingsHandler("Async Entity Sorting Algorithm");
        $this->entitiesTickUpdate = new TimingsHandler("Async Scheduled Entity Updates");
    }

    public static function getInstance(): ?AsyncWorldTicker
    {
        return self::$ticker;
    }

    public function onRun(): void
    {
        $currentTick = Server::getInstance()->getTick();
        $wm = Server::getInstance()->getWorldManager();

        if ($this->enableBlockUpdates) {
            $this->collectDelayedBlockTicks();
            $this->collectAllBlockUpdates();

            $this->scheduledBlockUpdates->startTiming();

            $start = hrtime(true);
            foreach ($this->blockUpdates as $serverTick => $entryRaw) {
                if ($currentTick < $serverTick) {
                    continue;
                }

                foreach ($entryRaw as $worldName => $entry) {
                    $world = $wm->getWorldByName($worldName);

                    $queue = $entry->blockUpdates;
                    while (!$queue->isEmpty()) {
                        $vec = $queue->dequeue();

                        if (!$world->isInLoadedTerrain($vec)) {
                            continue;
                        }

                        $block = $world->getBlock($vec);

                        $ev = new ScheduledBlockUpdateEvent($block);
                        $ev->call();

                        if (!$ev->isCancelled()) {
                            $block->onScheduledUpdate();
                        }

                        if ((hrtime(true) - $start) >= $this->executionThreshold) {
                            GlobalLogger::get()->debug("$worldName - world tick exceeded global tick threshold, stepping world tick.");
                            break 3;
                        }
                    }
                }

                unset($this->blockUpdates[$serverTick]);
            }

            $this->scheduledBlockUpdates->stopTiming();
        }

        if ($this->enableEntityUpdates) {
            $this->entitiesSorting->startTiming();
            foreach ($wm->getWorlds() as $world) {
                foreach ($world->updateEntities as $id => $entity) {
                    // Only entities that opt into async ticking are collected; players must never be.
                    if ($entity instanceof AsyncModularEntity) {
                        $this->updateEntities[$id] = $entity;

                        unset($world->updateEntities[$id]);
                    }
                }
            }

            $this->entitiesTicker[$currentTick] = $this->updateEntities;
            $this->entitiesSorting->stopTiming();

            $this->entitiesTickUpdate->startTiming();

            $start = hrtime(true);
            foreach ($this->entitiesTicker as $tick => $entryPerTick) {
                foreach ($entryPerTick as $id => $entity) {
                    $tickDiff = $currentTick - $entity->lastUpdate;

                    // A non-positive delta means the world already ticked this entity on the current tick,
                    // which should be impossible: entities handed to this ticker are removed from the
                    // world's update list above, so the world is no longer responsible for them. If it
                    // happens anyway the entity is double-ticked, so skip it rather than tick it twice.
                    if ($tickDiff <= 0) {
                        continue;
                    }

                    if ($entity->isClosed() || !$entity->onUpdate($tick)) {
                        unset($this->updateEntities[$id]);
                    }
                    if ($entity->isFlaggedForDespawn()) {
                        $entity->close();
                    }

                    unset($this->entitiesTicker[$tick][$id]);

                    if ((hrtime(true) - $start) >= $this->executionThreshold) {
                        GlobalLogger::get()->debug("Entity tick exceeded global tick threshold, stepping entities tick, backlog " . count($entryPerTick) . ' for tick ' . $tick . ' with size of ' . count($this->entitiesTicker));
                        break 2;
                    }
                }

                unset($this->entitiesTicker[$tick]);
            }

            // Falling behind faster than the budget can catch up. Left alone the pending buckets would
            // grow without bound until the server runs out of memory, so drop the oldest tick's work.
            if (count($this->entitiesTicker) > self::MAX_ENTITY_TICK_BACKLOG) {
                GlobalLogger::get()->debug("Entity tick exceeded global tick measure, jumping ticks");

                unset($this->entitiesTicker[array_key_first($this->entitiesTicker)]);
            }

            $this->entitiesTickUpdate->stopTiming();
        }
    }

    /**
     * Discards block updates that have fallen too far behind the current tick.
     *
     * Once a bucket is this stale, replaying it would apply updates the world has long since moved past,
     * so dropping it is preferable to executing it late.
     */
    private function collectDelayedBlockTicks(): void
    {
        $currentTick = Server::getInstance()->getTick();

        foreach ($this->blockUpdates as $tick => $data) {
            if ($tick <= ($currentTick - $this->garbageBlocksThreshold)) {
                unset($this->blockUpdates[$tick]);
            }
        }
    }

    /**
     * Drains each world's pending scheduled block updates into this ticker's own queue.
     *
     * The closure is rebound to the World with ->call() so it can reach scheduledBlockUpdateQueue and
     * scheduledBlockUpdateQueueIndex, which are private. That is what the inspection suppressions below
     * are for; there is no public API for taking ownership of these updates.
     *
     * @noinspection PhpUndefinedFieldInspection
     * @noinspection PhpUndefinedMethodInspection
     */
    private function collectAllBlockUpdates(): void
    {
        foreach (Server::getInstance()->getWorldManager()->getWorlds() as $world) {
            // Collect everything due up to and including the next tick, filed under that tick. onRun()
            // only runs buckets whose tick has arrived, so these execute on the following pass — the one
            // tick of slack is what lets an update scheduled during this tick be collected before it runs.
            $currentTick = Server::getInstance()->getTick() + 1;

            /** @var AsyncBlockUpdateEntry $blockUpdateEntry */
            $blockUpdateEntry = (function () use ($currentTick): AsyncBlockUpdateEntry {
                $entry = new AsyncBlockUpdateEntry();

                while ($this->scheduledBlockUpdateQueue->count() > 0 and $this->scheduledBlockUpdateQueue->current()["priority"] <= $currentTick) {
                    /** @var Vector3 $vec */
                    $vec = $this->scheduledBlockUpdateQueue->extract()["data"];
                    unset($this->scheduledBlockUpdateQueueIndex[World::blockHash($vec->x, $vec->y, $vec->z)]);
                    if (!$this->isInLoadedTerrain($vec)) {
                        continue;
                    }

                    $entry->blockUpdates->enqueue($vec);
                }

                return $entry;
            })->call($world);

            $this->blockUpdates[$currentTick][$world->getFolderName()] = $blockUpdateEntry;
        }
    }

    public function clearEntities(): void
    {
        $this->entitiesTicker = [];
        $this->updateEntities = [];
    }
}