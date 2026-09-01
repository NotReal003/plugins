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

namespace factions\commands;

use factions\Factions;
use libMMO\MMOPlugin;
use pocketmine\entity\Human;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class ClearEntitiesCommand extends BaseCommand
{

    public function __construct(Factions $faction)
    {
        parent::__construct('clearentities', $faction);

        $this->setPermission('nethergames.trainee');
        $this->setDescription("Clear excess entities");
    }

    public function executeCommand(Player $sender, string $commandLabel, array $args): bool
    {
        if (!$this->testPermission($sender)) {
            return false;
        }

        foreach (Server::getInstance()->getWorldManager()->getWorlds() as $level) {
            foreach ($level->getEntities() as $entity) {
                if (!$entity instanceof Human) {
                    $entity->flagForDespawn();
                }
            }
        }

        Server::getInstance()->broadcastMessage(MMOPlugin::getPrefix() . TextFormat::YELLOW . 'Excess entities have been forcefully cleared.');

        return true;
    }
}