<?php /** @noinspection PhpDocSignatureInspection */
/**
 *         _____ _          _     _            _
 *        / ____| |        | |   | |          | |
 *  __  _| (___ | | ___   _| |__ | | ___   ___| | __
 *  \ \/ /\___ \| |/ / | | | '_ \| |/ _ \ / __| |/ /
 *   >  < ____) |   <| |_| | |_) | | (_) | (__|   <
 *  /_/\_\_____/|_|\_\\__, |_.__/|_|\___/ \___|_|\_\
 *                     __/ |
 *                    |___/
 *
 * Copyright (C) 2016-2022 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder
 */

declare(strict_types=1);

namespace skyblock\utils;

use DirectoryIterator;
use Generator;
use GlobalLogger;
use libasyncio\compression\CompressionFormat;
use libasyncio\RecursiveCompressor;
use libMMO\item\enchantment\CustomEnchantment;
use libMMO\item\ItemStorage;
use pocketmine\block\tile\Container;
use pocketmine\block\tile\TileFactory;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\inventory\Inventory;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\utils\Filesystem;
use pocketmine\world\World;
use skyblock\islands\storage\IslandStorage;
use Symfony\Component\Filesystem\Path;

/**
 * Recursive islands world chunk checker, checks every world for every possible chests
 * then after it is done, the world will be deleted and the next world will be loaded.
 */
class RecursiveIslandCheck extends Task
{
    /** @var true[] */
    public array $itemInstances = [];
    /** @var int */
    public int $currentIsland = 0;
    /** @var int */
    public int $islandTakes = 0;
    /** @var true[] */
    public array $detected = [];

    public function __construct()
    {
        Database::executeSelectRaw("SELECT * FROM item_storage", [], function (array $rows): void {
            foreach ($rows as ['id' => $id]) {
                $this->itemInstances[$id] = true;
            }
        });
        Database::getMySQLDatabase()->waitAll();

        GlobalLogger::get()->info('Successfully loaded ' . count($this->itemInstances) . ' instances... ');
    }

    public function onRun(): void
    {
        $server = Server::getInstance();
        foreach ($this->getNextIsland() as $island) {
            if (!Server::getInstance()->isRunning()) {
                GlobalLogger::get()->info('Shutdown signal received ' . $island->getFilename() . ', take ' . $this->islandTakes . ' is not executed.');

                break;
            }

            if ($this->currentIsland > 40) {
                $this->islandTakes++;
                $this->currentIsland = 0;

                GlobalLogger::get()->info('Currently on island ' . $island->getFilename() . ', take ' . $this->islandTakes);
            }

            Filesystem::recursiveUnlink(Path::join($server->getDataPath(), 'worlds', 'backup_island_check'));

            $filePath = str_replace("." . CompressionFormat::ZSTD->getFileExtension(), '', $island->getRealPath());
            $islandXuid = str_replace("." . CompressionFormat::ZSTD->getFileExtension(), '', $island->getFilename());

            RecursiveCompressor::uncompress($filePath, Path::join($server->getDataPath(), 'worlds', 'backup_island_check'), CompressionFormat::ZSTD);

            if ($server->getWorldManager()->loadWorld('backup_island_check')) {
                $world = $server->getWorldManager()->getWorldByName('backup_island_check');

                /** @var Vector3[] $leftOvers */
                $leftOvers = $this->startWorldChecking($world, $islandXuid);

                if (count($leftOvers) > 0) {
                    foreach ($leftOvers as $tileLocation) {
                        GlobalLogger::get()->info("[AntiDupe] Found contraband item in island at position $tileLocation, $islandXuid.");
                    }
                }

                $server->getWorldManager()->unloadWorld($world);
            } else {
                GlobalLogger::get()->warning("Unable to load file " . $island->getFilename());
            }

            $this->currentIsland++;
        }

        foreach ($this->detected as $detected => $obj) {
            GlobalLogger::get()->info($detected);
            GlobalLogger::get()->info($detected);
        }
    }

