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
 * @author Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder, Studgi
 */
declare(strict_types=1);

namespace libMMO\commands;

use Generator;
use libMMO\economy\auctionHouse\AuctionHouse;
use libMMO\economy\EconomyManager;
use libMMO\item\CustomItemRegistry;
use libMMO\item\ItemStorage;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use pocketmine\block\BlockTypeIds;
use pocketmine\item\ItemTypeIds;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;
use function count;
use function in_array;
use function number_format;

class AuctionHouseCommand extends BaseCommand
{

    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct('auctionhouse', $plugin);

        $this->setAliases(['ah']);
        $this->setDescription('Open the auction house');
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        if (count($args) > 0) {
            if ($args[0] === 'sell') {
                if (count($args) === 1) {
                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Usage: /ah sell <price>');
                    return false;
                }

                if ($sender->isCombatTimerActive()) {
                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'You cannot sell items while in combat!');
                    return false;
                }

                $item = $sender->getInventory()->getItemInHand();
                if ($item->isNull()) {
                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You're not holding any items!");
                    return false;
                }

                static $blacklist = [];
                if (empty($blacklist)) {
                    $blacklist = [
                        ItemTypeIds::BOOK,
                        ItemTypeIds::WRITABLE_BOOK,
                        ItemTypeIds::WRITTEN_BOOK,
                        CustomItemRegistry::MONEY_POUCH()->getTypeId()];
                }

                if (in_array($item->getTypeId(), $blacklist)) {
                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't put this item on the auction house.");
                    return false;
                }

                $price = (int)$args[1];
                if ($price < 1) {
                    $sender->sendMessage(TextFormat::RED . 'You cannot sell items for $0.');
                    return false;
                }
                if ($price > EconomyManager::MAX_MONEY_AMOUNT) {
                    $sender->sendMessage(TextFormat::RED . "You can't sell items for over $" . number_format(EconomyManager::MAX_MONEY_AMOUNT) . '.');
                    return false;
                }

                $sender->getInventory()->removeItem($item);

                Await::f2c(function () use ($sender, $item, $price): Generator {
                    $itemClone = clone $item;
                    if (ItemStorage::hasValidationId($itemClone)) {
                        ItemStorage::isValid($itemClone, yield);

                        $result = yield Await::ONCE;
                        if ($result === ItemStorage::ITEM_INVALID) {
                            $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "The item you are selling is unverifiable, it has been removed.");
                            return;
                        }
                    }

                    $auctionHouse = $this->getOwningPlugin()->getAuctionHouse();
                    $auctionHouse->getAuctionsFromPlayer($sender->getName(), true, yield);

                    $auctions = yield Await::ONCE;

                    if ($sender->isConnected()) {
                        $max = $auctionHouse->getMaximumNumberOfAuctions($sender);
                        if (count($auctions) >= $max) {
                            $sender->sendMessage(TextFormat::RED . "You can't run more than " . $max . ' auctions at the same time. Purchase or upgrade your rank at ' . TextFormat::AQUA . 'ngmc.co/store' . TextFormat::RED . ' to unlock this feature!');
                        } elseif ($auctionHouse->sellItem($sender, $item, $price, AuctionHouse::AUCTION_LENGTH)) {
                            $sender->sendMessage(TextFormat::GREEN . 'You put ' . TextFormat::GOLD . $item->getCount() . 'x ' . TextFormat::clean($item->getName()) . TextFormat::GREEN . ' on your auction house for ' . TextFormat::GOLD . '$' . number_format($price));

                            if (in_array(ItemTypeIds::toBlockTypeId($item->getTypeId()), [BlockTypeIds::MONSTER_SPAWNER, BlockTypeIds::MOB_HEAD], true) && $item->getCount() >= 5) {
                                $auctionHouse->getPlugin()->getLoggerStream()->add('**SUSPICIOUS ACTIVITY** - ' . $sender->getName() . ' put ' . $item->getCount() . 'x ' . TextFormat::clean($item->getName()) . ' onto their auction house for ' . $price . ' coins');
                            }
                            return;
                        } else {
                            $sender->sendMessage(TextFormat::RED . 'Unable to sell item to the auction house');
                        }

                        $sender->getInventory()->addItem($item);
                    }
                });
                return false;
            }
            $sender->sendMessage(TextFormat::RED . 'Usage: /ah sell');
            return false;
        }

        $this->getOwningPlugin()->getAuctionHouse()->sendAuctionMenu($sender);
        return true;
    }
}