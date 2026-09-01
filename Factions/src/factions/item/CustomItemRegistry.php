<?php
/**
 *        ______         _   _
 *       |  ____|       | | (_)
 *  __  _| |__ __ _  ___| |_ _  ___  _ __  ___
 *  \ \/ /  __/ _` |/ __| __| |/ _ \| '_ \/ __|
 *   >  <| | | (_| | (__| |_| | (_) | | | \__ \
 *  /_/\_\_|  \__,_|\___|\__|_|\___/|_| |_|___/
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

namespace factions\item;

use Closure;
use factions\item\enum\GeneratorType;
use factions\item\item\CustomPotion;
use factions\item\item\GeneratorBucket;
use factions\item\item\PlayerHead;
use factions\item\item\ThrowableTNT;
use NetherGames\NGEssentials\item\SimpleCustomItem;
use NetherGames\NGEssentials\utils\Utils;
use pocketmine\data\bedrock\item\ItemDeserializer;
use pocketmine\data\bedrock\item\ItemSerializer;
use pocketmine\data\bedrock\item\ItemTypeNames;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\inventory\CreativeInventory;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\StringToItemParser;
use pocketmine\utils\CloningRegistryTrait;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use ReflectionClass;

/**
 * @method static CustomPotion POTION()
 * @method static ThrowableTNT THROWABLE_TNT()
 * @method static ThrowableTNT THROWABLE_TNT_UNDERWATER()
 * @method static GeneratorBucket GENERATOR_BUCKET_COBBLESTONE()
 * @method static GeneratorBucket GENERATOR_BUCKET_OBSIDIAN()
 * @method static GeneratorBucket GENERATOR_BUCKET_BEDROCK()
 * @method static PlayerHead PLAYER_HEAD()
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
         * @phpstan-template T of Item
         *
         * @phpstan-param $className class-string<SimpleCustomItem>
         * @phpstan-param $fn Closure(T): T
         */
        $closure = function (string $name, string $identifier, string $className = SimpleCustomItem::class, ?Closure $fn = null, array $bcDeserializers = []): void {
            self::register(ItemTypeIds::newId(), $name, $identifier, function (int $id, string $name) use ($identifier, $className, $fn): Item {
                $item = new $className(new ItemIdentifier($id), $name);
                $item->initComponent($identifier);
                if ($fn !== null) {
                    $item = $fn($item);
                }

                return $item;
            }, [], $bcDeserializers);
        };

        /**
         * @phpstan-param $className class-string<Item>
         */
        $vanilla = function (string $name, int $itemId, string $identifier, array $names = [], string $className = Item::class): void {
            self::register($itemId, $name, $identifier, function (int $id, string $name) use ($className): Item {
                return new $className(new ItemIdentifier($id), $name);
            }, $names);
        };

        // Custom item redesign:
        // - Cobblestone Generator Bucket   (nethergames:generator_cobblestone)
        // - Obsidian Generator Bucket      (nethergames:generator_obsidian)
        // - Bedrock Generator Bucket       (nethergames:generator_bedrock)

        // - Throwable TNT                  (nethergames:tnt_throwable)
        // - Underwater Throwable TNT       (nethergames:tnt_throwable_underwater)

        // - Player head                    (nethergames:player_head)

        $vanilla('Potion', ItemTypeIds::POTION, ItemTypeNames::POTION, ["potion", "water_potion"], CustomPotion::class);
        $closure('ThrowableTnt', CustomItemTypeNames::TNT_THROWABLE, ThrowableTNT::class, static fn(ThrowableTNT $tnt) => $tnt, ["nethergames:tnt_throwable"]);
        $closure('ThrowableTntUnderwater', CustomItemTypeNames::TNT_THROWABLE_UNDERWATER, ThrowableTNT::class, static fn(ThrowableTNT $tnt) => $tnt->setWorksUnderwater(true), ["nethergames:tnt_throwable_underwater"]);
        $closure('GeneratorBucketCobblestone', CustomItemTypeNames::GENERATOR_COBBLESTONE, GeneratorBucket::class, static fn(GeneratorBucket $bucket) => $bucket->setType(GeneratorType::COBBLESTONE)->setCustomName("Cobblestone Generator Bucket"), ["nethergames:generator_cobblestone"]);
        $closure('GeneratorBucketObsidian', CustomItemTypeNames::GENERATOR_OBSIDIAN, GeneratorBucket::class, static fn(GeneratorBucket $bucket) => $bucket->setType(GeneratorType::OBSIDIAN)->setCustomName("Obsidian Generator Bucket"), ["nethergames:generator_obsidian"]);
        $closure('GeneratorBucketBedrock', CustomItemTypeNames::GENERATOR_BEDROCK, GeneratorBucket::class, static fn(GeneratorBucket $bucket) => $bucket->setType(GeneratorType::BEDROCK)->setCustomName("Bedrock Generator Bucket"), ["nethergames:generator_bedrock"]);
        $closure('PlayerHead', CustomItemTypeNames::PLAYER_HEAD, PlayerHead::class, null, ["nethergames:player_head"]);
    }

    /**
     * @phpstan-param Closure(int, string): Item $factory
     */
    protected static function register(int $itemId, string $name, string $identifier, Closure $factory, array $itemNames = [], array $bcDeserializers = []): void
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

        $deserializer->map($identifier, fn() => clone $item);
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