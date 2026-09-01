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

namespace factions\economy\shop;

use factions\entities\StackableRegistry;
use factions\item\CustomItemManager;
use factions\item\enum\GeneratorType;
use factions\item\item\CustomPotion;
use libMMO\economy\EconomyManager;
use libMMO\economy\shop\ShopItem;
use libMMO\item\CustomItemManager as MMOCustomItemManager;
use libMMO\item\CustomItemRegistry;
use libMMO\item\enchantment\Enchantment;
use libMMO\player\enchantment\EnchantmentManager;
use libMMO\player\enchantment\EnchantmentUtils;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\lang\Translatable;
use pocketmine\utils\TextFormat;
use ReflectionProperty;
use RuntimeException;

class Shop extends \libMMO\economy\shop\Shop
{
    public function __construct(EconomyManager $economyManager, string $title = 'Shop')
    {
        parent::__construct($economyManager, $title);


        $ultraKit = MMOCustomItemManager::getKitItem('Ultra', TextFormat::GOLD);
        $emeraldKit = MMOCustomItemManager::getKitItem('Emerald', TextFormat::GREEN);
        $legendKit = MMOCustomItemManager::getKitItem('Legend', TextFormat::AQUA);

        $this->addCategory('Decoration', VanillaBlocks::GLASS()->asItem(), [
            new ShopItem(VanillaBlocks::GLASS()->asItem(), 50),

            new ShopItem(VanillaBlocks::GLOWSTONE()->asItem(), 100),
            new ShopItem(VanillaBlocks::SEA_LANTERN()->asItem(), 100),
            new ShopItem(VanillaBlocks::LANTERN()->asItem(), 150),
            new ShopItem(VanillaBlocks::END_ROD()->asItem(), 100),
            new ShopItem(VanillaBlocks::BOOKSHELF()->asItem(), 100),

            // Family: Dye
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::BLACK), 25),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::BLUE), 25),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::BROWN), 25),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::CYAN), 25),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::GRAY), 25),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::GREEN), 25),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::LIGHT_BLUE), 25),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::LIGHT_GRAY), 25),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::LIME), 25),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::MAGENTA), 25),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::ORANGE), 25),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::PINK), 25),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::PURPLE), 25),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::RED), 25),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::WHITE), 25),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::YELLOW), 25),

            // Family: Terracotta
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::BLACK)->asItem(), 50),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::BLUE)->asItem(), 50),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::BROWN)->asItem(), 50),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::CYAN)->asItem(), 50),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::GRAY)->asItem(), 50),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::GREEN)->asItem(), 50),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::LIGHT_BLUE)->asItem(), 50),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::LIGHT_GRAY)->asItem(), 50),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::LIME)->asItem(), 50),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::MAGENTA)->asItem(), 50),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::ORANGE)->asItem(), 50),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::PINK)->asItem(), 50),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::PURPLE)->asItem(), 50),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::RED)->asItem(), 50),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::WHITE)->asItem(), 50),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::YELLOW)->asItem(), 50),

            // Family: Wool
            new ShopItem(VanillaBlocks::WOOL()->setColor(DyeColor::BLACK)->asItem(), 50),
            new ShopItem(VanillaBlocks::WOOL()->setColor(DyeColor::BLUE)->asItem(), 50),
            new ShopItem(VanillaBlocks::WOOL()->setColor(DyeColor::BROWN)->asItem(), 50),
            new ShopItem(VanillaBlocks::WOOL()->setColor(DyeColor::CYAN)->asItem(), 50),
            new ShopItem(VanillaBlocks::WOOL()->setColor(DyeColor::GRAY)->asItem(), 50),
            new ShopItem(VanillaBlocks::WOOL()->setColor(DyeColor::GREEN)->asItem(), 50),
            new ShopItem(VanillaBlocks::WOOL()->setColor(DyeColor::LIGHT_BLUE)->asItem(), 50),
            new ShopItem(VanillaBlocks::WOOL()->setColor(DyeColor::LIGHT_GRAY)->asItem(), 50),
            new ShopItem(VanillaBlocks::WOOL()->setColor(DyeColor::LIME)->asItem(), 50),
            new ShopItem(VanillaBlocks::WOOL()->setColor(DyeColor::MAGENTA)->asItem(), 50),
            new ShopItem(VanillaBlocks::WOOL()->setColor(DyeColor::ORANGE)->asItem(), 50),
            new ShopItem(VanillaBlocks::WOOL()->setColor(DyeColor::PINK)->asItem(), 50),
            new ShopItem(VanillaBlocks::WOOL()->setColor(DyeColor::PURPLE)->asItem(), 50),
            new ShopItem(VanillaBlocks::WOOL()->setColor(DyeColor::RED)->asItem(), 50),
            new ShopItem(VanillaBlocks::WOOL()->setColor(DyeColor::WHITE)->asItem(), 50),
            new ShopItem(VanillaBlocks::WOOL()->setColor(DyeColor::YELLOW)->asItem(), 50),
        ]);

        $this->addCategory('Blocks', VanillaBlocks::COBBLESTONE()->asItem(), [
            // Family: Logs
            new ShopItem(VanillaBlocks::OAK_LOG()->asItem(), 30, 5),
            new ShopItem(VanillaBlocks::BIRCH_LOG()->asItem(), 30, 5),
            new ShopItem(VanillaBlocks::ACACIA_LOG()->asItem(), 30, 5),
            new ShopItem(VanillaBlocks::DARK_OAK_LOG()->asItem(), 30, 5),
            new ShopItem(VanillaBlocks::JUNGLE_LOG()->asItem(), 30, 5),
            new ShopItem(VanillaBlocks::SPRUCE_LOG()->asItem(), 30, 5),

            new ShopItem(VanillaBlocks::GRAVEL()->asItem(), 30, 5),
            new ShopItem(VanillaBlocks::ICE()->asItem(), 25, 5),
            new ShopItem(VanillaBlocks::CLAY()->asItem(), 100, 5),
            new ShopItem(VanillaBlocks::GRANITE()->asItem(), 25, 5),
            new ShopItem(VanillaBlocks::GRASS()->asItem(), 25, 5),
            new ShopItem(VanillaBlocks::PURPUR()->asItem(), 100),
            new ShopItem(VanillaBlocks::QUARTZ()->asItem(), 100),

            new ShopItem(VanillaBlocks::PRISMARINE()->asItem(), 100),
            new ShopItem(VanillaBlocks::DIRT()->asItem(), 5, 3),
            new ShopItem(VanillaBlocks::STONE()->asItem(), 25, 5),
            new ShopItem(VanillaBlocks::COBBLESTONE()->asItem(), 20, 5),

            new ShopItem(VanillaBlocks::SAND()->asItem(), 20, 5),
            new ShopItem(VanillaBlocks::SANDSTONE()->asItem(), 30, 10),
            new ShopItem(VanillaBlocks::DIORITE()->asItem(), 25, 5),
            new ShopItem(VanillaBlocks::ANDESITE()->asItem(), 25, 5),
            new ShopItem(VanillaBlocks::NETHERRACK()->asItem(), 20, 3),
            new ShopItem(VanillaBlocks::END_STONE()->asItem(), 25, 8),

            new ShopItem(VanillaBlocks::SOUL_SAND()->asItem(), 100, 20),
            new ShopItem(VanillaBlocks::OBSIDIAN()->asItem(), 1000, 100),
            new ShopItem(VanillaBlocks::BEDROCK()->asItem(), 10000, 5000),
            new ShopItem(VanillaBlocks::CHEST()->asItem(), 100),
            new ShopItem(VanillaBlocks::ENDER_CHEST()->asItem(), 1500),
            new ShopItem(VanillaBlocks::CRAFTING_TABLE()->asItem(), 100),

            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::BLACK)->asItem(), 50),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::BLUE)->asItem(), 50),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::BROWN)->asItem(), 50),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::CYAN)->asItem(), 50),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::GRAY)->asItem(), 50),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::GREEN)->asItem(), 50),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::LIGHT_BLUE)->asItem(), 50),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::LIGHT_GRAY)->asItem(), 50),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::LIME)->asItem(), 50),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::MAGENTA)->asItem(), 50),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::ORANGE)->asItem(), 50),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::PINK)->asItem(), 50),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::PURPLE)->asItem(), 50),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::RED)->asItem(), 50),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::WHITE)->asItem(), 50),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::YELLOW)->asItem(), 50),

            new ShopItem(VanillaBlocks::HOPPER()->asItem(), 450, 3),
            new ShopItem(VanillaBlocks::SNOW()->asItem(), 25),
            new ShopItem(VanillaBlocks::ITEM_FRAME()->asItem(), 30),
        ]);

        $this->addCategory('Potions', VanillaItems::GLASS_BOTTLE(), [
            new ShopItem(CustomItemManager::getCustomPotion(CustomPotion::TRY_HARD), 30000),
            new ShopItem(CustomItemManager::getCustomPotion(CustomPotion::BUILDER), 30000),
            new ShopItem(CustomItemManager::getCustomPotion(CustomPotion::RAIDER), 30000),
        ], purchaseCallback: function (Item $item): array {
            if (!($item instanceof CustomPotion)) {
                throw new RuntimeException('A player is attempting to purchase a non existing item');
            }

            $items = [];

            $itemCalls = 36;
            $itemCount = $item->getCount();
            while ($itemCount-- > 0 && $itemCalls-- > 0) {
                $item = (clone $item);
                $item->setCount(1);

                $items[] = $item;
            }

            return $items;
        });

        $this->addCategory('Utilities', VanillaItems::WATER_BUCKET(), [
            new ShopItem(VanillaBlocks::TORCH()->asItem(), 20),

            // Misc?
            new ShopItem(CustomItemManager::getThrowableTNT(), 10000),
            new ShopItem(CustomItemManager::getThrowableTNT(true), 30000),
            new ShopItem(MMOCustomItemManager::getFlightOrb(true), 50000),
            new ShopItem(CustomItemManager::getGeneratorBucket(GeneratorType::COBBLESTONE), 5000),
            new ShopItem(CustomItemManager::getGeneratorBucket(GeneratorType::OBSIDIAN), 30000),
            new ShopItem(CustomItemManager::getGeneratorBucket(GeneratorType::BEDROCK), 100000),
            new ShopItem(VanillaItems::PUFFERFISH(), 100),

            // Family: Signs
            new ShopItem(VanillaBlocks::OAK_SIGN()->asItem(), 50),
            new ShopItem(VanillaBlocks::SPRUCE_SIGN()->asItem(), 50),
            new ShopItem(VanillaBlocks::ACACIA_SIGN()->asItem(), 50),
            new ShopItem(VanillaBlocks::BIRCH_SIGN()->asItem(), 50),
            new ShopItem(VanillaBlocks::DARK_OAK_SIGN()->asItem(), 50),
            new ShopItem(VanillaBlocks::JUNGLE_SIGN()->asItem(), 50),

            new ShopItem(VanillaBlocks::LADDER()->asItem(), 30),
            new ShopItem(VanillaBlocks::VINES()->asItem(), 20),
            new ShopItem(VanillaBlocks::COBWEB()->asItem(), 135),
            new ShopItem(VanillaBlocks::LILY_PAD()->asItem(), 30),
            new ShopItem(StringToItemParser::getInstance()->parse("tnt"), 700),
            new ShopItem(StringToItemParser::getInstance()->parse("underwater_tnt"), 2100),
            new ShopItem(VanillaItems::ENDER_PEARL(), 1000),
            new ShopItem(VanillaItems::FLINT_AND_STEEL(), 200),
            new ShopItem(VanillaItems::ARROW(), 10, 1),
            new ShopItem(VanillaItems::WATER_BUCKET(), 1000),
            new ShopItem(VanillaItems::LAVA_BUCKET(), 1000),
        ]);

        // Food
        $this->addCategory('Food', VanillaItems::STEAK(), [
            // Family: Cooked food
            new ShopItem(VanillaItems::COOKED_CHICKEN(), 15, 5),
            new ShopItem(VanillaItems::COOKED_MUTTON(), 15, 5),
            new ShopItem(VanillaItems::COOKED_PORKCHOP(), 15, 5),
            new ShopItem(VanillaItems::STEAK(), 100, 30),

            new ShopItem(VanillaItems::GOLDEN_APPLE(), 1000, 50),
            new ShopItem(VanillaItems::ENCHANTED_GOLDEN_APPLE(), 10000),
        ]);

        // Farming
        $this->addCategory('Farming', VanillaItems::DIAMOND_HOE(), [
            new ShopItem(VanillaBlocks::NETHER_WART()->asItem(), 500, 100),
            new ShopItem(VanillaBlocks::CACTUS()->asItem(), 200, 100),
            new ShopItem(VanillaBlocks::SUGARCANE()->asItem(), 100, 50),
            new ShopItem(VanillaItems::CARROT(), 90, 45),
            new ShopItem(VanillaItems::POTATO(), 90, 45),
            new ShopItem(VanillaItems::WHEAT(), 50, 25),
            new ShopItem(VanillaItems::BONE_MEAL(), 100),
            new ShopItem(VanillaItems::WHEAT_SEEDS(), 60, 5)
        ]);

        // Mineral and mob drops
        $this->addCategory('Minerals & Mob Drops', VanillaItems::DIAMOND(), [
            new ShopItem(VanillaItems::FLINT(), 25, 5),
            new ShopItem(VanillaItems::LAPIS_LAZULI(), 100, 5),
            new ShopItem(VanillaItems::REDSTONE_DUST(), 50, 15),

            new ShopItem(VanillaItems::COAL(), 100, 50),
            new ShopItem(VanillaItems::IRON_INGOT(), 450, 75),
            new ShopItem(VanillaItems::GOLD_INGOT(), 600, 300),
            new ShopItem(VanillaItems::DIAMOND(), 1000, 500),

            new ShopItem(VanillaItems::RAW_BEEF(), 75, 15),
            new ShopItem(VanillaItems::RAW_MUTTON(), 75, 10),
            new ShopItem(VanillaItems::LEATHER(), 75, 5),
            new ShopItem(VanillaItems::ROTTEN_FLESH(), 200, 45),
            new ShopItem(VanillaItems::STRING(), 75, 15),
            new ShopItem(VanillaItems::SPIDER_EYE(), 180, 40),
            new ShopItem(VanillaBlocks::POPPY()->asItem(), 20, 10),
        ]);

        // Spawners
        $this->addCategory('Spawners', VanillaBlocks::MONSTER_SPAWNER()->asItem(), [
            new ShopItem(CustomItemManager::getSpawnerItem(StackableRegistry::ZOMBIE()), 100000),
            new ShopItem(CustomItemManager::getSpawnerItem(StackableRegistry::SHEEP()), 250000),
            new ShopItem(CustomItemManager::getSpawnerItem(StackableRegistry::COW()), 500000),
            new ShopItem(CustomItemManager::getSpawnerItem(StackableRegistry::SPIDER()), 750000),
            new ShopItem(CustomItemManager::getSpawnerItem(StackableRegistry::IRON_GOLEM()), 1500000),
        ]);

        // Kits item.
        $this->addCategory("Kits", CustomItemRegistry::KIT(), [
            new ShopItem($ultraKit, 300000),
            new ShopItem($emeraldKit, 500000),
            new ShopItem($legendKit, 600000),
        ]);


        $items = [];
        /** @var Enchantment $enchantment */
        foreach (CustomItemManager::getEnchantments() as $enchantment) {
            if (EnchantmentManager::isEnchantExcluded($enchantment)) {
                continue;
            }

            $name = $enchantment->getName();
            if ($name instanceof Translatable) {
                $name = EnchantmentUtils::getVanillaTranslation($name->getText());
            } else {
                $name = EnchantmentUtils::getVanillaTranslation($name);
            }

            $items[] = new ShopItem(MMOCustomItemManager::getEnchantedBook(mt_rand(0, 100), new EnchantmentInstance($enchantment, 1)), 35, previewTitle: TextFormat::YELLOW . $name);
        }

        // Enchantments
        $this->addCategory('Enchantments', CustomItemRegistry::ENCHANTED_BOOK_COMMON(), $items, true);

        // Shard & Scrolls
        $this->addCategory('Shards & Scrolls', VanillaItems::PAPER(), [
            new ShopItem(CustomItemRegistry::LUCKY_SHARD()
                ->setLore([
                    '',
                    TextFormat::RESET . TextFormat::GRAY . 'Drag this shard onto an Enchantment',
                    TextFormat::RESET . TextFormat::GRAY . 'Book to increase its success rate.'
                ]), 30, previewTitle: "Lucky Shard"),
            new ShopItem(CustomItemRegistry::POWER_SHARD()
                ->setLore([
                    '',
                    TextFormat::RESET . TextFormat::GRAY . 'Drag this shard onto an Enchantment',
                    TextFormat::RESET . TextFormat::GRAY . 'Book to increase its power.'
                ]), 30, previewTitle: "Power Shard")
        ], true, function (Item $item): array {
            $items = [];

            $itemCalls = 40;
            $itemCount = $item->getCount();
            while ($itemCount-- > 0 && $itemCalls-- > 0) {
                if ($item->getTypeId() === CustomItemRegistry::POWER_SHARD()->getTypeId()) {
                    $increaseAmount = mt_rand(1000, mt_rand(2500, 3000));

                    $items[] = MMOCustomItemManager::getPowerShard($increaseAmount);
                } else {
                    $increaseAmount = mt_rand(0, mt_rand(70, 100));

                    $items[] = MMOCustomItemManager::getLuckyShard($increaseAmount);
                }
            }

            return $items;
        });
    }
}
