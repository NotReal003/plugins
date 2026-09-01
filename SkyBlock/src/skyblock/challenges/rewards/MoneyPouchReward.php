<?php
/**
 *         _____ _          _     _            _
 *        / ____| |        | |   | |          | |
 *  __  _| (___ | | ___   _| |__ | | ___   ___| | __
 *  \ \/ /\___ \| |/ / | | | '_ \| |/ _ \ / __| |/ /
 *   >  < ____) |   <| |_| | |_) | | (_) | (__|   <
 *  /_/\_\_____/|_|\_\\__, |_.__/|_|\___/ \___|_|\_\
 *                     __/ |
 *                    |___/
 *
 * Copyright (C) 2016-2022 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew
 *
 */

namespace skyblock\challenges\rewards;

use libMMO\challenges\reward\CustomReward;
use skyblock\item\CustomItemManager;
use libMMO\item\ItemStorage;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function number_format;

class MoneyPouchReward extends CustomReward
{
    /** @var int */
    private int $amount;

    public function __construct(int $amount)
    {
        $this->amount = $amount;
    }

    public function getFormat(): string
    {
        return '$' . number_format($this->getAmount()) . ' Money Pouch';
    }

    /**
     * @return int
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * @param int $amount
     */
    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }

    public function give(Player $player): void
    {
        $reward = CustomItemManager::getMoneyPouch($this->getAmount());

        ItemStorage::createValidationId($reward, $player->getName(), static function (Item $pouch) use ($player) {
            if (!$player->isConnected()) {
                return; // We can't do anything if the player left the server.
            }

            if ($player->getInventory()->canAddItem($pouch)) {
                $player->getInventory()->addItem($pouch);
            } else {
                $player->getWorld()->dropItem($player->getPosition(), $pouch);
                $player->sendMessage(TextFormat::RED . 'Your inventory is currently full! The pouch was dropped at your position.');
            }
        });
    }
}