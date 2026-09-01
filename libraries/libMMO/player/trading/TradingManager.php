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

namespace libMMO\player\trading;

use libforms\elements\Button;
use libforms\elements\Dropdown;
use libforms\elements\Input;
use libforms\FormManager;
use libMMO\MMOPlugin;
use libMMO\player\PlayerData;
use libMMO\utils\trade\TradeManager;
use libMMO\utils\Utils;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class TradingManager
{
    /** @var MMOPlugin */
    private MMOPlugin $plugin;

    public function __construct(MMOPlugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function sendTradingForm(Player $player, string $message = ""): void
    {
        if (($form = FormManager::createSimpleForm($player)) === null || !$player->isConnected()) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Something went wrong while trying to create a form, try again later");
            return;
        }

        $form->setTitle("Trading Menu");
        if (!empty($message)) {
            $form->setContent($message . TextFormat::EOL . TextFormat::WHITE . "Choose a selection:");
        } else {
            $form->setContent("Choose a selection:");
        }

        $form->addButton(new Button("Trade a player", function (Player $player) {
            $this->sendTradeToPlayerForm($player);
        }));

        $tradeInvites = TradeManager::getInstance()->getTradeInvites($player);
        if (empty($tradeInvites)) {
            $form->addButton(new Button("Pending invites" . TextFormat::EOL . TextFormat::RED . "No pending invites" , function (Player $player) {
                $this->sendPendingInvitesForm($player);
            }));
        } else {
            $form->addButton(new Button("Pending invites" . TextFormat::EOL . TextFormat::GREEN . count($tradeInvites) . " pending invite(s)" , function (Player $player) {
                $this->sendPendingInvitesForm($player);
            }));
        }

        TradeManager::getInstance()->viewTradeCache($player,
            /**
             * @var Item[] $items
             */
            function (array $items) use ($form): void {
                $totalItem = 0;
                foreach ($items as $item) {
                    $totalItem += $item->getCount();
                }

                if (empty($items)) {
                    $form->addButton(new Button("Trade Stash" . TextFormat::EOL . TextFormat::RED . "No items to claim", function (Player $player): void {
                        $this->sendTradingForm($player, TextFormat::RED . "You do not have any items in your trade stash.");
                    }));
                } else {
                    $form->addButton(new Button("Trade Stash" . TextFormat::EOL . TextFormat::GREEN . $totalItem . " item(s) available", function (Player $player) use ($items): void {
                        $this->sendTradeStashModal($player, $items);
                    }));
                }
                $form->sendForm();
            }
        );
    }

    /**
     * @param Player $player
     * @param Item[] $items
     * @return void
     */
    private function sendTradeStashModal(Player $player, array $items): void
    {
        if (($form = FormManager::createModalForm($player)) === null || !$player->isConnected()) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Something went wrong while trying to create a form, try again later");
            return;
        }

        $contentDescription = "";
        foreach ($items as $item) {
            $contentDescription .= TextFormat::GOLD . $item->getCount() . "x " . TextFormat::WHITE . $item->getName() . TextFormat::EOL;
        }

        $form->setTitle("Trade Stash");
        $form->setContent("The items from your trade will be stored here, you can view the " .
            "item that you have received from this menu. Items received:" . TextFormat::EOL . $contentDescription);
        $form->setButton1(new Button("Claim Stash", function (Player $player): void {
            TradeManager::getInstance()->claimTradeItems($player, true);
        }));

        $form->setButton2(new Button("View Stash", function (Player $player): void {
            TradeManager::getInstance()->claimTradeItems($player);
        }));

        $form->setCloseClosure(function (Player $player): void {
            $this->sendTradingForm($player);
        });

        $form->sendForm();
    }

    private function sendPendingInvitesForm(Player $player): void
    {
        $tradeInvites = TradeManager::getInstance()->getTradeInvites($player);
        if ($tradeInvites === null) {
            return;
        }

        if (empty($tradeInvites)) {
            $this->sendTradingForm($player, TextFormat::RED . "You currently do not have any active trade invites.");
            return;
        }

        if (($form = FormManager::createSimpleForm($player)) === null || !$player->isConnected()) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Something went wrong while trying to create a form, try again later");
            return;
        }

        $form->setTitle("Pending Invites");
        $form->setContent("These are the list of players requesting for a trade with you:");
        foreach ($tradeInvites as [$trader, $recipient, $price, $time]) {
            $elapsed = Utils::timeElapsedString($time);
            $form->addButton(new Button(TextFormat::GOLD . "{$trader->getName()} | " . TextFormat::GRAY . "$" . number_format($price) . TextFormat::EOL . TextFormat::RED . $elapsed,
                    function (Player $player) use ($trader) {
                        TradeManager::getInstance()->acceptTradeRequest($player, $trader);
                    }
                )
            );
        }
        $form->addButton(new Button('Cancel', function (Player $player): void {
            $this->sendTradeToPlayerForm($player);
        }));

        $form->setCloseClosure(function (Player $player): void {
            $this->sendTradeToPlayerForm($player);
        });

        $form->sendForm();
    }

    public function sendTradeToPlayerForm(Player $player): void
    {
        if (($form = FormManager::createCustomForm($player)) === null || !$player->isConnected()) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Something went wrong while trying to create a form, try again later");
            return;
        }

        $onlinePlayers = array_filter($this->plugin->getServer()->getOnlinePlayers(), fn(Player $target) => $target->getName() !== $player->getName());
        $players = array_map(function (Player $target) use ($player) {
            if ($target->getName() === $player->getName()) {
                return false;
            }

            return $target->getName();
        }, $onlinePlayers);
        $players = array_values($players);

        if (empty($players)) {
            $player->sendMessage(MMOPlugin::getPrefix() . "There are no players to trade other than yourself.");
            return;
        }

        $form->setTitle("Trade a Player");
        $form->addElement(new Dropdown("Player", $players));
        $form->addElement(new Input("Trade Price", "The value of the item to trade.", "1000"));
        $form->setCallable(function (Player $player, ?array $responses = null) use ($players) {
			if ($responses === null) {
				return;
			}

            $playerName = $players[$responses[0]] ?? "";

            $target = $this->plugin->getServer()->getPlayerExact($playerName);
            if ($target === null || !$target->isConnected()) {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "That player is currently offline.");
                return;
            }

            $tradePrice = (int)($responses[1] ?? "0");
            $totalAirContents = array_filter($player->getInventory()->getContents(true), static function (Item $item): bool {
                return $item->isNull();
            });

            $playerData = $this->plugin->getPlayerData();
            if ($tradePrice < 1000) {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't start a trade below $1,000!");
            } elseif ($playerData->getInt($target, PlayerData::PLAYER_MONEY) < $tradePrice) {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "That player doesn't have $" . $tradePrice . " to start the trade.");
            } elseif (count($totalAirContents) < 12) {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You must clear some contents in your inventory first.");
            } elseif ($this->plugin->getPlayerManager()->canDoTransactions($player)) {
                $this->plugin->getTradeManager()->sendTradeConfirmation($player, $target, $tradePrice);
            } else {
                $player->sendMessage(TextFormat::RED . "Unable to perform a trade to the requested player, please try again later.");
            }
        });
        $form->setCloseClosure(function (Player $player): void {
            $this->sendTradingForm($player);
        });

        $form->sendForm();
    }

    public function sendTradeConfirmation(Player $sender, Player $target, int $tradePrice): void
    {
        if (($form = FormManager::createModalForm($sender)) === null || !$sender->isConnected()) {
            $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Something went wrong while trying to create a form, try again later");
            return;
        }

        $form->setTitle(MMOPlugin::getPrefix() . TextFormat::BLACK . 'Trade confirmation');
        $form->setContent(TextFormat::RED . 'Are you sure to start a trade with ' . $target->getName() . ' for $' . TextFormat::YELLOW . number_format(($tradePrice)) . TextFormat::RED . '?');
        $form->setButton1(new Button('Confirm', function () use ($sender, $target, $tradePrice) {
            if (!$target->isConnected()) {
                $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'That player is currently offline.');
                return;
            }

            TradeManager::getInstance()->addTradeQueue($sender, $target, $tradePrice);
        }));
        $form->setButton2(new Button('Cancel', function () use ($sender) {
            $this->sendPendingInvitesForm($sender);
        }));
        $form->sendForm();
    }
}