    /**
     * @return Generator|DirectoryIterator[]
     */
    public function getNextIsland(): Generator
    {
        $dir = new DirectoryIterator(Path::join(IslandStorage::$islandStoragePath, 'PlayerIslands'));

        /** @var DirectoryIterator $fileInfo */
        foreach ($dir as $fileInfo) {
            if (!$fileInfo->isDot() && !str_contains($fileInfo->getFilename(), IslandStorage::ISLAND_BACKUP)) {
                yield $fileInfo;
            }
        }
    }

    public function startWorldChecking(World $world, string $islandXuid): array
    {
        $contraband = [];

        $containerInventory = [];
        foreach ($world->getProvider()->getAllChunks() as $coords => $chunkData) {
            if (count($chunkData->getData()->getTileNBT()) !== 0) {
                $tileFactory = TileFactory::getInstance();
                foreach ($chunkData->getData()->getTileNBT() as $k => $nbt) {
                    try {
                        $tile = $tileFactory->createFromData($world, $nbt);
                    } catch (SavedDataLoadingException $e) {
                        continue;
                    }
                    if ($tile === null) {
                        continue;
                    }

                    if ($tile instanceof Container) {
                        $containerInventory[] = $tile;
                    }
                }
            }
        }

        if (count($containerInventory) > 0) {
            // 0 -> count, 1 -> enchantments total.
            // This is per chunk, meaning that if the player duped PLENTY of god set, it will be detected
            $itemsLore = [];

            $loreVL = 0;
            $curseVL = 0;
            $maxLore = 0;
            foreach ($containerInventory as $tile) {
                $inv = $tile->getRealInventory();

                // Detect only custom enchantment (Detect if there's the same custom enchantment lore in a chunk)
                $loreTileVL = 0;
                $durabilityVL = $this->detectSameDurability($inv);
                foreach ($inv->getContents() as $item) {
                    if (ItemStorage::hasValidationId($item) && !isset($this->itemInstances[$item->getNamedTag()->getInt(ItemStorage::VALIDATION_ID, -1)])) {
                        $position = $tile->getPosition()->asVector3();

                        $contraband[World::blockHash($position->getX(), $position->getY(), $position->getZ())] = $position;
                    }

                    if (!($item instanceof Durable)) {
                        continue;
                    }

                    if (isset($itemsLore[$item->getStateId()])) {
                        foreach ($itemsLore[$item->getStateId()] as $lore) {
                            $maxLore = max($maxLore, count($item->getLore()));

                            if (!empty($item->getLore()) && $item->getLore() === $lore) {
                                $loreTileVL++;
                                break;
                            }
                        }
                    }

                    if ($item->getEnchantment(VanillaEnchantments::VANISHING()) !== null) {
                        $curseVL++;
                    }

                    if (!empty($item->getLore())) {
                        $itemsLore[$item->getStateId()][] = $item->getLore();
                    }
                }

                if ($durabilityVL > 0 || $loreTileVL > 0 || $curseVL > 8) {
                    $this->detected[$islandXuid] = true;

                    $v = $tile->getPosition()->asVector3();

                    GlobalLogger::get()->info("[AntiDupe] Violation detected for container, {$v->getX()} {$v->getY()} {$v->getZ()}, $islandXuid, max=0, dVl=$durabilityVL, lVl=$loreTileVL, maxLore=$maxLore, cVl=$curseVL");
                }

                $loreVL += $loreTileVL;

                $tile->close();
            }
        }

        return $contraband;
    }

    public function detectSameDurability(Inventory $inventory): int
    {
        $violation = 0;

        /** @var Item[] $durableItems */
        $durableItems = [];
        foreach ($inventory->getContents() as $item) {
            $enchantments = array_filter($item->getEnchantments(), function ($enchantment): bool {
                /** @phpstan-ignore-next-line */
                return $enchantment instanceof CustomEnchantment;
            });

            /** @phpstan-ignore-next-line */
            if ($item instanceof Durable && $item->getDamage() > 0 && count($enchantments) > 0) {
                if (!empty($durableItems)) {
                    foreach ($durableItems as $data) {
                        if ($data->equalsExact($item)) {
                            $violation++;
                        }
                    }
                }

                $durableItems[] = $item;
            }
        }

        return $violation;
    }
}