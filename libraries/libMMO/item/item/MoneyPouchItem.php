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

use libMMO\item\ItemStorage;
use libMMO\item\SingleCustomItem;
use libMMO\MMOPlugin;
use libMMO\utils\Utils;
use pocketmine\item\Item;
use pocketmine\item\ItemUseResult;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class MoneyPouchItem extends SingleCustomItem
{
    use ReusableInteractTrait;

    public const TAG_VALUE = 'Value';

    public function onUse(Player $player): ItemUseResult
    {
        if (Utils::hasTag($this->getNamedTag(), self::TAG_VALUE) && ItemStorage::hasValidationId($this)) {
            ItemStorage::isValidAndRemove($this, static function (int $code, ?Item $item) use ($player): void {
                switch ($code) {
                    case ItemStorage::ITEM_VALIDATED:
                        $balance = $item->getNamedTag()->getInt(MoneyPouchItem::TAG_VALUE);
                        if ($balance <= 0) {
                            return;
                        }

                        $plugin = MMOPlugin::getInstance();
                        $plugin->getEconomyManager()->increasePlayerMoney($player->getName(), $balance, static function () use ($player, $balance) {
                            if ($player->isConnected()) {
                                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::GOLD . 'You redeemed ' . TextFormat::AQUA . '$' . number_format($balance) . TextFormat::GOLD . ' from the money pouch!');
                            }
                        }, ignoreLock: true);

                        if ($balance > 25000000) {
                            $plugin->getLoggerStream()->add('**SUSPICIOUS ACTIVITY** - ' . $player->getName() . ' redeemed ' . $balance . ' coins from a money pouch');
                        }

                        break;
                    case ItemStorage::ITEM_INVALID:
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Your money pouch is invalid, this incident has been reported.");
                        break;
                    case ItemStorage::ITEM_INVALID_ID:
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Your money pouch is not a valid pouch.");
                        break;
                    case ItemStorage::EXECUTION_FAILED:
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "There was an error when trying to redeem your money pouch.");
                        $player->getInventory()->addItem($item);
                        break;
                }
            });

            return ItemUseResult::SUCCESS;
        }

        return ItemUseResult::NONE;
    }
}