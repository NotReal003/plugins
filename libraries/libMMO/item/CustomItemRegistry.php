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

declare(strict_types=1);

namespace libMMO\item;

use Closure;
use libMMO\item\item\CustomEnchantedBookItem;
use libMMO\item\item\CustomKitItem;
use libMMO\item\item\FlyingOrbItem;
use libMMO\item\item\MiniHelperItem;
use libMMO\item\item\MoneyPouchItem;
use NetherGames\NGEssentials\item\SimpleCustomItem;
use NetherGames\NGEssentials\utils\Utils;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\data\bedrock\item\ItemDeserializer;
use pocketmine\data\bedrock\item\ItemSerializer;
use pocketmine\data\bedrock\item\ItemTypeNames;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\data\bedrock\item\SavedItemData as Data;
use pocketmine\inventory\CreativeInventory;
use pocketmine\item\enchantment\Rarity;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\StringToItemParser;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\CloningRegistryTrait;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use ReflectionClass;

/**
 * @method static FlyingOrbItem ORB_OF_FLIGHT()
 * @method static SimpleCustomItem LUCKY_SHARD()
 * @method static SimpleCustomItem POWER_SHARD()
 * @method static CustomKitItem KIT()
 * @method static SingleCustomItem ENCHANTED_BOOK_COMMON()
 * @method static SingleCustomItem ENCHANTED_BOOK_UNCOMMON()
 * @method static SingleCustomItem ENCHANTED_BOOK_RARE()
 * @method static SingleCustomItem ENCHANTED_BOOK_MYTHICAL()
 * @method static MoneyPouchItem MONEY_POUCH()
 * @method static MiniHelperItem HELPER_MINER()
 * @method static MiniHelperItem HELPER_HARVESTER()
 * @method static MiniHelperItem HELPER_LUMBERJACK()
 * @method static SimpleCustomItem TRADE_SEPARATOR()
 * @method static SimpleCustomItem TRADE_BUTTON_ACCEPT()
 * @method static SimpleCustomItem TRADE_BUTTON_DENY()
 * @method static SimpleCustomItem TRADE_ICON_INFORMATION()
 */
final class CustomItemRegistry
{
    use CloningRegistryTrait;

    public static function forceInitialization(): void
    {
        self::checkInit();
    }

    protected static function setup(): void
    {
        /**
         * @phpstan-param $className class-string<SimpleCustomItem>
         */
        $closure = function (string $name, string $identifier, string $className = SimpleCustomItem::class, array $bcDeserializers = [], ?Closure $itemModifier = null): void {
            self::register(ItemTypeIds::newId(), $name, $identifier, function (int $id, string $name) use ($identifier, $className): Item {
                $item = new $className(new ItemIdentifier($id), $name);
                $item->initComponent($identifier);

                return $item;
            }, [], $bcDeserializers, $itemModifier);
        };

        /**
         * @phpstan-param $className class-string<Item>
         */
        $vanilla = function (string $name, int $itemId, string $identifier, array $names = [], string $className = Item::class): void {
            self::register($itemId, $name, $identifier, function (int $id, string $name) use ($className): Item {
                return new $className(new ItemIdentifier($id), $name);
            }, $names);
        };

        $vanilla('OrbOfFlight', ItemTypeIds::MAGMA_CREAM, ItemTypeNames::MAGMA_CREAM, ["magma_cream"], FlyingOrbItem::class);
        $closure('LuckyShard', CustomItemTypeNames::SHARD_LUCKY, SimpleCustomItem::class, ["nethergames:lucky_shard"]);
        $closure('PowerShard', CustomItemTypeNames::SHARD_POWER, SimpleCustomItem::class, ["nethergames:power_shard"]);
        $closure('Kit', CustomItemTypeNames::KIT, CustomKitItem::class, ["nethergames:kit_item"]);
        $closure('EnchantedBookCommon', CustomItemTypeNames::ENCHANTED_BOOK_COMMON, CustomEnchantedBookItem::class, ["nethergames:common_enchanted_book"]);
        $closure('EnchantedBookUncommon', CustomItemTypeNames::ENCHANTED_BOOK_UNCOMMON, CustomEnchantedBookItem::class, ["nethergames:uncommon_enchanted_book"]);
        $closure('EnchantedBookRare', CustomItemTypeNames::ENCHANTED_BOOK_RARE, CustomEnchantedBookItem::class, ["nethergames:rare_enchanted_book"]);
        $closure('EnchantedBookMythical', CustomItemTypeNames::ENCHANTED_BOOK_MYTHICAL, CustomEnchantedBookItem::class, ["nethergames:mythical_enchanted_book"], function (Data $data): Item {
            $tag = $data->getTag();
            $blockTag = $tag?->getCompoundTag(Item::TAG_BLOCK_ENTITY_TAG);

            if ($blockTag?->getTag("Type") === null) {
                throw new \RuntimeException("Mythical Enchanted Book must have a Type tag");
            } else if ($blockTag->getString("Type") === "Random") {
                if ($blockTag->getTag("Rarity") === null) {
                    throw new \RuntimeException("Mythical Enchanted Book must have a Rarity tag");
                } else if (($rarity = $blockTag->getInt("Rarity")) === Rarity::MYTHIC) {
                    return self::ENCHANTED_BOOK_MYTHICAL();
                } else if ($rarity === Rarity::COMMON) {
                    return self::ENCHANTED_BOOK_COMMON();
                } else {
                    throw new \RuntimeException("Invalid rarity for Mythical Enchanted Book");
                }
            } else if (($rarity = self::getRarity($tag)) === null) {
                throw new \RuntimeException("Mythical Enchanted Book must have a Rarity tag");
            } else if ($rarity === Rarity::MYTHIC) {
                return self::ENCHANTED_BOOK_MYTHICAL();
            } else if ($rarity === Rarity::COMMON) {
                return self::ENCHANTED_BOOK_COMMON();
            } else {
                throw new \RuntimeException("Invalid rarity for Mythical Enchanted Book");
            }
        });
        $closure('MoneyPouch', CustomItemTypeNames::MONEY_POUCH, MoneyPouchItem::class, ["nethergames:money_pouch"], function (Data $data): Item {
            if ($data->getTag()?->getCompoundTag(Item::TAG_BLOCK_ENTITY_TAG)?->getTag("Type") === null) {
                return self::MONEY_POUCH();
            } else {
                return self::ENCHANTED_BOOK_MYTHICAL();
            }
        });
        $closure('TradeSeparator', CustomItemTypeNames::TRADE_SEPARATOR);
        $closure('TradeButtonAccept', CustomItemTypeNames::TRADE_BUTTON_ACCEPT);
        $closure('TradeButtonDeny', CustomItemTypeNames::TRADE_BUTTON_DENY);
        $closure('TradeIconInformation', CustomItemTypeNames::TRADE_ICON_INFORMATION);

        $closure("HelperMiner", CustomItemTypeNames::HELPER_MINER, MiniHelperItem::class, ["nethergames:miner_helper"]);
        $closure("HelperHarvester", CustomItemTypeNames::HELPER_HARVESTER, MiniHelperItem::class, ["nethergames:harvester_helper"]);
        $closure("HelperLumberjack", CustomItemTypeNames::HELPER_LUMBERJACK, MiniHelperItem::class, ["nethergames:lumberjack_helper"]);
    }

