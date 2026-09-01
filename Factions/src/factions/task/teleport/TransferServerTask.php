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

namespace factions\task\teleport;

use factions\Factions;
use factions\player\PlayerData;
use libMMO\MMOPlugin;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\servers\DataServer;
use pocketmine\entity\Location;
use pocketmine\player\Player;
use pocketmine\Server;

/**
 * A Server transfer task.
 *
 * @package factions\task
 */
class TransferServerTask extends TeleportTask
{
    /** @var DataServer */
    protected DataServer $dataServer;

    public function __construct(Player $player, DataServer $dataServer)
    {
        $spawn = $player->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation();

        parent::__construct($player, $spawn);

        $this->dataServer = $dataServer;
        $this->sendTeleportInfo = NGEssentials::getInstance()->getServerManager()->getUniqueId() === $this->dataServer->getUniqueId();

        // Reset transfer location just in case.
        Factions::getInstance()->getPlayerData()->unsetValue($this->player, PlayerData::TRANSFER_LOCATION);
    }

    public function teleportToTarget(): void
    {
        parent::teleportToTarget();

        $manager = Factions::getInstance()->getRegionManager();

        $serverManager = NGEssentials::getInstance()->getServerManager();
        if ($serverManager->getUniqueId() === $this->dataServer->getUniqueId()) {
            return;
        }

        if ($this->dataServer->getOnlinePlayers() >= $this->dataServer->getMaxPlayers() && !$serverManager->canJoinFullServers($this->player)) {
            $this->player->sendMessage('§6The server is currently full and you have been placed in a queue.');
            $this->player->sendMessage('§6Buy the §l§aEMERALD§r §6or §l§bLEGEND§r §6rank at §bngmc.co/store §6to skip the queue!');

            $manager->addPlayerToQueue($this->player, $this->dataServer);
        } else {
            $this->player->sendMessage(MMOPlugin::getPrefix() . "Transporting you to " . $this->dataServer->getRegion() . '-' . $this->dataServer->getCluster()->getGameType());

            Factions::getInstance()->getPlayerManager()->transferPlayer($this->player, $this->dataServer->getCluster()->getGameType(), $this->dataServer->getRegion());
        }
    }
}