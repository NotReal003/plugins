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

namespace skyblock\economy\shop;

use libMMO\economy\EconomyManager;
use libMMO\economy\shop\ShopItem;
use skyblock\item\CustomItemManager;
use libMMO\item\CustomItemRegistry;
use libMMO\item\enchantment\Enchantment;
use libMMO\player\enchantment\EnchantmentManager;
use libMMO\player\enchantment\EnchantmentUtils;
use pocketmine\block\utils\CoralType;
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

class Shop extends \libMMO\economy\shop\Shop
{

    public function __construct(EconomyManager $economyManager, string $title = 'Shop')
    {
        parent::__construct($economyManager, $title);

        $this->addCategory('Decoration', VanillaBlocks::GLASS()->asItem(), [
            new ShopItem(VanillaBlocks::GLASS()->asItem(), 50),
            new ShopItem(VanillaBlocks::WOOL()->asItem(), 50),

            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::BLACK), 50, previewTitle: "Black Dye"),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::BLUE), 50, previewTitle: "Blue Dye"),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::BROWN), 50, previewTitle: "Brown Dye"),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::CYAN), 50, previewTitle: "Cyan Dye"),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::GRAY), 50, previewTitle: "Gray Dye"),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::GREEN), 50, previewTitle: "Green Dye"),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::LIGHT_BLUE), 50, previewTitle: "Light Blue Dye"),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::LIGHT_GRAY), 50, previewTitle: "Light Gray Dye"),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::LIME), 50, previewTitle: "Lime Dye"),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::MAGENTA), 50, previewTitle: "Magenta Dye"),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::ORANGE), 50, previewTitle: "Orange Dye"),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::PINK), 50, previewTitle: "Pink Dye"),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::PURPLE), 50, previewTitle: "Purple Dye"),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::RED), 50, previewTitle: "Red Dye"),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::WHITE), 50, previewTitle: "White Dye"),
            new ShopItem(VanillaItems::DYE()->setColor(DyeColor::YELLOW), 50, previewTitle: "Yellow Dye"),

            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::BLACK)->asItem(), 50, previewTitle: "Black Stained Clay"),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::BLUE)->asItem(), 50, previewTitle: "Blue Stained Clay"),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::BROWN)->asItem(), 50, previewTitle: "Brown Stained Clay"),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::CYAN)->asItem(), 50, previewTitle: "Cyan Stained Clay"),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::GRAY)->asItem(), 50, previewTitle: "Gray Stained Clay"),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::GREEN)->asItem(), 50, previewTitle: "Green Stained Clay"),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::LIGHT_BLUE)->asItem(), 50, previewTitle: "Light Blue Stained Clay"),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::LIGHT_GRAY)->asItem(), 50, previewTitle: "Light Gray Stained Clay"),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::LIME)->asItem(), 50, previewTitle: "Lime Stained Clay"),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::MAGENTA)->asItem(), 50, previewTitle: "Magenta Stained Clay"),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::ORANGE)->asItem(), 50, previewTitle: "Orange Stained Clay"),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::PINK)->asItem(), 50, previewTitle: "Pink Stained Clay"),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::PURPLE)->asItem(), 50, previewTitle: "Purple Stained Clay"),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::RED)->asItem(), 50, previewTitle: "Red Stained Clay"),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::WHITE)->asItem(), 50, previewTitle: "White Stained Clay"),
            new ShopItem(VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::YELLOW)->asItem(), 50, previewTitle: "Yellow Stained Clay"),

            new ShopItem(VanillaBlocks::SEA_LANTERN()->asItem(), 200),
            new ShopItem(VanillaBlocks::END_ROD()->asItem(), 250),

            new ShopItem(VanillaBlocks::CORAL_BLOCK()->setCoralType(CoralType::BRAIN)->asItem(), 100, previewTitle: 'Brain Coral Block'),
            new ShopItem(VanillaBlocks::CORAL_BLOCK()->setCoralType(CoralType::BUBBLE)->asItem(), 100, previewTitle: 'Bubble Coral Block'),
            new ShopItem(VanillaBlocks::CORAL_BLOCK()->setCoralType(CoralType::FIRE)->asItem(), 100, previewTitle: 'Fire Coral Block'),
            new ShopItem(VanillaBlocks::CORAL_BLOCK()->setCoralType(CoralType::HORN)->asItem(), 100, previewTitle: 'Horn Coral Block'),
            new ShopItem(VanillaBlocks::CORAL_BLOCK()->setCoralType(CoralType::TUBE)->asItem(), 100, previewTitle: 'Tube Coral Block'),

            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::BLACK())->asItem(), 50, previewTitle: "Black Concrete"),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::BLUE())->asItem(), 50, previewTitle: "Blue Concrete"),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::BROWN())->asItem(), 50, previewTitle: "Brown Concrete"),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::CYAN())->asItem(), 50, previewTitle: "Cyan Concrete"),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::GRAY())->asItem(), 50, previewTitle: "Gray Concrete"),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::GREEN())->asItem(), 50, previewTitle: "Green Concrete"),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::LIGHT_BLUE())->asItem(), 50, previewTitle: "Light Blue Concrete"),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::LIGHT_GRAY())->asItem(), 50, previewTitle: "Light Gray Concrete"),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::LIME())->asItem(), 50, previewTitle: "Lime Concrete"),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::MAGENTA())->asItem(), 50, previewTitle: "Magenta Concrete"),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::ORANGE())->asItem(), 50, previewTitle: "Orange Concrete"),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::PINK())->asItem(), 50, previewTitle: "Pink Concrete"),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::PURPLE())->asItem(), 50, previewTitle: "Purple Concrete"),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::RED())->asItem(), 50, previewTitle: "Red Concrete"),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::WHITE())->asItem(), 50, previewTitle: "White Concrete"),
            new ShopItem(VanillaBlocks::CONCRETE()->setColor(DyeColor::YELLOW())->asItem(), 50, previewTitle: "Yellow Concrete"),

            new ShopItem(VanillaBlocks::DIORITE()->asItem(), 50),
        ]);
        $this->addCategory('Blocks', VanillaBlocks::COBBLESTONE()->asItem(), [
            new ShopItem(VanillaBlocks::DIRT()->asItem(), 5, 1),
            new ShopItem(VanillaBlocks::GRASS()->asItem(), 10),
            new ShopItem(VanillaBlocks::COBBLESTONE()->asItem(), 10, 5),
            new ShopItem(VanillaBlocks::STONE()->asItem(), 20, 1),
            new ShopItem(VanillaBlocks::STONE_BRICKS()->asItem(), 30, 20),
            new ShopItem(VanillaBlocks::ANDESITE()->asItem(), 30, 20),
            new ShopItem(VanillaBlocks::GRANITE()->asItem(), 30, 20),
            new ShopItem(VanillaBlocks::DIORITE()->asItem(), 30, 20),

            new ShopItem(VanillaBlocks::ACACIA_LOG()->asItem(), 50, 30),
            new ShopItem(VanillaBlocks::BIRCH_LOG()->asItem(), 50, 30),
            new ShopItem(VanillaBlocks::DARK_OAK_LOG()->asItem(), 50, 30),
            new ShopItem(VanillaBlocks::OAK_LOG()->asItem(), 50, 30),
            new ShopItem(VanillaBlocks::SPRUCE_LOG()->asItem(), 50, 30),
            new ShopItem(VanillaBlocks::JUNGLE_LOG()->asItem(), 50, 30),
            new ShopItem(VanillaBlocks::SPRUCE_LOG()->asItem(), 50, 30),

            new ShopItem(VanillaBlocks::NETHERRACK()->asItem(), 50, 30),
            new ShopItem(VanillaBlocks::QUARTZ()->asItem(), 70, 40),
            new ShopItem(VanillaBlocks::SAND()->asItem(), 80, 1),
            new ShopItem(VanillaBlocks::SOUL_SAND()->asItem(), 100, 60),
            new ShopItem(VanillaBlocks::GRAVEL()->asItem(), 50, 30),
            new ShopItem(VanillaBlocks::GLOWSTONE()->asItem(), 100),
            new ShopItem(VanillaBlocks::PACKED_ICE()->asItem(), 1000),
            new ShopItem(VanillaBlocks::ENDER_CHEST()->asItem(), 5000),
        ]);
        $this->addCategory('Redstone', VanillaItems::REDSTONE_DUST(), [
            new ShopItem(VanillaBlocks::HOPPER()->asItem(), 3000),
        ]);
        $this->addCategory('Food', VanillaItems::STEAK(), [
            new ShopItem(VanillaItems::RAW_CHICKEN(), 10),
            new ShopItem(VanillaItems::RAW_RABBIT(), 15),
            new ShopItem(VanillaItems::RAW_BEEF(), 15 ),
            new ShopItem(VanillaItems::RAW_PORKCHOP(), 20),
            new ShopItem(VanillaItems::COOKED_CHICKEN(), 25, 20),
            new ShopItem(VanillaItems::COOKED_RABBIT(), 30, 25),
            new ShopItem(VanillaItems::STEAK(), 35, 30),
            new ShopItem(VanillaItems::COOKED_PORKCHOP(), 45, 40),
            new ShopItem(VanillaItems::BAKED_POTATO(), 100, 40),
            new ShopItem(VanillaItems::BREAD(), 150, 100),
            new ShopItem(VanillaItems::GOLDEN_APPLE(), 500, 0),
            new ShopItem(VanillaItems::ENCHANTED_GOLDEN_APPLE(), 10000,),
        ]);
        $this->addCategory('Farming', VanillaItems::DIAMOND_HOE(), [
            new ShopItem(VanillaBlocks::OAK_SAPLING()->asItem(), 50, 30),
            new ShopItem(VanillaItems::CARROT(), 30, 15),
            new ShopItem(VanillaItems::WHEAT_SEEDS(), 40),
            new ShopItem(VanillaItems::POTATO(), 80, 20),
            new ShopItem(VanillaBlocks::SUGARCANE()->asItem(), 80, 20),
            new ShopItem(VanillaBlocks::CACTUS()->asItem(), 100, 35),
            new ShopItem(VanillaBlocks::NETHER_WART()->asItem(), 200, 45),
            new ShopItem(VanillaBlocks::PUMPKIN()->asItem(), 45, 35),
            new ShopItem(VanillaItems::MELON(), 20, 5),
            new ShopItem(VanillaItems::MELON_SEEDS(), 60),
            new ShopItem(VanillaItems::PUMPKIN_SEEDS(), 40),
        ]);
        $this->addCategory('Utilities', VanillaItems::WATER_BUCKET(), [
            new ShopItem(StringToItemParser::getInstance()->parse("monster_spawner"), 30000),
            new ShopItem(VanillaItems::WATER_BUCKET(), 500),
            new ShopItem(VanillaItems::LAVA_BUCKET(), 500),
            new ShopItem(VanillaItems::ENDER_PEARL(), 700),
            new ShopItem(VanillaBlocks::LILY_PAD()->asItem(), 50),
            new ShopItem(VanillaItems::BOW(), 500),
            new ShopItem(VanillaItems::ARROW(), 50),
            new ShopItem(CustomItemManager::getFlightOrb(), 50000, previewTitle: "Orb of Flight")
        ]);
        $this->addCategory('Minerals & Mob Drops', VanillaItems::DIAMOND(), [
            new ShopItem(VanillaItems::IRON_INGOT(), 40, 35),
            new ShopItem(VanillaItems::GOLD_INGOT(), 50, 40),
            new ShopItem(VanillaItems::DIAMOND(), 100, 65),

            new ShopItem(VanillaBlocks::IRON()->asItem(), 360, 315),
            new ShopItem(VanillaBlocks::GOLD()->asItem(), 450, 360),
            new ShopItem(VanillaBlocks::DIAMOND()->asItem(), 900, 585),

            new ShopItem(VanillaItems::COAL(), 50, 10),
            new ShopItem(VanillaItems::FEATHER(), 50, 25),
            new ShopItem(VanillaItems::RABBIT_HIDE(), 50, 20),
            new ShopItem(VanillaItems::RABBIT_FOOT(), 50, 30),
            new ShopItem(VanillaItems::LEATHER(), 150, 15),
            new ShopItem(VanillaBlocks::RED_MUSHROOM()->asItem(), 175, 35),
            new ShopItem(VanillaItems::ROTTEN_FLESH(), 250, 40),
            new ShopItem(VanillaItems::STRING(), 200, 50),
            new ShopItem(VanillaItems::SPIDER_EYE(), 200, 50),
            new ShopItem(VanillaItems::BONE(), 300, 60),
            new ShopItem(VanillaItems::ARROW(), 30, 5),
            new ShopItem(VanillaBlocks::POPPY()->asItem(), 350, 75),
        ]);

        $ultraKit = CustomItemManager::getKitItem('Ultra', TextFormat::GOLD);
        $emeraldKit = CustomItemManager::getKitItem('Emerald', TextFormat::GREEN);
        $legendKit = CustomItemManager::getKitItem('Legend', TextFormat::AQUA);

        $this->addCategory("Kits", CustomItemRegistry::KIT(), [
            new ShopItem($ultraKit, 75000, previewTitle: "Ultra Kit"),
            new ShopItem($emeraldKit, 150000, previewTitle: "Emerald Kit"),
            new ShopItem($legendKit, 285000, previewTitle: "Legend Kit"),
        ]);

        $enchIdMap = EnchantmentIdMap::getInstance();
        $ref = new ReflectionProperty(EnchantmentIdMap::class, "idToEnum");
        $enchantments = $ref->getValue($enchIdMap);

        $items = [];
        /** @var Enchantment $enchantment */
        foreach ($enchantments as $enchantment) {
            if (EnchantmentManager::isEnchantExcluded($enchantment)) {
                continue;
            }

            $name = $enchantment->getName();
            if ($name instanceof Translatable) {
                $name = EnchantmentUtils::getVanillaTranslation($name->getText());
            } else {
                $name = EnchantmentUtils::getVanillaTranslation($name);
            }

            $items[] = new ShopItem(CustomItemManager::getEnchantedBook(mt_rand(0, 100), new EnchantmentInstance($enchantment, 1)), 35, previewTitle: TextFormat::YELLOW . $name);
        }

        // Enchantments
        $this->addCategory('Enchantments', CustomItemRegistry::ENCHANTED_BOOK_COMMON(), $items, true);

        // Shard & Scrolls
        $this->addCategory('Shards & Scrolls', CustomItemRegistry::LUCKY_SHARD(), [
            new ShopItem(CustomItemRegistry::LUCKY_SHARD()
                ->setLore([
                    '',
                    TextFormat::RESET . TextFormat::GRAY . 'Drag this shard onto an Enchantment',
                    TextFormat::RESET . TextFormat::GRAY . 'Book to increase its success rate.'
                ]), 20, previewTitle: "Lucky Shard"),
            new ShopItem(CustomItemRegistry::POWER_SHARD()
                ->setLore([
                    '',
                    TextFormat::RESET . TextFormat::GRAY . 'Drag this shard onto an Enchantment',
                    TextFormat::RESET . TextFormat::GRAY . 'Book to increase its power.'
                ]), 20, previewTitle: "Power Shard")
        ], true, function (Item $item): array {
            $items = [];

            $itemCalls = 40;
            $itemCount = $item->getCount();
            while ($itemCount-- > 0 && $itemCalls-- > 0) {
                if ($item->getTypeId() === CustomItemRegistry::POWER_SHARD()->getTypeId()) {
                    $increaseAmount = mt_rand(1000, mt_rand(2500, 3000));

                    $items[] = CustomItemManager::getPowerShard($increaseAmount);
                } else {
                    $increaseAmount = mt_rand(0, 30);

                    $items[] = CustomItemManager::getLuckyShard($increaseAmount);
                }
            }

            return $items;
        });
    }
}
