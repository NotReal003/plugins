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

namespace factions\item\crate;

use factions\crates\CrateManager;
use factions\Factions;
use libMMO\challenges\reward\CustomReward;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class CrateKeyItem extends CustomReward
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
        Factions::getInstance()->getPlayerData()->increaseKey($player, $this->getRarity(), $this->getAmount());
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
            CrateManager::COMMON => TextFormat::GRAY . 'Common Key',
            CrateManager::RARE => TextFormat::RED . 'Rare Key',
            CrateManager::MYTHIC => TextFormat::DARK_PURPLE . 'Mythic Key',
            default => TextFormat::GRAY . 'Unknown Key',
        };

        if ($amount > 1) {
            $ret .= 's'; // plural, Key*s*
        }
        return $ret;
    }
}