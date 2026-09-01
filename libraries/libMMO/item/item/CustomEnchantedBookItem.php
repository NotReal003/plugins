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

namespace libMMO\item\item;

use libMMO\item\CustomItemManager;
use libMMO\item\enchantment\Enchantment;
use libMMO\item\item\component\GlintComponent;
use libMMO\item\SingleCustomItem;
use libMMO\MMOPlugin;
use libMMO\utils\Utils;
use NetherGames\NGEssentials\item\CreativeInventoryInfo;
use pocketmine\block\Block;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\ItemUseResult;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class CustomEnchantedBookItem extends SingleCustomItem
{
    public function initComponent(string $texture, ?CreativeInventoryInfo $creativeInfo = null): void
    {
        parent::initComponent($texture, $creativeInfo);

        $this->addComponent(new GlintComponent());
    }

    public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems): ItemUseResult
    {
        $enchantmentInfo = $this->getCustomBlockData();
        if ($enchantmentInfo === null || $enchantmentInfo->getString('Type', '') !== 'Random' || !Utils::hasTag($enchantmentInfo, 'Rarity')) {
            return parent::onInteractBlock($player, $blockReplace, $blockClicked, $face, $clickVector, $returnedItems);
        }

        $this->pop();

        $enchantment = Enchantment::getRandomEnchantmentFromRarity($enchantmentInfo->getInt('Rarity'));
        $book = CustomItemManager::getEnchantedBook(mt_rand(0, 100), new EnchantmentInstance($enchantment, 1));

        foreach ($player->getInventory()->addItem($book) as $overflow) {
            $player->dropItem($overflow);
        }

        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . 'You received a ' . TextFormat::GOLD . (Enchantment::RARITY_NAMES[$enchantmentInfo->getInt('Rarity')] ?? 'Unknown') . ' Book');

        return ItemUseResult::SUCCESS;
    }
}