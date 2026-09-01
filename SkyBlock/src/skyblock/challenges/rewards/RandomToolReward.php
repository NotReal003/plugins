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
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Tool;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use function array_keys;
use function array_rand;
use function mt_rand;

class RandomToolReward extends CustomReward
{
    /** @var Tool[][] */
    private static ?array $tools = null;

    public static function getTools(): array
    {
        if (self::$tools === null) {
            self::$tools = [
                7 => [VanillaItems::DIAMOND_AXE(), VanillaItems::DIAMOND_PICKAXE(), VanillaItems::DIAMOND_SHOVEL(), VanillaItems::DIAMOND_HOE()],
                25 => [VanillaItems::GOLDEN_AXE(), VanillaItems::GOLDEN_PICKAXE(), VanillaItems::GOLDEN_SHOVEL(), VanillaItems::GOLDEN_HOE()],
                18 => [VanillaItems::IRON_AXE(), VanillaItems::IRON_PICKAXE(), VanillaItems::IRON_SHOVEL(), VanillaItems::IRON_HOE()],
                50 => [VanillaItems::STONE_AXE(), VanillaItems::STONE_PICKAXE(), VanillaItems::STONE_SHOVEL(), VanillaItems::STONE_HOE()],
            ];
        }

        return self::$tools;
    }

    public function give(Player $player): void
    {
        while (true) {
            $tools = self::getTools();

            $key = array_keys($tools)[array_rand(array_keys($tools))];

            $tools = $tools[$key];
            $item = $tools[array_rand($tools)];

            if (mt_rand(1, 100) <= $key) {
                $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), mt_rand(1, 4)));
                $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), mt_rand(1, 3)));
                $player->getInventory()->addItem($item);
                return;
            }
        }
    }

    public function getFormat(): string
    {
        return 'Random Enchanted Tool';
    }
}