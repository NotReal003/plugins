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

use libMMO\item\SingleCustomItem;
use libMMO\kit\KitManager;
use libMMO\MMOPlugin;
use pocketmine\item\ItemUseResult;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class CustomKitItem extends SingleCustomItem
{
    use ReusableInteractTrait;

    public function onUse(Player $player): ItemUseResult
    {
        $title = $this->getNamedTag()->getString('title', '');
        if ($title !== '') {
            $contents = KitManager::getContents($title);

            if ($contents !== null) {
                if ((count($player->getInventory()->getContents()) + count($contents)) > $player->getInventory()->getSize()) {
                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Your inventory is currently full!');
                    return ItemUseResult::FAIL;
                }

                foreach ($contents as $content) {
                    $player->getInventory()->addItem($content);
                }

                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . 'You redeemed the ' . TextFormat::GOLD . $title . TextFormat::GREEN . ' kit!');
                $this->pop();

                return ItemUseResult::SUCCESS;
            }
        }

        return ItemUseResult::FAIL;
    }
}