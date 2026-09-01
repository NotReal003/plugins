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

namespace factions\kit;

use libMMO\item\enchantment\CustomEnchantment;
use libMMO\MMOPlugin;
use libMMO\player\enchantment\EnchantmentManager;
use libMMO\player\enchantment\EnchantmentUtils;
use libMMO\utils\RomanNumbers;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\data\bedrock\EnchantmentIds;
use pocketmine\item\enchantment\AvailableEnchantmentRegistry;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\ItemEnchantmentTagRegistry as TagRegistry;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\utils\TextFormat;
use function array_map;

class KitManager extends \libMMO\kit\KitManager
{
    public final const bool TOGGLE_GOD_SET = true;

    public function __construct(MMOPlugin $instance)
    {
        parent::__construct($instance);

        $enchIdMap = EnchantmentIdMap::getInstance();
        $this->addKit('Superior Starter', 69 * 365 * 24 * 60 * 60, [
            VanillaItems::DIAMOND_HELMET()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 4))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3)),
            VanillaItems::DIAMOND_CHESTPLATE()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 4))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3)),
            VanillaItems::DIAMOND_LEGGINGS()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 4))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3)),
            VanillaItems::DIAMOND_BOOTS()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 4))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3)),
            VanillaItems::DIAMOND_SWORD()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3)),
            VanillaItems::DIAMOND_PICKAXE()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 4))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FORTUNE(), 2)),
            VanillaItems::DIAMOND_AXE()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 4))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FORTUNE(), 2)),
            VanillaItems::DIAMOND_SHOVEL()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 4))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FORTUNE(), 2)),
            VanillaItems::DIAMOND_HOE()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 4))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FORTUNE(), 2)),
            VanillaItems::BOW(),
            VanillaItems::ARROW()->setCount(32),
            VanillaItems::ENCHANTED_GOLDEN_APPLE()->setCount(32),
            VanillaItems::GOLDEN_APPLE()->setCount(64),
            VanillaItems::STEAK()->setCount(64),
            VanillaBlocks::OAK_LOG()->asItem()->setCount(64),
            VanillaBlocks::OBSIDIAN()->asItem()->setCount(64),
            VanillaBlocks::OBSIDIAN()->asItem()->setCount(64),
            VanillaBlocks::BEDROCK()->asItem()->setCount(1),
            VanillaItems::DIAMOND()->setCount(64),
        ]);

        $this->addKit('Starter', 60 * 60, [
            VanillaItems::DIAMOND_HELMET()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 1))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3)),
            VanillaItems::DIAMOND_CHESTPLATE()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 1))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3)),
            VanillaItems::DIAMOND_LEGGINGS()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 1))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3)),
            VanillaItems::DIAMOND_BOOTS()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 1))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3)),
            VanillaItems::DIAMOND_SWORD()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 3))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3)),
            VanillaItems::DIAMOND_PICKAXE()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 2))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3)),
            VanillaItems::DIAMOND_AXE()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 2))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3)),
            VanillaItems::DIAMOND_SHOVEL()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 2))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3)),
            VanillaItems::DIAMOND_HOE()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 2))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3)),
            VanillaItems::BOW(),
            VanillaItems::ARROW()->setCount(32),
            VanillaBlocks::OAK_LOG()->asItem()->setCount(64),
            VanillaItems::ENCHANTED_GOLDEN_APPLE()->setCount(3),
            VanillaItems::GOLDEN_APPLE()->setCount(32),
            VanillaItems::STEAK()->setCount(64),
            VanillaBlocks::OBSIDIAN()->asItem()->setCount(64),
            VanillaBlocks::BEDROCK()->asItem()->setCount(1)
        ]);

        $this->addKit('Ultra', 8 * 60 * 60, [
            VanillaItems::DIAMOND_HELMET()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 3))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 5)),
            VanillaItems::DIAMOND_CHESTPLATE()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 3))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 5)),
            VanillaItems::DIAMOND_LEGGINGS()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 3))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 5)),
            VanillaItems::DIAMOND_BOOTS()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 3))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 5)),
            VanillaItems::DIAMOND_SWORD()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 5))
                ->addEnchantment(new EnchantmentInstance($enchIdMap->fromId(EnchantmentIds::LOOTING), 1)),
            VanillaItems::DIAMOND_PICKAXE()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 4))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FORTUNE(), 1)),
            VanillaItems::DIAMOND_AXE()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 4))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FORTUNE(), 1)),
            VanillaItems::DIAMOND_SHOVEL()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 4))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FORTUNE(), 1)),
            VanillaItems::DIAMOND_HOE()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 4))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FORTUNE(), 1)),
            VanillaItems::BOW(),
            VanillaItems::ARROW()->setCount(32),
            VanillaItems::ENCHANTED_GOLDEN_APPLE()->setCount(32),
            VanillaItems::GOLDEN_APPLE()->setCount(64),
            VanillaItems::STEAK()->setCount(64),
            VanillaBlocks::OAK_LOG()->asItem()->setCount(64),
            VanillaBlocks::OBSIDIAN()->asItem()->setCount(64),
            VanillaBlocks::OBSIDIAN()->asItem()->setCount(64),
            VanillaBlocks::BEDROCK()->asItem()->setCount(4),
            VanillaItems::DIAMOND()->setCount(32),
        ], 'nethergames.vip.ultra', TextFormat::GOLD);

        $this->addKit('Emerald', 10 * 60 * 60, [
            VanillaItems::DIAMOND_HELMET()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 4))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 6)),
            VanillaItems::DIAMOND_CHESTPLATE()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 4))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 6)),
            VanillaItems::DIAMOND_LEGGINGS()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 4))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 6)),
            VanillaItems::DIAMOND_BOOTS()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 4))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 6)),
            VanillaItems::DIAMOND_SWORD()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 6))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 6))
                ->addEnchantment(new EnchantmentInstance($enchIdMap->fromId(EnchantmentIds::LOOTING), 2)),
            VanillaItems::DIAMOND_PICKAXE()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 6))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FORTUNE(), 2)),
            VanillaItems::DIAMOND_AXE()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 6))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FORTUNE(), 2)),
            VanillaItems::DIAMOND_SHOVEL()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 6))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FORTUNE(), 2)),
            VanillaItems::DIAMOND_HOE()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 6))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FORTUNE(), 2)),
            VanillaItems::BOW(),
            VanillaItems::ARROW()->setCount(32),
            VanillaItems::ENCHANTED_GOLDEN_APPLE()->setCount(48),
            VanillaItems::GOLDEN_APPLE()->setCount(64),
            VanillaItems::GOLDEN_APPLE()->setCount(64),
            VanillaItems::STEAK()->setCount(64),
            VanillaBlocks::OAK_LOG()->asItem()->setCount(64),
            VanillaBlocks::OBSIDIAN()->asItem()->setCount(64),
            VanillaBlocks::OBSIDIAN()->asItem()->setCount(64),
            VanillaBlocks::OBSIDIAN()->asItem()->setCount(64),
            VanillaBlocks::BEDROCK()->asItem()->setCount(6),
            VanillaItems::DIAMOND()->setCount(64),
        ], 'nethergames.vip.emerald', TextFormat::GREEN);

        $this->addKit('Legend', 12 * 60 * 60, [
            VanillaItems::DIAMOND_HELMET()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 7)),
            VanillaItems::DIAMOND_CHESTPLATE()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 7)),
            VanillaItems::DIAMOND_LEGGINGS()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 7)),
            VanillaItems::DIAMOND_BOOTS()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 7)),
            VanillaItems::DIAMOND_SWORD()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 8))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 7))
                ->addEnchantment(new EnchantmentInstance($enchIdMap->fromId(EnchantmentIds::LOOTING), 3)),
            VanillaItems::DIAMOND_PICKAXE()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FORTUNE(), 3)),
            VanillaItems::DIAMOND_AXE()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FORTUNE(), 3)),
            VanillaItems::DIAMOND_SHOVEL()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FORTUNE(), 3)),
            VanillaItems::DIAMOND_HOE()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 5))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FORTUNE(), 3)),
            VanillaItems::BOW(),
            VanillaItems::ARROW()->setCount(32),
            VanillaItems::ENCHANTED_GOLDEN_APPLE()->setCount(48),
            VanillaItems::GOLDEN_APPLE()->setCount(64),
            VanillaItems::GOLDEN_APPLE()->setCount(64),
            VanillaItems::STEAK()->setCount(64),
            VanillaBlocks::OAK_LOG()->asItem()->setCount(64),
            VanillaBlocks::OBSIDIAN()->asItem()->setCount(64),
            VanillaBlocks::OBSIDIAN()->asItem()->setCount(64),
            VanillaBlocks::OBSIDIAN()->asItem()->setCount(64),
            VanillaBlocks::OBSIDIAN()->asItem()->setCount(64),
            VanillaBlocks::BEDROCK()->asItem()->setCount(8),
            VanillaItems::DIAMOND()->setCount(64),
            VanillaItems::DIAMOND()->setCount(32),
        ], 'nethergames.vip.legend', TextFormat::AQUA);

        /** @phpstan-ignore-next-line */
        if (self::TOGGLE_GOD_SET) {
            $helmet = VanillaItems::DIAMOND_HELMET()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 6))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 10));
            $chestplate = VanillaItems::DIAMOND_CHESTPLATE()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 6))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 10));
            $legging = VanillaItems::DIAMOND_LEGGINGS()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 6))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 10));
            $boots = VanillaItems::DIAMOND_BOOTS()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 6))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 10));
            $diamondSword = VanillaItems::DIAMOND_SWORD()
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 10))
                ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 10))
                ->addEnchantment(new EnchantmentInstance($enchIdMap->fromId(EnchantmentIds::LOOTING), 3));
            $bow = VanillaItems::BOW();

            /** @var Item[] $items */
            $items = [$helmet, $chestplate, $legging, $boots, $diamondSword, $bow];

            $strMap = StringToEnchantmentParser::getInstance();
            $excludeEnchantments = array_map(static fn(string $name) => $strMap->parse($name), [
                "binding",
                "rabbit",
            ]);

            $availableEnchantmentRegistry = AvailableEnchantmentRegistry::getInstance();
            foreach ($items as $item) {
                $itemTags = $item->getEnchantmentTags();

                foreach ($availableEnchantmentRegistry->getAll() as $enchantment) {
                    if (EnchantmentManager::isEnchantExcluded($enchantment) || in_array($enchantment, $excludeEnchantments, true)) {
                        continue;
                    }

                    if (TagRegistry::getInstance()->isTagArrayIntersection($availableEnchantmentRegistry->getPrimaryItemTags($enchantment), $itemTags)) {
                        $item->addEnchantment(new EnchantmentInstance($enchantment, EnchantmentManager::getMaxEnchantmentLevel($enchantment)));
                    }

                    if (TagRegistry::getInstance()->isTagArrayIntersection($availableEnchantmentRegistry->getSecondaryItemTags($enchantment), $itemTags)) {
                        $item->addEnchantment(new EnchantmentInstance($enchantment, EnchantmentManager::getMaxEnchantmentLevel($enchantment)));
                    }
                }

                $lore = [];
                $lore[] = '';
                foreach ($item->getEnchantments() as $ench) {
                    if ($ench->getType() instanceof CustomEnchantment) {
                        $lore[] = TextFormat::RESET . EnchantmentUtils::getColorCodeForRarity($ench->getType()->getRarity()) . $ench->getType()->getName() . ' ' . RomanNumbers::getRomanNumber($ench->getLevel());
                    }
                }

                $item->setLore($lore);
            }

            $this->addKit('The Free Gift', 5 * 60, [
                ...$items,
                VanillaItems::ARROW()->setCount(32),
                VanillaItems::ENCHANTED_GOLDEN_APPLE()->setCount(48),
                VanillaItems::GOLDEN_APPLE()->setCount(64),
                VanillaItems::GOLDEN_APPLE()->setCount(64)
            ]);
        }
    }
}