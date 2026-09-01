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

namespace factions\task;

use libMMO\MMOPlugin;
use pocketmine\entity\Human;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class PurgeEntitiesTask extends Task
{
    private int $currentTick = 0;

    public function onRun(): void
    {
        if ($this->currentTick === 600) {
            Server::getInstance()->broadcastMessage(MMOPlugin::getPrefix() . TextFormat::GRAY . 'Excess entities will be cleared in 5 minutes.');
        } else if ($this->currentTick === 840) {
            Server::getInstance()->broadcastMessage(MMOPlugin::getPrefix() . TextFormat::GRAY . 'Excess entities will be cleared in ' . TextFormat::RED . '60' . TextFormat::GRAY . ' seconds.');
        } else if ($this->currentTick === 895) {
            Server::getInstance()->broadcastMessage(MMOPlugin::getPrefix() . TextFormat::GRAY . 'Excess entities will be cleared in ' . TextFormat::RED . '5' . TextFormat::GRAY . ' seconds.');
        } else if ($this->currentTick === 900) {
            $this->currentTick = -1;
            foreach (Server::getInstance()->getWorldManager()->getWorlds() as $level) {
                foreach ($level->getEntities() as $entity) {
                    if (!($entity instanceof Human)) {
                        $entity->flagForDespawn();
                    }
                }
            }

            Server::getInstance()->broadcastMessage(MMOPlugin::getPrefix() . TextFormat::YELLOW . 'Excess entities have been cleared.');
        }

        $this->currentTick++;
    }
}