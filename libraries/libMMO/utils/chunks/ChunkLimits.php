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

namespace libMMO\utils\chunks;

use ArrayIterator;
use libMMO\MMOPlugin;
use libMMO\utils\BaseListener;
use muqsit\asynciterator\AsyncIterator;
use muqsit\asynciterator\handler\AsyncForeachResult;
use pocketmine\block\tile\Tile;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\world\ChunkLoadEvent;
use pocketmine\event\world\ChunkUnloadEvent;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\world\World;

abstract class ChunkLimits extends BaseListener
{
    // TODO: Entity limits?
    // TODO: SubChunk redstone tick limit?

    /** @var int[] */
    protected array $tileLimit = [];        /* Proposed tile limit per chunk */
    /** @var int[][][] */
    private array $cachedTileData = [];
    /** @var AsyncIterator */
    private AsyncIterator $iterator;
    /** @var true[] */
    private array $chunkLoadQueue = [];

    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct($plugin);

        $this->iterator = new AsyncIterator($plugin->getScheduler());

        $plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
    }

    /**
     * @param BlockPlaceEvent $event
     * @priority HIGHEST
     */
    public function onBlockPlaceEvent(BlockPlaceEvent $event): void
    {
        $player = $event->getPlayer();
        $tx = $event->getTransaction();

        foreach ($tx->getBlocks() as [$x, $y, $z, $block]) {
            $pos = new Position($x, $y, $z, $player->getWorld());

            $tileInfo = $block->getIdInfo()->getTileClass();
            $chunk = $pos->getWorld()->getChunk($chunkX = $pos->getFloorX() >> 4, $chunkZ = $pos->getFloorZ() >> 4);

            // Something tells me that the block could be placed even the chunk wasn't loaded?
            if ($chunk === null || $tileInfo === null || !in_array($tileInfo, array_keys($this->tileLimit))) {
                return;
            }

            $tileData = $this->getTileData($chunkX, $chunkZ, $pos->getWorld()->getFolderName());
            if ($tileData === null) {
                $player->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . "Please wait for the chunk to load.");
            } else if (($tileData[$tileInfo] ?? 0) >= $this->tileLimit[$tileInfo]) {
                $player->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . "You have reached maximum tiles per chunk.");
            } else {
                $this->increaseTile($chunkX, $chunkZ, $pos->getWorld()->getFolderName(), $tileInfo);
                return;
            }

            $event->cancel();
        }
    }

    /**
     * @param BlockBreakEvent $event
     * @priority HIGHEST
     */
    public function onBlockBreakEvent(BlockBreakEvent $event): void
    {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $pos = $block->getPosition();

        $tileInfo = $block->getIdInfo()->getTileClass();
        $chunk = $pos->getWorld()->getChunk($chunkX = $pos->getFloorX() >> 4, $chunkZ = $pos->getFloorZ() >> 4);

        // Something tells me that the block could be placed even the chunk wasn't loaded?
        if ($chunk === null || $tileInfo === null || !in_array($tileInfo, array_keys($this->tileLimit))) {
            return;
        }

        $tileData = $this->getTileData($chunkX, $chunkZ, $pos->getWorld()->getFolderName());
        if ($tileData === null) {
            $player->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . "Please wait for the chunk to load.");
        } else {
            $this->decreaseTile($chunkX, $chunkZ, $pos->getWorld()->getFolderName(), $tileInfo);
            return;
        }

        $event->cancel();
    }

    /**
     * @param ChunkUnloadEvent $event
     * @priority LOWEST
     */
    public function onChunkUnloadEvent(ChunkUnloadEvent $event): void
    {
        unset($this->cachedTileData[$event->getWorld()->getFolderName()][World::chunkHash($event->getChunkX(), $event->getChunkZ())]);
    }

    /**
     * @param ChunkLoadEvent $event
     * @priority LOWEST
     */
    public function onChunkLoadEvent(ChunkLoadEvent $event): void
    {
        // I wonder if someone want to lag the server by placing thousand of chests in a chunk,
        // well it wont work anyways :)
        $chunkHash = World::chunkHash($event->getChunkX(), $event->getChunkZ());
        $worldName = $event->getWorld()->getFolderName();

        $tileLimit = $this->tileLimit;
        $cachedTile = &$this->cachedTileData;

        $this->chunkLoadQueue[$chunkHash] = true;
        $this->iterator->forEach(new ArrayIterator($event->getChunk()->getTiles()), 50)
            ->as(static function (int $index, Tile $tile) use ($tileLimit, $worldName, $chunkHash, &$cachedTile): AsyncForeachResult {
                $className = get_class($tile);
                if (array_key_exists($className, $tileLimit) !== false) {
                    if (!isset($cachedTile[$worldName][$chunkHash][$className])) {
                        $cachedTile[$worldName][$chunkHash][$className] = 1;
                    } else {
                        $cachedTile[$worldName][$chunkHash][$className]++;
                    }
                }

                return AsyncForeachResult::CONTINUE();
            })->onCompletion(function () use ($chunkHash): void {
                unset($this->chunkLoadQueue[$chunkHash]);
            });
    }

    public function getTileData(int $chunkX, int $chunkZ, string $worldName): ?array
    {
        $hash = World::chunkHash($chunkX, $chunkZ);
        if (isset($this->chunkLoadQueue[$hash])) {
            return null;
        }

        return $this->cachedTileData[$worldName][World::chunkHash($chunkX, $chunkZ)] ?? [];
    }

    private function increaseTile(int $chunkX, int $chunkZ, string $worldName, string $tileInfo): void
    {
        $chunkHash = World::chunkHash($chunkX, $chunkZ);
        if (!isset($this->cachedTileData[$worldName][$chunkHash][$tileInfo])) {
            $this->cachedTileData[$worldName][$chunkHash][$tileInfo] = 1;
        } else {
            $this->cachedTileData[$worldName][$chunkHash][$tileInfo]++;
        }
    }

    private function decreaseTile(int $chunkX, int $chunkZ, string $worldName, string $tileInfo): void
    {
        $chunkHash = World::chunkHash($chunkX, $chunkZ);
        if (isset($this->cachedTileData[$worldName][$chunkHash][$tileInfo])) {
            $tiles = $this->cachedTileData[$worldName][$chunkHash][$tileInfo]--;
            if ($tiles <= 0) {
                unset($this->cachedTileData[$worldName][$chunkHash][$tileInfo]);
            }
        }
    }
}