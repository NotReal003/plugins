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
 * Copyright (C) 2016-2021 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder
 */

namespace skyblock\item;

use Closure;
use libMMO\item\CustomItemRegistry;
use libMMO\item\item\MiniHelperItem;
use libMMO\player\Inventory;
use pocketmine\inventory\BaseInventory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\TextFormat;
use RuntimeException;
use skyblock\entities\helpers\HelperManager;
use skyblock\entities\helpers\MiniHelper;

class CustomItemManager extends \libMMO\item\CustomItemManager
{
    public static function getMiniHelperFrom(MiniHelper $miniHelper): MiniHelperItem
    {
        /** @var BaseInventory $inventory */
        $inventory = $miniHelper->getHelperMenu()->getInventory();

        return self::getMiniHelper($miniHelper->getJobType(), $miniHelper->getTier(), $miniHelper->getSlots(), $miniHelper->getEfficiency(), $inventory);
    }

    public static function getMiniHelper(int $jobType, int $tier = MiniHelper::TIER_WOOD, int $slots = 1, int $efficiency = 0, ?BaseInventory $inventory = null): MiniHelperItem
    {
        $item = match ($jobType) {
            MiniHelper::LUMBERJACK => CustomItemRegistry::HELPER_LUMBERJACK(),
            MiniHelper::MINER => CustomItemRegistry::HELPER_MINER(),
            MiniHelper::HARVESTER => CustomItemRegistry::HELPER_HARVESTER(),
            default => throw new RuntimeException("Helper with job type $jobType is not found."),
        };

        $item->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GREEN . HelperManager::fromJobType($jobType) . ' Mini-Helper');

        $customBlockData = new CompoundTag();
        $customBlockData->setInt(MiniHelperItem::JOB_TYPE, $jobType);

        if ($tier !== MiniHelper::TIER_WOOD) {
            $customBlockData->setInt(MiniHelper::TIER_TAG, $tier);
        }
        if ($slots > 1) {
            $customBlockData->setInt(MiniHelper::SLOTS_TAG, $slots);
        }
        if ($efficiency > 0) {
            $customBlockData->setInt(MiniHelper::EFFICIENCY_TAG, $efficiency);
        }
        if ($inventory !== null) {
            $customBlockData->setTag(MiniHelper::HELPER_INVENTORY_TAG, Inventory::convertInventoryToNBT($inventory));
        }

        $item->setCustomBlockData($customBlockData);
        $item->setLore([
            '',
            TextFormat::RESET . TextFormat::LIGHT_PURPLE . 'Tier: ' . TextFormat::WHITE . $tier,
            TextFormat::RESET . TextFormat::AQUA . 'Slots: ' . TextFormat::WHITE . $slots,
            TextFormat::RESET . TextFormat::GOLD . 'Efficiency: ' . TextFormat::WHITE . $efficiency,
            '',
            TextFormat::RESET . TextFormat::GRAY . 'Click to spawn mini-helper.'
        ]);

        return $item;
    }
}