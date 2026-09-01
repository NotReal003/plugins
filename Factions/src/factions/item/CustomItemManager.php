<?php
/**
 *        ______         _   _
 *       |  ____|       | | (_)
 *  __  _| |__ __ _  ___| |_ _  ___  _ __  ___
 *  \ \/ /  __/ _` |/ __| __| |/ _ \| '_ \/ __|
 *   >  <| | | (_| | (__| |_| | (_) | | | \__ \
 *  /_/\_\_|  \__,_|\___|\__|_|\___/|_| |_|___/
 *
 * Copyright (C) 2016-2023 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder, Studgi
 */

namespace factions\item;

use factions\block\SpawnerTile;
use factions\entities\StackableRegistry;
use factions\item\enum\GeneratorType;
use factions\item\item\CustomPotion;
use factions\item\item\GeneratorBucket;
use factions\item\item\PlayerHead;
use factions\item\item\ThrowableTNT;
use InvalidArgumentException;
use libMMO\player\enchantment\EnchantmentManager;
use libVanilla\entity\registry\ActorList;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use ReflectionProperty;

class CustomItemManager extends \libMMO\item\CustomItemManager
{
    public static function getCustomPotion(int $potionType): CustomPotion
    {
        $potion = CustomItemRegistry::POTION();
        switch ($potionType) {
            case CustomPotion::BUILDER:
                $potion->setCustomName(TextFormat::RESET . TextFormat::RED . TextFormat::BOLD . 'Builder Potion');
                $potion->setLore(['',
                    TextFormat::RESET . TextFormat::GRAY . 'Haste III (3:00)',
                    TextFormat::RESET . TextFormat::GRAY . 'Fire Resistance I (8:00)',
                    TextFormat::RESET . TextFormat::GRAY . 'Night Vision I (8:00)',
                    TextFormat::RESET . TextFormat::GRAY . 'Water Breathing I (8:00)'
                ]);

                (function () {
                    $this->name = 'Builder Potion';
                })->call($potion);
                break;
            case CustomPotion::TRY_HARD:
                $potion->setCustomName(TextFormat::RESET . TextFormat::RED . TextFormat::BOLD . 'Try-Hard Potion');
                $potion->setLore(['',
                    TextFormat::RESET . TextFormat::GRAY . 'Speed II (2:00)',
                    TextFormat::RESET . TextFormat::GRAY . 'Jump Boost I (3:00)',
                    TextFormat::RESET . TextFormat::GRAY . 'Night Vision I (8:00)'
                ]);

                (function () {
                    $this->name = 'Try-Hard Potion';
                })->call($potion);
                break;
            case CustomPotion::RAIDER:
                $potion->setCustomName(TextFormat::RESET . TextFormat::RED . TextFormat::BOLD . 'Raider Potion');
                $potion->setLore(['',
                    TextFormat::RESET . TextFormat::GRAY . 'Jump Boost III (3:00)',
                    TextFormat::RESET . TextFormat::GRAY . 'Speed I (3:00)',
                    TextFormat::RESET . TextFormat::GRAY . 'Fire Resistance I (8:00)'
                ]);

                (function () {
                    $this->name = 'Raider Potion';
                })->call($potion);
                break;
            case CustomPotion::MARAUDER:
                $potion->setCustomName(TextFormat::RESET . TextFormat::RED . TextFormat::BOLD . 'Marauder Potion');
                $potion->setLore(['',
                    TextFormat::RESET . TextFormat::GRAY . 'Jump Boost II (3:00)',
                    TextFormat::RESET . TextFormat::GRAY . 'Speed I (3:00)',
                ]);

                (function () {
                    $this->name = 'Marauder Potion';
                })->call($potion);
                break;
            default:
                throw new InvalidArgumentException();
        }
        $potion->setNamedTag($potion->getNamedTag()->setInt("potionType", $potionType));

        return $potion;
    }


