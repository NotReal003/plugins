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
 * Copyright (C) 2016-2021 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder
 */

namespace skyblock\item;

use libMMO\player\MMOPlayer;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use skyblock\entities\helpers\HelperManager;
use skyblock\entities\helpers\MiniHelper;
use skyblock\SkyBlock;

class ItemManager extends \libMMO\item\ItemManager
{
    public function canFly(MMOPlayer $player): bool
    {
        return !SkyBlock::getInstance()->isAgora();
    }

    public function canUseMiniHelper(Player $player): bool
    {
        return ($island = SkyBlock::getInstance()->getIslandManager()->getIslandByWorld($player->getWorld())) === null || $island->getOwner() !== $player->getName();
    }

    public function addHelper(Player $player, int $jobType, Location $location, ?CompoundTag $blockData): bool
    {
        $island = SkyBlock::getInstance()->getIslandManager()->getIslandByWorld($player->getWorld());
        if ($island === null) {
            return false;
        }

        if ($island->addHelper($player, $jobType)) {
            $entity = HelperManager::getEntityFromJobType($jobType, $location, $blockData, $island, $player);

            if ($entity instanceof MiniHelper) {
                $entity->spawnToAll();
            }

            return true;
        }

        return false;
    }
}