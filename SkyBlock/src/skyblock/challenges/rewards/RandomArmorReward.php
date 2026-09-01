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
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew
 *
 */
declare(strict_types=1);

namespace skyblock\challenges\rewards;

use libMMO\challenges\reward\CustomReward;
use pocketmine\item\Armor;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use function mt_rand;

class RandomArmorReward extends CustomReward
{
    /** @var Armor[][] */
    private static ?array $armor = null;

    public static function getArmor(): array
    {
        if (self::$armor === null) {
            self::$armor = [
                7 => [VanillaItems::DIAMOND_CHESTPLATE(), VanillaItems::DIAMOND_LEGGINGS(), VanillaItems::DIAMOND_BOOTS(), VanillaItems::DIAMOND_HELMET()],
                25 => [VanillaItems::GOLDEN_CHESTPLATE(), VanillaItems::GOLDEN_LEGGINGS(), VanillaItems::GOLDEN_BOOTS(), VanillaItems::GOLDEN_HELMET()],
                18 => [VanillaItems::IRON_CHESTPLATE(), VanillaItems::IRON_LEGGINGS(), VanillaItems::IRON_BOOTS(), VanillaItems::IRON_HELMET()],
                50 => [VanillaItems::LEATHER_TUNIC(), VanillaItems::LEATHER_PANTS(), VanillaItems::LEATHER_BOOTS(), VanillaItems::LEATHER_CAP()]
            ];
        }

        return self::$armor;
    }

    public function give(Player $player): void
    {
        $armor = self::getArmor();

        while (true) {
            $key = array_keys($armor)[array_rand(array_keys($armor))];

            $armors = $armor[$key];
            $item = $armors[array_rand($armors)];

            if (mt_rand(1, 100) <= $key) {
                $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), mt_rand(1, 5)));
                $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), mt_rand(1, 5)));
                $player->getInventory()->addItem($item);
                return;
            }
        }
    }

    public function getFormat(): string
    {
        return 'Random Enchanted Armor';
    }
}