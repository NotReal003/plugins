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

namespace libMMO\commands;

use libMMO\economy\shop\Shop;
use libMMO\EventListener;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use NetherGames\NGEssentials\player\permissions\Permissions as NGPermissions;
use pocketmine\item\VanillaItems;
use pocketmine\utils\TextFormat;
use function number_format;

class SellCommand extends BaseCommand
{

    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct('sell', $plugin);

        $this->setDescription('Sell items in your inventory');
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        switch ($args[0] ?? '') {
            case 'hand':
                $item = $sender->getInventory()->getItemInHand();
                if ($item->isNull()) {
                    $sender->sendMessage(TextFormat::RED . "You're not holding any items!");
                    return false;
                }

                $price = Shop::getSellPrice($item) * $item->getCount();
                if ($price === 0) {
                    $sender->sendMessage(TextFormat::RED . "This item can't be sold.");
                    return false;
                }

                $sender->getInventory()->setItemInHand(VanillaItems::AIR());

                $this->getOwningPlugin()->getEconomyManager()->increasePlayerMoney($sender->getName(), $price, function () use ($sender, $item, $price) {
                    if ($sender->isConnected()) {
                        $sender->sendMessage(TextFormat::GREEN . 'You sold ' . TextFormat::GOLD . $item->getCount() . 'x ' . TextFormat::clean($item->getName()) . TextFormat::GREEN . ' for ' . TextFormat::GOLD . '$' . number_format($price));
                    }
                });
                break;
            case 'chest':
                if (!$this->testPermission($sender, NGPermissions::RANK_LEGEND)) {
                    break;
                }

                if (isset(EventListener::$sellObjects[$sender->getName()])) {
                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Please tap on a chest to sell its contents inside.");
                } else {
                    $sender->sendMessage(MMOPlugin::getPrefix() . "Tap on a chest to sell its contents inside.");

                    EventListener::$sellObjects[$sender->getName()] = true;
                }
                break;
            case 'all':
                $sold = $total = 0;

                $items = [];
                foreach ($sender->getInventory()->getContents() as $slot => $item) {
                    $items[$slot] = $item;
                    $price = Shop::getSellPrice($item) * $item->getCount();
                    if ($price !== 0) {
                        $sold += $item->getCount();
                        $total += $price;
                        $items[$slot] = VanillaItems::AIR();
                    }
                }

                $sender->getInventory()->setContents($items);
                if ($total === 0) {
                    $sender->sendMessage(TextFormat::RED . "You don't have any items that can be sold.");
                    return false;
                }

                $this->getOwningPlugin()->getEconomyManager()->increasePlayerMoney($sender->getName(), $total, function () use ($sender, $sold, $total) {
                    if ($sender->isConnected()) {
                        $sender->sendMessage(TextFormat::GREEN . 'You sold ' . TextFormat::GOLD . number_format($sold) . TextFormat::GREEN . ' items for ' . TextFormat::GOLD . '$' . number_format($total));
                    }
                });
                break;
            default:
                $sender->sendMessage(TextFormat::RED . 'Usage: /sell <hand|chest|all>');
                break;
        }

        return true;
    }
}