<?php
/**
 *        ______         _   _
 *       |  ____|       | | (_)
 *  __  _| |__ __ _  ___| |_ _  ___  _ __  ___
 *  \ \/ /  __/ _` |/ __| __| |/ _ \| '_ \/ __|
 *   >  <| | | (_| | (__| |_| | (_) | | | \__ \
 *  /_/\_\_|  \__,_|\___|\__|_|\___/|_| |_|___/
 *
 * Copyright (C) 2016-2022 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author larryTheCoder
 */

declare(strict_types=1);

namespace skyblock\commands;

use libMMO\entities\OptimizedItemEntity;
use libMMO\item\item\MiniHelperItem;
use libMMO\MMOPlugin;
use libMMO\utils\async\AsyncWorldTicker;
use libVanilla\entity\EntityBase;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use skyblock\SkyBlock;
use skyblock\utils\NonDespawnEntity;

class ClearEntitiesCommand extends BaseCommand
{
    public function __construct(SkyBlock $skyblock)
    {
        parent::__construct('clearentities', $skyblock);

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
                if (($entity instanceof EntityBase && !($entity instanceof NonDespawnEntity)) || $entity instanceof OptimizedItemEntity) {
                    if ($entity instanceof OptimizedItemEntity && $entity->getItem() instanceof MiniHelperItem) {
                        continue;
                    }

                    $entity->flagForDespawn();
                }
            }
        }

        AsyncWorldTicker::getInstance()?->clearEntities();

        Server::getInstance()->broadcastMessage(MMOPlugin::getPrefix() . TextFormat::YELLOW . 'Excess entities have been forcefully cleared.');

        return true;
    }
}