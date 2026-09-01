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
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use skyblock\crates\CrateManager;
use skyblock\SkyBlock;

class CrateKeyReward extends CustomReward
{
    /** @var int */
    private int $rarity;
    /** @var int */
    private int $amount;

    public function __construct(int $amount, int $rarity)
    {
        $this->rarity = $rarity;
        $this->amount = $amount;
    }


    public function give(Player $player): void
    {
        SkyBlock::getInstance()->getPlayerData()->increaseKey($player, $this->getRarity(), $this->getAmount());
    }

    /**
     * @return int
     */
    public function getRarity(): int
    {
        return $this->rarity;
    }

    /**
     * @param int $rarity
     */
    public function setRarity(int $rarity): void
    {
        $this->rarity = $rarity;
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

    public function getFormat(): string
    {
        return $this->getAmount() . 'x ' . $this->getKeyName($this->getRarity(), $this->getAmount());
    }

    public function getKeyName(int $rarity, int $amount): string
    {
        $ret = match ($rarity) {
            CrateManager::VOTE => TextFormat::GREEN . 'Vote Key',
            CrateManager::COMMON => TextFormat::GRAY . 'Common Key',
            CrateManager::RARE => TextFormat::RED . 'Rare Key',
            CrateManager::MYTHIC => TextFormat::DARK_PURPLE . 'Mythic Key',
            default => 'Unknown Key',
        };

        if ($amount > 1) {
            $ret .= 's'; // plural, Key*s*
        }
        return $ret;
    }
}