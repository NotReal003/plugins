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
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder
 */

namespace libMMO\player\enchantment;

use libMMO\item\enchantment\CustomEnchantment;
use libMMO\utils\RomanNumbers;
use libMMO\utils\Utils;
use NetherGames\NGEssentials\item\SimpleCustomItem;
use pocketmine\item\enchantment\Rarity;
use pocketmine\utils\TextFormat;

class EnchantmentUtils
{

    public static function updateLore(SimpleCustomItem $book): void
    {
        $lore = [];
        $ces = null;

        $enchantment = $book->getEnchantments()[array_key_first($book->getEnchantments())];
        if ($enchantment->getType() instanceof CustomEnchantment) { // Custom Enchantment
            $ces = $enchantment;
        }

        if ($ces !== null) {
            $lore = [
                '',
                TextFormat::RESET . 'Enchantments: ' . TextFormat::RESET . self::getColorCodeForRarity($enchantment->getType()->getRarity()) . $enchantment->getType()->getName() . ' ' . RomanNumbers::getRomanNumber($enchantment->getLevel())
            ];
        }

        $blockData = $book->getCustomBlockData();
        $successChance = $blockData->getInt('SuccessChance');
        $power = $blockData->getInt('Power');
        $objective = $blockData->getInt('Objective');

        $book->setLore(array_merge($lore, [
            '',
            TextFormat::RESET . TextFormat::GREEN . 'Success: ' . TextFormat::WHITE . $successChance . '%',
            TextFormat::RESET . TextFormat::RED . 'Destroy: ' . TextFormat::WHITE . (100 - $successChance) . '%',
            '',
            TextFormat::RESET . Utils::nicePercentFormat($power / $objective),
            TextFormat::RESET . TextFormat::AQUA . 'Power: ' . TextFormat::WHITE . $power,
            TextFormat::RESET . TextFormat::DARK_AQUA . 'Objective: ' . TextFormat::WHITE . $objective,
            '',
            TextFormat::RESET . TextFormat::GRAY . 'Drag this book onto an item to enchant it.'
        ]));
    }

    public static function getColorCodeForRarity(int $rarity): string
    {
        return match ($rarity) {
            Rarity::COMMON => TextFormat::GREEN,
            Rarity::UNCOMMON => TextFormat::BLUE,
            Rarity::RARE => TextFormat::RED,
            Rarity::MYTHIC => TextFormat::DARK_PURPLE,
            default => TextFormat::GRAY,
        };
    }

    public static function getVanillaTranslation(string $keyword): string
    {
        return match (str_replace('%', '', $keyword)) {
            "enchantment.lootBonus" => "Looting",
            "enchantment.arrowDamage" => "Power",
            "enchantment.arrowFire" => "Flame",
            "enchantment.arrowInfinite" => "Infinity",
            "enchantment.arrowKnockback" => "Punch",
            "enchantment.crossbowMultishot" => "Multishot",
            "enchantment.crossbowPiercing" => "Piercing",
            "enchantment.crossbowQuickCharge" => "Quick Charge",
            "enchantment.curse.binding" => "Curse of Binding",
            "enchantment.curse.vanishing" => "Curse of Vanishing",
            "enchantment.damage.all" => "Sharpness",
            "enchantment.damage.arthropods" => "Bane of Arthropods",
            "enchantment.damage.undead" => "Smite",
            "enchantment.digging" => "Efficiency",
            "enchantment.durability" => "Unbreaking",
            "enchantment.fire" => "Fire Aspect",
            "enchantment.fishingSpeed" => "Lure",
            "enchantment.frostwalker" => "Frost Walker",
            "enchantment.knockback" => "Knockback",
            "enchantment.lootBonusDigger" => "Fortune",
            "enchantment.lootBonusFishing" => "Luck of the Sea",
            "enchantment.mending" => "Mending",
            "enchantment.oxygen" => "Respiration",
            "enchantment.protect.all" => "Protection",
            "enchantment.protect.explosion" => "Blast Protection",
            "enchantment.protect.fall" => "Feather Falling",
            "enchantment.protect.fire" => "Fire Protection",
            "enchantment.protect.projectile" => "Projectile Protection",
            "enchantment.soul_speed" => "Soul Speed",
            "enchantment.swift_sneak" => "Swift Sneak",
            "enchantment.thorns" => "Thorns",
            "enchantment.untouching" => "Silk Touch",
            "enchantment.waterWalker" => "Depth Strider",
            "enchantment.waterWorker" => "Aqua Affinity",
            "enchantment.tridentChanneling" => "Channeling",
            "enchantment.tridentLoyalty" => "Loyalty",
            "enchantment.tridentRiptide" => "Riptide",
            "enchantment.tridentImpaling" => "Impaling",
            default => $keyword,
        };
    }
}