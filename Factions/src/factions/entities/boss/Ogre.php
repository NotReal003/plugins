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

namespace factions\entities\boss;

use factions\item\CustomItemManager;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\utils\TextFormat;

class Ogre extends Boss
{
    /** @var array<callable(): Item> */
    private static array $itemDrops = [];

    public function __construct(Location $location, Skin $skin)
    {
        $this->bossId = self::OGRE;
        $this->speed = 0.2;
        $this->spawnMinion = false;
        $this->spawnHealth = 1500;
        $this->damage = 80;

        parent::__construct($location, $skin);

        self::$itemDrops = [
            static function () {
                $enchantments = CustomItemManager::getEnchantments();
                $enchantment = $enchantments[array_rand($enchantments)];
                return CustomItemManager::getEnchantedBook(mt_rand(0, 100), new EnchantmentInstance($enchantment));
            },
            static fn () => CustomItemManager::getPowerShard(mt_rand(1, 10000)),
            static fn () => CustomItemManager::getLuckyShard(mt_rand(1, 25)),
            static fn () => CustomItemManager::getKitItem('Legend', TextFormat::AQUA),
            static fn () => VanillaBlocks::BEDROCK()->asItem()->setCount(5),
            static fn () => CustomItemManager::getFlightOrb(true),
        ];
    }

    public function getDrops(): array
    {
        $items = self::$itemDrops;

        $drops = [];

        for ($i = 0; $i < 8; $i++) {
            $generator = $items[array_rand($items)];
            $drops[] = $generator();
        }

        return $drops;
    }
}