    private static function getRarity(CompoundTag $tag): ?int
    {
        $enchantments = $tag->getListTag(Item::TAG_ENCH);
        if ($enchantments !== null && $enchantments->getTagType() === NBT::TAG_Compound) {
            /** @var CompoundTag $enchantment */
            foreach ($enchantments as $enchantment) {
                $magicNumber = $enchantment->getShort("id", -1);
                $level = $enchantment->getShort("lvl", 0);
                if ($level <= 0) {
                    continue;
                }
                $type = EnchantmentIdMap::getInstance()->fromId($magicNumber);
                if ($type !== null) {
                    return $type->getRarity();
                }
            }
        }

        return null;
    }

    /**
     * @phpstan-param Closure(int, string): Item $factory
     * @phpstan-param Closure(Data $data): Item $itemModifier
     */
    protected static function register(int $itemId, string $name, string $identifier, Closure $factory, array $itemNames = [], array $bcDeserializers = [], ?Closure $itemModifier = null): void
    {
        // this entire thing is a hack
        $deserializer = GlobalItemDataHandlers::getDeserializer();
        $serializer = GlobalItemDataHandlers::getSerializer();

        $item = $factory($itemId, $name);

        $deserializerRefClass = new ReflectionClass(ItemDeserializer::class);
        $deserializerRefProp = $deserializerRefClass->getProperty("deserializers");

        $serializerRefClass = new ReflectionClass(ItemSerializer::class);
        $serializerItemRefProp = $serializerRefClass->getProperty("itemSerializers");

        $deserializerMap = $deserializerRefProp->getValue($deserializer);
        unset($deserializerMap[$identifier]);
        foreach ($bcDeserializers as $bcDeserializer) {
            unset($deserializerMap[$bcDeserializer]);
        }
        $deserializerRefProp->setValue($deserializer, $deserializerMap);

        $itemSerializerMap = $serializerItemRefProp->getValue($serializer);
        unset($itemSerializerMap[$item->getTypeId()]);
        $serializerItemRefProp->setValue($serializer, $itemSerializerMap);

        if ($itemModifier === null) {
            $deserializer->map($identifier, fn() => clone $item);
        } else {
            $deserializer->map($identifier, $itemModifier);
        }

        foreach ($bcDeserializers as $bcDeserializer) {
            $deserializer->map($bcDeserializer, fn() => clone $item);
        }
        $serializer->map($item, fn() => new SavedItemData($identifier));

        if ($itemId >= ItemTypeIds::FIRST_UNUSED_ITEM_ID) {
            StringToItemParser::getInstance()->override($identifier, fn() => clone $item);

            CreativeInventory::getInstance()->add($item);
        } else {
            foreach ($itemNames as $itemName) {
                StringToItemParser::getInstance()->override($itemName, fn() => clone $item);
            }
        }

        self::_registryRegister(Utils::pascalCaseToSnakeCase($name), $item);
    }
}