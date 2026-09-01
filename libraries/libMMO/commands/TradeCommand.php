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

use libforms\elements\Button;
use libforms\FormManager;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use libMMO\player\PlayerData;
use libMMO\player\trading\TradingManager;
use libMMO\utils\trade\TradeManager;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class TradeCommand extends BaseCommand
{

    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct("trade", $plugin);

        $this->setDescription("Perform a trade to another player.");
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        if (!isset($args[0])) {
            $this->owningPlugin->getTradeManager()->sendTradingForm($sender);
            return true;
        }

        $playerData = $this->getOwningPlugin()->getPlayerData();
        switch (strtolower($args[0])) {
            case "help":
                $sender->sendMessage("§aTrade commands: ");
                $sender->sendMessage("- §2/$commandLabel <player> <price> §l§5»§r§f Starts a trade to a player.");
                $sender->sendMessage("- §2/$commandLabel accept §l§5»§r§f Accept a trade from a player.");
                $sender->sendMessage("- §2/$commandLabel reject §l§5»§r§f Reject a trade from a player.");
                $sender->sendMessage("- §2/$commandLabel storage §l§5»§r§f Open trades storage list.");
                break;
            case "storage":
                $type = strtolower($args[1] ?? "");
                if ($type === "claim") {
                    TradeManager::getInstance()->claimTradeItems($sender, true);
                } else if ($type === "view") {
                    TradeManager::getInstance()->claimTradeItems($sender, false);
                } else {
                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Usage: /trade storage <view/claim>");
                }

                break;
            case "a":
            case "accept":
                if (!TradeManager::getInstance()->acceptTradeRequest($sender)) {
                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You don't have any pending trades");
                }
                break;
            case "c":
            case "cancel":
            case "reject":
                if (!TradeManager::getInstance()->rejectTradeRequest($sender)) {
                    $sender->sendMessage(TextFormat::RED . "You don't have any pending trades");
                }
                break;
            default:
                $tradePrice = 1000;
                $targetPlayer = implode(" ", $args);

                if (is_numeric(end($args))) {
                    $targetPlayer = implode(' ', array_slice($args, 0, -1));
                    $tradePrice = (int)end($args);
                }

                $totalAirContents = array_filter($sender->getInventory()->getContents(true), static function (Item $item): bool {
                    return $item->isNull();
                });

                if (($target = $this->getOwningPlugin()->getEssentials()->getPlayerManager()->getBestMatchingPlayer($targetPlayer)) instanceof Player) {
                    if ($tradePrice < 1000) {
                        $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't start a trade below $1,000!");
                    } elseif ($playerData->getInt($target, PlayerData::PLAYER_MONEY) < $tradePrice) {
                        $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "That player doesn't have $" . $tradePrice . " to start the trade.");
                    } elseif (count($totalAirContents) < 12) {
                        $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You must clear some contents in your inventory first.");
                    } elseif ($this->getOwningPlugin()->getPlayerManager()->canDoTransactions($sender)) {
                        $this->owningPlugin->getTradeManager()->sendTradeConfirmation($sender, $target, $tradePrice);
                    } else {
                        $sender->sendMessage(TextFormat::RED . "Unable to perform a trade to the requested player, please try again later.");
                    }
                } else {
                    $sender->sendMessage(TextFormat::RED . "That player is either offline or doesn't exists.");
                }
                break;
        }
        return true;
    }
}