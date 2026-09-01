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

namespace libMMO\item\enchantment;

use libMMO\player\enchantment\EnchantmentManager;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\item\enchantment\Enchantment as PMEnchantment;
use pocketmine\item\enchantment\Rarity;
use ReflectionProperty;
use function array_filter;
use function array_rand;

class Enchantment extends PMEnchantment implements CustomEnchantment
{
    public const RARITY_NAMES = [
        Rarity::COMMON => 'Common',
        Rarity::RARE => 'Rare',
        Rarity::MYTHIC => 'Mythical'
    ];

    public function __construct(string $name, int $rarity, int $primaryItemFlags, int $secondaryItemFlags, int $maxLevel)
    {
        parent::__construct($name, $rarity, $primaryItemFlags, $secondaryItemFlags, $maxLevel);
    }

    public static function getRandomEnchantmentFromRarity(int $rarity): ?PMEnchantment
    {
        $enchIdMap = EnchantmentIdMap::getInstance();
        $ref = new ReflectionProperty(EnchantmentIdMap::class, "idToEnum");
        $enchantments = $ref->getValue($enchIdMap);

        $enchantments = array_filter($enchantments, static function (?PMEnchantment $enchantment) use ($rarity): bool {
            return $enchantment !== null && $enchantment->getRarity() === $rarity && !EnchantmentManager::isEnchantExcluded($enchantment);
        });

        return $enchantments[array_rand($enchantments)] ?? null;
    }
}