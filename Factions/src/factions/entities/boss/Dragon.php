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

class Dragon extends Boss
{
    protected const HEALTH_DEFAULT = 1000;

    /** @var array<callable(): Item> */
    private static array $itemDrops = [];

    public function __construct(Location $location, Skin $skin)
    {
        $this->bossId = self::DRAGON;
        $this->speed = 0.75;
        $this->spawnMinion = false;
        $this->spawnHealth = 1000;
        $this->damage = 35;

        parent::__construct($location, $skin);

        self::$itemDrops = [
            static function () {
                $enchantments = CustomItemManager::getEnchantments();
                $enchantment = $enchantments[array_rand($enchantments)];
                return CustomItemManager::getEnchantedBook(mt_rand(0, 100), new EnchantmentInstance($enchantment));
            },
            static fn () => CustomItemManager::getPowerShard(mt_rand(1, 5000)),
            static fn () => CustomItemManager::getLuckyShard(mt_rand(1, 15)),
            static fn () => CustomItemManager::getKitItem('Emerald', TextFormat::GREEN),
            static fn () => VanillaBlocks::BEDROCK()->asItem()->setCount(8),
            static fn () => CustomItemManager::getFlightOrb(true),
        ];

        $this->setScale(2);
    }

    public function getDrops(): array
    {
        $items = self::$itemDrops;

        $drops = [];
        for ($i = 0; $i < 7; $i++) {
            $generator = $items[array_rand($items)];
            $drops[] = $generator();
        }

        return $drops;
    }
}