    /**
     * @param ActorList $identifier
     * @return Item
     */
    public static function getSpawnerItem(ActorList $identifier): Item
    {
        $spawner = StringToItemParser::getInstance()->parse("monster_spawner");
        $customName = match ($identifier->getName()) {
            StackableRegistry::ZOMBIE()->getName() => 'Zombie Spawner',
            StackableRegistry::SHEEP()->getName() => 'Sheep Spawner',
            StackableRegistry::COW()->getName() => 'Cow Spawner',
            StackableRegistry::SPIDER()->getName() => 'Spider Spawner',
            StackableRegistry::IRON_GOLEM()->getName() => 'Iron Golem Spawner',
            default => 'Unknown Spawner'
        };

        $spawner->setCustomBlockData(CompoundTag::create()->setString(SpawnerTile::NG_ENTITY_IDENTIFIER, $identifier->getNewId()));
        $spawner->setCustomName(TextFormat::RESET . TextFormat::GOLD . TextFormat::BOLD . $customName);
        $spawner->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Place anywhere to activate spawner.']);

        return $spawner;
    }

    public static function getThrowableTNT(bool $worksUnderwater = false): ThrowableTNT
    {
        $item = $worksUnderwater ? CustomItemRegistry::THROWABLE_TNT_UNDERWATER() : CustomItemRegistry::THROWABLE_TNT();

        if (!$worksUnderwater) {
            $item->setCustomName(TextFormat::RESET . TextFormat::GOLD . TextFormat::BOLD . 'Throwable TNT');
            $item->setLore(['',
                TextFormat::RESET . TextFormat::GRAY . 'Click to launch TNT.'
            ]);
        } else {
            $item->setCustomName(TextFormat::RESET . TextFormat::LIGHT_PURPLE . TextFormat::BOLD . 'Special Throwable TNT');
            $item->setLore(['',
                TextFormat::RESET . TextFormat::GRAY . 'Throwable TNT that breaks blocks',
                TextFormat::RESET . TextFormat::GRAY . 'inside a water with the chance of 40%',
                '',
                TextFormat::RESET . TextFormat::GRAY . 'Click to launch TNT.'
            ]);
        }

        return $item;
    }

    public static function getGeneratorBucket(GeneratorType $type): GeneratorBucket
    {
        $item = match ($type) {
            GeneratorType::COBBLESTONE => CustomItemRegistry::GENERATOR_BUCKET_COBBLESTONE(),
            GeneratorType::OBSIDIAN => CustomItemRegistry::GENERATOR_BUCKET_OBSIDIAN(),
            GeneratorType::BEDROCK => CustomItemRegistry::GENERATOR_BUCKET_BEDROCK(),
        };

        $item->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::AQUA . 'Generator Bucket');
        $item->setLore(['',
            TextFormat::RESET . TextFormat::YELLOW . 'Block: ' . TextFormat::WHITE . $item->getGeneratorBlock()->getName(), '',
            TextFormat::RESET . TextFormat::GRAY . 'Click to generate ' . $item->getGeneratorBlock()->getName() . ' blocks.'
        ]);

        return $item;
    }

    public static function getPlayerHead(Player $player): PlayerHead
    {
        $item = CustomItemRegistry::PLAYER_HEAD();

        $item->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::AQUA . $player->getName() . "'s Head" . TextFormat::RESET . TextFormat::DARK_GRAY . ' (' . TextFormat::GRAY . 'Click anywhere' . TextFormat::DARK_GRAY . ')');
        $item->setLore(['',
            TextFormat::RESET . TextFormat::YELLOW . 'Balance Percentage: ' . TextFormat::WHITE . PlayerHead::DEDUCTION_PERCENTAGE . '%', '',
            TextFormat::RESET . TextFormat::GRAY . 'Click anywhere to claim reward.'
        ]);

        $item->setCustomBlockData(CompoundTag::create()
            ->setString("Name", $player->getName())
            ->setByteArray('Data', $player->getSkin()->getSkinData()));

        $item->setNamedTag($item->getNamedTag()
            ->setString('xuid', $player->getXuid())
            ->setString('player', $player->getName()));

        return $item;
    }

    public static function getEnchantments(): array
    {
        $ref = new ReflectionProperty(EnchantmentIdMap::class, "idToEnum");

        $enchantments = $ref->getValue(EnchantmentIdMap::getInstance());

        return array_filter($enchantments, static function (?Enchantment $enchantment): bool {
            return $enchantment !== null && !EnchantmentManager::isEnchantExcluded($enchantment);
        });
    }
}