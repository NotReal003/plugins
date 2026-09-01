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

namespace libMMO\player;

use Exception;
use GlobalLogger;
use libMMO\MMOPlugin;
use libMMO\utils\Utils;
use NetherGames\NGEssentials\player\social\PlayerSocialInfo;
use NetherGames\NGEssentials\player\social\SocialManager;
use pocketmine\inventory\Inventory as PMInventory;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use RuntimeException;
use Throwable;
use function array_diff;
use function array_key_first;
use function array_keys;
use function count;
use function json_encode;
use function range;
use function strlen;
use function zstd_compress;
use function zstd_uncompress;
use const JSON_THROW_ON_ERROR;

class Inventory
{
    public const INVENTORY_TAG = 0;
    public const INVENTORY_ARMOR_TAG = 1;
    public const INVENTORY_ENDER_CHEST_TAG = 2;

    public static function convertInventoryToNBT(PMInventory $inventory): ListTag
    {
        $content = $inventory->getContents();

        $serialized = [];
        foreach ($content as $slot => $item) {
            $serialized[] = $item->nbtSerialize($slot);
        }

        return new ListTag($serialized);
    }

    public static function convertNBTToComponents(ListTag $listTag): array
    {
        $contents = [];

        foreach ($listTag as $itemSerialized) {
            if ($itemSerialized instanceof CompoundTag) {
                $contents[$itemSerialized->getByte('Slot')] = Item::nbtDeserialize($itemSerialized);
            }
        }

        return $contents;
    }

    /**
     * This is a method that adds an item to a players inventory, even they're not on line. This method returns a callable(bool $success);
     *
     * @param MMOPlugin $plugin
     * @param string $playerName
     * @param Item $item
     * @param callable|null $callable
     */
    public static function addItemToPlayer(MMOPlugin $plugin, string $playerName, Item $item, ?callable $callable = null): void
    {
        if (($player = $plugin->getServer()->getPlayerExact($playerName)) === null) {
            $plugin->getPlayerData()->loadValue($playerName, PlayerData::PLAYER_INVENTORY, static function (string $inventoryString) use ($playerName, $item, $callable, $plugin) {
                if ($plugin->getEssentials() === null) {
                    if ($callable !== null) {
                        $callable(false);
                    }
                } else {
                    SocialManager::requestPlayerInfo($playerName, function (?PlayerSocialInfo $info) use ($playerName, $item, $inventoryString, $callable, $plugin): void {
                        if ($info !== null) {
                            $inventoryData = Inventory::convertStringToInventoryJSON($inventoryString);

                            if (count($emptySlots = array_diff(range(0, 35), array_keys($inventoryData[Inventory::INVENTORY_TAG]))) !== 0) {
                                $inventoryData[Inventory::INVENTORY_TAG][$emptySlots[array_key_first($emptySlots)]] = Utils::zlibEncodeItem($item);

                                $playerData = $plugin->getPlayerData();
                                $playerData->setValue($playerName, PlayerData::PLAYER_INVENTORY, Inventory::convertInventoryJSONToString(
                                    $inventoryData[Inventory::INVENTORY_TAG],
                                    $inventoryData[Inventory::INVENTORY_ARMOR_TAG],
                                    $inventoryData[Inventory::INVENTORY_ENDER_CHEST_TAG]
                                ));
                                $playerData->saveValue($playerName, PlayerData::PLAYER_INVENTORY);
                                $playerData->unsetValue($playerName, PlayerData::PLAYER_INVENTORY);

                                if ($callable !== null) {
                                    $callable(true);
                                }
                                return;
                            }
                        }

                        if ($callable !== null) {
                            $callable(false);
                        }
                    });
                }
            });
        } elseif ($player->getInventory()->canAddItem($item)) {
            $player->getInventory()->addItem($item);

            if ($callable !== null) {
                $callable(true);
            }
        }
    }

    public static function convertStringToInventoryJSON(string $string, ?string $playerName = null, bool $throwOnError = false, bool $decodedData = false): array
    {
        try {
            if (strlen($string) === 0) {
                return $decodedData ? [[], ''] : [];
            }

            if (($decoded = zstd_uncompress($string)) === false) {
                if ($throwOnError) {
                    throw new RuntimeException("Zstd error: uncompressed data returned false for $playerName");
                }

                if ($playerName !== null) {
                    GlobalLogger::get()->info("Zstd error: uncompressed data returned false for $playerName, raw data=" . base64_encode($string));

                    $player = Server::getInstance()->getPlayerExact($playerName);
                    if ($player !== null && $player->isConnected()) {
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Your inventory data was not able to be loaded, contact a Faction/SkyBlock Taskforce with your IGN to resolve this issue.');
                    }
                } else {
                    GlobalLogger::get()->info("Zstd error: uncompressed data returned false, raw data=" . base64_encode($string));
                }

                return $decodedData ? [[], ''] : [];
            }

            $decode = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);

            return $decodedData ? [$decode, $decoded] : $decode;
        } catch (Throwable $ex) {
            GlobalLogger::get()->logException($ex);
            return $decodedData ? [[], ''] : [];
        }
    }

    public static function convertInventoryJSONToString(array $inventoryJSON, array $armorInventoryJSON, array $chestInventoryJSON): string
    {
        try {
            return zstd_compress(json_encode([
                self::INVENTORY_TAG => $inventoryJSON,
                self::INVENTORY_ARMOR_TAG => $armorInventoryJSON,
                self::INVENTORY_ENDER_CHEST_TAG => $chestInventoryJSON,
            ], JSON_THROW_ON_ERROR));
        } catch (Exception) {
            return '';
        }
    }

    public static function convertJsonToContents(array $inventoryData): array
    {
        $items = [];

        foreach ($inventoryData as $slot => $itemData) {
            $items[$slot] = Utils::decodeItem($itemData);
        }

        return Utils::doInventoryCleanup($items);
    }

    public static function convertInventoryToJson(PMInventory $inventory): array
    {
        return self::convertItemsToJson(Utils::doInventoryCleanup($inventory->getContents()));
    }

    /**
     * @param Item[] $contents
     * @return string[]
     */
    public static function convertItemsToJson(array $contents): array
    {
        $serialized = [];
        foreach ($contents as $slot => $item) {
            if ($item->isNull()) {
                continue;
            }

            $serialized[$slot] = Utils::zlibEncodeItem($item);
        }

        return $serialized;
    }
}