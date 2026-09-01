<?php
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

use GlobalLogger;
use libMMO\player\Inventory;
use pocketmine\scheduler\Task;
use Throwable;

class PlayerAnalyzeTask extends Task
{
    /** @var array */
    private array $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function onRun(): void
    {
        foreach ($this->rows as $data) {
            $playerName = $data['player'];
            $playerXuid = $data['xuid'];

            $inventoryData = Inventory::convertStringToInventoryJSON($data['inventory']);

            $index = 0;
            foreach ($inventoryData as $tag => $inventory) {
                $contents = Inventory::convertJsonToContents($inventory);

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
                    $inventoryData[$tag] = Inventory::convertItemsToJson($contents);

                    $index += $currentIndex;
                }
            }

            if ($index > 0) {
                Database::executeChangeRaw("UPDATE player_data SET inventory = ? WHERE xuid = ?", [zstd_compress(json_encode($inventoryData)), $playerXuid]);

                GlobalLogger::get()->info("[AntiDupe] Found AuctionHouse violation for player $playerName.");
            }

            $index = 0;

            if (!empty($vaults = $data['vaults'] ?? '')) {
                $vaultsContent = self::jsonSafeDecode($vaults);

                foreach ($vaultsContent as $vaultId => $vault) {
                    $contents = Inventory::convertJsonToContents(self::jsonSafeDecode(zstd_uncompress(base64_decode($vault ?? ''))));

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
                        $vaultsContent[$vaultId] = base64_encode(zstd_compress(json_encode(Inventory::convertItemsToJson($contents))));

                        $index += $currentIndex;
                    }
                }

                if ($index > 0) {
                    GlobalLogger::get()->info("[AntiDupe] Found AuctionHouse violation for player $playerName in their vaults.");
                }
            }
        }

        Database::getMySQLDatabase()->waitAll();
    }

    private static function jsonSafeDecode(string $data): array
    {
        try {
            return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }
    }
}