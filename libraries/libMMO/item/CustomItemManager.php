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

namespace libMMO\item;

use libMMO\item\enchantment\Enchantment;
use libMMO\item\item\FlyingOrbItem;
use libMMO\item\item\MoneyPouchItem;
use libMMO\player\enchantment\EnchantmentUtils;
use NetherGames\NGEssentials\item\SimpleCustomItem;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\Rarity;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\TextFormat;

class CustomItemManager
{
    public const FEATURE_TAG = "Feature";

    public static function getLuckyShard(int $increase): SimpleCustomItem
    {
        $increase = min($increase, 100);

        return CustomItemRegistry::LUCKY_SHARD()
            ->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::AQUA . 'Luck Shard')
            ->setCustomBlockData(CompoundTag::create()
                ->setTag("LuckyShard", new CompoundTag())
                ->setInt("Increase", $increase)
            )
            ->setLore(['', TextFormat::RESET . TextFormat::DARK_AQUA . 'Increase: ' . TextFormat::WHITE . $increase . '%', '', TextFormat::RESET . TextFormat::GRAY . 'Drag this shard onto an Enchantment', TextFormat::RESET . TextFormat::GRAY . 'Book to increase its success rate.']);
    }

    public static function getPowerShard(int $increase): SimpleCustomItem
    {
        return CustomItemRegistry::POWER_SHARD()
            ->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::AQUA . 'Power Shard')
            ->setCustomBlockData(CompoundTag::create()
                ->setTag("PowerShard", new CompoundTag())
                ->setInt("Increase", $increase)
            )
            ->setLore(['', TextFormat::RESET . TextFormat::DARK_AQUA . 'Power: ' . TextFormat::WHITE . $increase, '', TextFormat::RESET . TextFormat::GRAY . 'Drag this shard onto an Enchantment', TextFormat::RESET . TextFormat::GRAY . 'Book to increase its power.']);
    }

    public static function getKitItem(string $title, string $color): SimpleCustomItem
    {
        return CustomItemRegistry::KIT()
            ->setNamedTag(CompoundTag::create()->setString('title', $title))
            ->setCustomName(TextFormat::RESET . TextFormat::BOLD . $color . $title . ' Kit')
            ->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to redeem ' . $title . ' kit.']);
    }

    public static function getEnchantedBook(int $successChance, EnchantmentInstance $enchantment): SimpleCustomItem
    {
        $successChance = min($successChance, 100);

        $rarity = $enchantment->getType()->getRarity();
        if ($rarity === Rarity::MYTHIC) {
            $book = CustomItemRegistry::ENCHANTED_BOOK_MYTHICAL();
        } else if ($rarity === Rarity::COMMON) {
            $book = CustomItemRegistry::ENCHANTED_BOOK_COMMON();
        } else if ($rarity === Rarity::RARE) {
            $book = CustomItemRegistry::ENCHANTED_BOOK_RARE();
        } else {
            $book = CustomItemRegistry::ENCHANTED_BOOK_UNCOMMON();
        }

        $book = $book
            ->setCustomName(TextFormat::RESET . TextFormat::YELLOW . 'Enchantment Book')
            ->addEnchantment($enchantment)
            ->setCustomBlockData(CompoundTag::create()
                ->setString("Type", "Specific")
                ->setInt("SuccessChance", $successChance)
                ->setInt("Power", $enchantment->getLevel() === $enchantment->getType()->getMaxLevel() ? $enchantment->getLevel() * 5000 : 0)
                ->setInt("Objective", $enchantment->getLevel() * 5000));
        EnchantmentUtils::updateLore($book);

        return $book;
    }

    public static function getRandomEnchantedBook(int $rarity): SimpleCustomItem
    {
        if ($rarity === Rarity::MYTHIC) {
            $book = CustomItemRegistry::ENCHANTED_BOOK_MYTHICAL();
        } else if ($rarity === Rarity::COMMON) {
            $book = CustomItemRegistry::ENCHANTED_BOOK_COMMON();
        } else if ($rarity === Rarity::RARE) {
            $book = CustomItemRegistry::ENCHANTED_BOOK_RARE();
        } else {
            $book = CustomItemRegistry::ENCHANTED_BOOK_UNCOMMON();
        }

        return $book
            ->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::YELLOW . 'Enchantment Book')
            ->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Interact with this book to receive a random ' . TextFormat::GOLD . (Enchantment::RARITY_NAMES[$rarity] ?? 'Unknown') . TextFormat::GRAY . ' enchant!'])
            ->setCustomBlockData(CompoundTag::create()
                ->setString("Type", "Random")
                ->setInt("Rarity", $rarity));
    }

    public static function getFlightOrb(bool $factionMessage = false): FlyingOrbItem
    {
        return CustomItemRegistry::ORB_OF_FLIGHT()
            ->setNamedTag(CompoundTag::create()->setString(self::FEATURE_TAG, "orb_of_flight"))
            ->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::AQUA . 'Orb of Flight')
            ->setLore($factionMessage ? [
                '',
                TextFormat::RESET . TextFormat::AQUA . "Click anywhere to enable flight",
                TextFormat::RESET . TextFormat::AQUA . 'before the server restarts.',
                '',
                TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . 'WARNING: ' . TextFormat::RESET . TextFormat::RED . "Flight will be disabled if",
                TextFormat::RESET . TextFormat::RED . 'you attempt to fight or in other',
                TextFormat::RESET . TextFormat::RED . 'faction claims.'
            ] : [
                '',
                TextFormat::RESET . TextFormat::AQUA . "Click anywhere to enable flight",
                TextFormat::RESET . TextFormat::AQUA . 'before the server restarts.',
                '',
            ]);
    }

    public static function getMoneyPouch(int $amount): MoneyPouchItem
    {
        return CustomItemRegistry::MONEY_POUCH()
            ->setNamedTag(CompoundTag::create()->setInt(MoneyPouchItem::TAG_VALUE, $amount))
            ->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::LIGHT_PURPLE . 'Money Pouch')
            ->setLore(['', TextFormat::RESET . TextFormat::YELLOW . 'Value: ' . TextFormat::WHITE . '$' . number_format($amount), '', TextFormat::RESET . TextFormat::GRAY . 'Click to redeem money pouch.']);
    }
}