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

namespace factions\crates;

use factions\Factions;
use factions\item\CustomItemManager;
use factions\player\tags\TagManager;
use factions\utils\Area;
use libMMO\crates\CrateListener;
use libMMO\crates\loottables\CrateLootTable;
use libMMO\crates\loottables\CrateLootTableEntry;
use libMMO\item\CustomItemRegistry;
use libMMO\MMOPlugin;
use NetherGames\NGEssentials\NGEssentials;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\enchantment\Rarity;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class CrateManager extends \libMMO\crates\CrateManager
{
    public const COMMON = 0;
    public const RARE = 1;
    public const MYTHIC = 2;
    public const KOTH = 3;

    public function __construct(MMOPlugin $instance)
    {
        parent::__construct($instance);

        CrateListener::$crateRotations = 20;

        $ultraKit = CustomItemManager::getKitItem('Ultra', TextFormat::GOLD);
        $emeraldKit = CustomItemManager::getKitItem('Emerald', TextFormat::GREEN);
        $legendKit = CustomItemManager::getKitItem('Legend', TextFormat::AQUA);

        $tags = TagManager::getRandomTag(3);

        // Crate 1: Vector3(x=15.5,y=1.9,z=-21.5)
        // Crate 2: Vector3(x=10.5,y=1.9,z=-22.5)
        // Crate 3: Vector3(x=5.5,y=1.9,z=-21.5)

        // Uhh????
        //  Skeleton king spawn egg
        //  Demon lord spawn egg
        $this->addLootTable(new CrateLootTable('Koth', self::KOTH, [
            new CrateLootTableEntry(VanillaBlocks::TRIPWIRE_HOOK()->asItem()
                ->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . 'Common Key')
                ->setCount(mt_rand(5, 15))
                ->setCustomBlockData(CompoundTag::create()
                    ->setTag("KeyTag", new CompoundTag())
                    ->setInt("KeyDataType", CrateManager::COMMON)), 10),
            new CrateLootTableEntry(VanillaBlocks::TRIPWIRE_HOOK()->asItem()
                ->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . 'Rare Key')
                ->setCount(mt_rand(3, 10))
                ->setCustomBlockData(CompoundTag::create()
                    ->setTag("KeyTag", new CompoundTag())
                    ->setInt("KeyDataType", CrateManager::RARE)), 10),
            new CrateLootTableEntry(VanillaBlocks::TRIPWIRE_HOOK()->asItem()
                ->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . 'Mythical Key')
                ->setCount(mt_rand(1, 5))
                ->setCustomBlockData(CompoundTag::create()
                    ->setTag("KeyTag", new CompoundTag())
                    ->setInt("KeyDataType", CrateManager::MYTHIC)), 10),
            new CrateLootTableEntry(CustomItemRegistry::MONEY_POUCH()->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::LIGHT_PURPLE . 'Money Pouch')->setLore([
                TextFormat::RESET . TextFormat::AQUA . 'Minimum amount: ' . TextFormat::WHITE . '$100,000',
                TextFormat::RESET . TextFormat::AQUA . 'Maximum amount: ' . TextFormat::WHITE . '$300,000'
            ])->setCustomBlockData(CompoundTag::create()
                ->setTag("MoneyPouch", new CompoundTag())
                ->setInt("Min", 100000)
                ->setInt("Max", 300000)
            ), 10),

            new CrateLootTableEntry(clone $legendKit, 10),
            new CrateLootTableEntry(clone $emeraldKit, 10),
        ]));

        $commonPower = mt_rand(1, 1500);
        $rarePower = mt_rand($commonPower, 3000);
        $mythicPower = mt_rand($rarePower, 5000);

        $commonLuck = mt_rand(1, 10);
        $rareLuck = mt_rand($commonLuck, 15);
        $mythicLuck = mt_rand($rareLuck, 20);

        // Common loot tables.
        $this->addLootTable(new CrateLootTable('Common', self::COMMON, [
            new CrateLootTableEntry($tags[0], 20),
            new CrateLootTableEntry(VanillaBlocks::BEDROCK()->asItem(), 10),
            new CrateLootTableEntry(VanillaBlocks::OBSIDIAN()->asItem()->setCount(10), 10),
            new CrateLootTableEntry(VanillaItems::ENCHANTED_GOLDEN_APPLE()->setCount(4), 15),
            new CrateLootTableEntry(VanillaBlocks::TRIPWIRE_HOOK()->asItem()->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . 'Rare Key')->setCustomBlockData(CompoundTag::create()->setTag("KeyTag", new CompoundTag())->setInt("KeyDataType", self::RARE)), 10),
            new CrateLootTableEntry(clone $ultraKit, 5),
            new CrateLootTableEntry(CustomItemManager::getPowerShard($commonPower), 15),
            new CrateLootTableEntry(CustomItemManager::getLuckyShard($commonLuck), 10),
            new CrateLootTableEntry(CustomItemManager::getRandomEnchantedBook(Rarity::COMMON), 5),
        ]));

        // Rare loot entries.
        $this->addLootTable(new CrateLootTable('Rare', self::RARE, [
            new CrateLootTableEntry($tags[1], 20),
            new CrateLootTableEntry(VanillaBlocks::BEDROCK()->asItem()->setCount(3), 10),
            new CrateLootTableEntry(VanillaBlocks::OBSIDIAN()->asItem()->setCount(32), 15),
            new CrateLootTableEntry(VanillaItems::ENCHANTED_GOLDEN_APPLE()->setCount(8), 20),
            new CrateLootTableEntry(VanillaBlocks::TRIPWIRE_HOOK()->asItem()->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . 'Mythic Key')->setCustomBlockData(CompoundTag::create()->setTag("KeyTag", new CompoundTag())->setInt("KeyDataType", self::MYTHIC)), 10),
            new CrateLootTableEntry(clone $emeraldKit, 5),
            new CrateLootTableEntry(CustomItemManager::getPowerShard($rarePower), 15),
            new CrateLootTableEntry(CustomItemManager::getLuckyShard($rareLuck), 15),
            new CrateLootTableEntry(CustomItemManager::getRandomEnchantedBook(Rarity::RARE), 10)
        ]));

        // Mythical loot entries.
        $this->addLootTable(new CrateLootTable('Mythical', self::MYTHIC, [
            new CrateLootTableEntry($tags[2], 15),
            new CrateLootTableEntry(VanillaBlocks::BEDROCK()->asItem()->setCount(5), 5),
            new CrateLootTableEntry(VanillaBlocks::OBSIDIAN()->asItem()->setCount(64), 15),
            new CrateLootTableEntry(VanillaItems::ENCHANTED_GOLDEN_APPLE()->setCount(16), 15),
            new CrateLootTableEntry(VanillaBlocks::TRIPWIRE_HOOK()->asItem()->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . 'Mythic Key')->setCustomBlockData(CompoundTag::create()->setTag("KeyTag", new CompoundTag())->setInt("KeyDataType", self::MYTHIC)), 15),
            new CrateLootTableEntry(clone $legendKit, 5),
            new CrateLootTableEntry(CustomItemManager::getPowerShard($mythicPower), 10),
            new CrateLootTableEntry(CustomItemManager::getLuckyShard($mythicLuck), 10),
            new CrateLootTableEntry(CustomItemManager::getRandomEnchantedBook(Rarity::MYTHIC), 10),
        ]));

        if (!Factions::isBadlands()) {
            $this->addCrateAtPosition('Common', Area::addVectorToLocation(new Vector3(6, 2, -21)));
            $this->addCrateAtPosition('Rare', Area::addVectorToLocation(new Vector3(16, 2, -21)));
            $this->addCrateAtPosition('Mythical', Area::addVectorToLocation(new Vector3(11, 2, -22)));
        }
    }

    public function getRandomCrates(Player $player): int
    {
        $chances = mt_rand(1, 100);

        $crate = CrateManager::COMMON; // 60%
        if ($chances > 60) {
            $crate = CrateManager::RARE;
        }

        if ($chances > 90) {
            $crate = CrateManager::MYTHIC; // 10%
        }

        if ($chances > 60) {
            Server::getInstance()->broadcastMessage(MMOPlugin::getPrefix() . sprintf('%s found a %s Crate Key while mining!', NGEssentials::getInstance()->getPlayerManager()->getPlayerName($player), $this->getCrateName($crate)));
        }

        return $crate;
    }
}