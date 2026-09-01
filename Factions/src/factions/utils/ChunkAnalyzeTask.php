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

use GlobalLogger;
use libMMO\item\enchantment\CustomEnchantment;
use pocketmine\block\tile\Container;
use pocketmine\block\tile\TileFactory;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\inventory\Inventory;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\scheduler\Task;
use pocketmine\world\format\io\LoadedChunkData;
use pocketmine\world\World;

class ChunkAnalyzeTask extends Task
{
    /** @var World */
    private World $world;

    public function __construct(World $world)
    {
        $this->world = $world;
    }

    public function onRun(): void
    {
        $logger = GlobalLogger::get();

        // We can only do read-only here.
        foreach ($this->world->getProvider()->getAllChunks() as $rawChunk) {
            $chunkData = $rawChunk->getData();
            $containerInventory = [];
            if (count($chunkData->getTileNBT()) !== 0) {
                $tileFactory = TileFactory::getInstance();
                foreach ($chunkData->getTileNBT() as $k => $nbt) {
                    try {
                        $tile = $tileFactory->createFromData($this->world, $nbt);
                    } catch (SavedDataLoadingException $e) {
                        $logger->error("Bad tile entity data at list position $k: " . $e->getMessage());
                        $logger->logException($e);
                        continue;
                    }
                    if ($tile === null) {
                        $logger->warning("Deleted unknown tile entity type " . $nbt->getString("id", "<unknown>"));
                        continue;
                    }

                    if ($tile instanceof Container) {
                        $containerInventory[] = $tile;
                    }
                }
            }

            if (count($containerInventory) > 0) {
                foreach ($containerInventory as $tile) {
                    $inv = $tile->getRealInventory();

                    $contents = $inv->getContents();

                    $currentIndex = 0;
                    foreach ($contents as $slot => $item) {
                        foreach ($item->getLore() as $lore) {
                            if (str_contains($lore, 'Seller:') || str_contains($lore, 'Chance: ') || str_contains($lore, 'Click to close this crate') || str_contains($lore, 'Keys:') || str_contains($lore, 'Click to switch filter.') || str_contains($lore, 'Coins: ')) {
                                $currentIndex++;

                                unset($contents[$slot]);
                            }
                        }
                    }

                    if ($currentIndex > 0) {
                        $v = $tile->getPosition()->asVector3();

                        GlobalLogger::get()->info("[AntiDupe] Violation detected for container, {$v->getX()} {$v->getY()} {$v->getZ()}, max=0, dVl=$currentIndex");
                    }
                }
            }
        }

        GlobalLogger::get()->info("[AntiDupe] Anti-dupe scan is complete.");
    }

    public function detectSameDurability(Inventory $inventory): int
    {
        $violation = 0;

        /** @var Item[] $durableItems */
        $durableItems = [];
        foreach ($inventory->getContents() as $item) {
            $enchantments = array_filter($item->getEnchantments(), function ($enchantment): bool {
                return $enchantment instanceof CustomEnchantment;
            });

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

        if ($violation > 0) {
            GlobalLogger::get()->info("[AntiDupe] Container contains exact durability for $violation times.");
        }

        return $violation;
    }
}