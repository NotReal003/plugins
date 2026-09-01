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

namespace libMMO\item;

use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use libMMO\utils\BaseClass;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;

class ItemManager extends BaseClass
{
    /** @var array */
    protected array $cooldowns = [];

    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct($plugin);
    }

    public function addCooldown(Player $player, Item $item, int $ticks): void
    {
        $this->cooldowns[$player->getName()][$item->getStateId()] = [$ticks, hrtime(true)];
    }

    public function removeCooldown(Player $player, ?Item $item = null): void
    {
        if ($item === null) {
            unset($this->cooldowns[$player->getName()]);
        } else if (isset($this->cooldowns[$player->getName()][$itemId = $item->getStateId()])) {
            unset($this->cooldowns[$player->getName()][$itemId]);

            if (count($this->cooldowns[$player->getName()]) === 0) {
                unset($this->cooldowns[$player->getName()]);
            }
        }
    }

    public function getCooldown(Player $player, Item $item): int
    {
        $highResolution = hrtime(true);
        if (isset($this->cooldowns[$player->getName()][$itemId = ($item->getStateId())])) {
            [$cooldown, $timer] = $this->cooldowns[$player->getName()][$itemId];

            return (int)(($cooldown / 20) - round(($highResolution - $timer) / 1e+9));
        }

        return -1;
    }

    public function hasCooldown(Player $player, Item $item): bool
    {
        return isset($this->cooldowns[$player->getName()][$item->getStateId()]) && $this->getCooldown($player, $item) > 0;
    }

    public function canFly(MMOPlayer $player): bool
    {
        return true;
    }

    public function canUseMiniHelper(Player $player): bool
    {
        return false;
    }

    public function addHelper(Player $player, int $jobType, Location $location, ?CompoundTag $blockData): bool
    {
        return false;
    }
}
