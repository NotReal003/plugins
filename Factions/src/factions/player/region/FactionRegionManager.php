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

namespace factions\player\region;

use factions\Factions;
use libMMO\MMOPlugin;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\player\PlayerData;
use NetherGames\NGEssentials\ServerManager;
use NetherGames\NGEssentials\servers\DataServer;
use NetherGames\NGEssentials\servers\ServersCluster;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

/**
 * Factions region id to data manager
 */
class FactionRegionManager implements Listener
{
    /** @var string[][][] */
    private array $regionQueue;
    /** @var DataServer[][]|null[][] */
    private array $regionEnabled = [
        ServerManager::GAME_TYPE_FARLANDS => [
            ServerManager::REGION_US => null,
            ServerManager::REGION_EU => null,
            ServerManager::REGION_AP => null,
        ],
        ServerManager::GAME_TYPE_BADLANDS => [
            ServerManager::REGION_US => null,
            ServerManager::REGION_EU => null,
            ServerManager::REGION_AP => null,
        ],
    ];

    public function __construct(Factions $faction)
    {
        $faction->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (): void {
            $this->tickProcessor();
        }), 20);

        $this->regionQueue = [];

        $faction->getServer()->getPluginManager()->registerEvents($this, $faction);
    }

    /**
     * @param PlayerQuitEvent $event
     */
    public function onPlayerQuitEvent(PlayerQuitEvent $event): void
    {
        $this->removePlayerFromQueue($event->getPlayer());
    }

    private function tickProcessor(): void
    {
        $serverManager = NGEssentials::getInstance()->getServerManager();

        // Reset regions enabled.
        $this->regionEnabled = [
            ServerManager::GAME_TYPE_FARLANDS => [
                ServerManager::REGION_US => null,
                ServerManager::REGION_EU => null,
                ServerManager::REGION_AP => null,
            ],
            ServerManager::GAME_TYPE_BADLANDS => [
                ServerManager::REGION_US => null,
                ServerManager::REGION_EU => null,
                ServerManager::REGION_AP => null,
            ],
        ];

        foreach ($serverManager->getClusters() as $cluster) {
            if (!in_array($cluster->getGameType(), [ServerManager::GAME_TYPE_FARLANDS, ServerManager::GAME_TYPE_BADLANDS], true) || !($cluster instanceof ServersCluster)) {
                continue;
            }

            foreach ($cluster->getServers() as $server) {
                $this->regionEnabled[$cluster->getGameType()][$server->getRegion()] = $server;
            }
        }

        $playerData = NGEssentials::getInstance()->getPlayerData();
        foreach ($this->regionQueue as $gameType => $data) {
            foreach ($data as $region => $playersQueue) {
                /** @var string[] $players */
                $players = array_values($playersQueue);

                foreach ($players as $index => $playerName) {
                    $player = Server::getInstance()->getPlayerExact($playerName);

                    if ($player === null || !$player->isConnected()) {
                        $this->removePlayerFromQueue($playerName);
                        continue;
                    }

                    $serverData = $this->regionEnabled[$gameType][$region];

                    if ($serverData === null) {
                        $player->sendPopup(TextFormat::RED . 'The queued server is offline, queuing is temporarily disabled.');
                    } else {
                        if ($serverData->getOnlinePlayers() <= $serverData->getMaxPlayers() && !$playerData->getBool($player, PlayerData::TRANSFER)) {
                            $player->sendMessage(MMOPlugin::getPrefix() . "Transporting you to " . $serverData->getCluster()->getGameType() . '-' . $serverData->getRegion());

                            $serverManager->getPlugin()->getPlayerManager()->forceTransfer($player, $serverData);
                            if ($playerData->getBool($player, PlayerData::TRANSFER)) {
                                $serverData->updatePlayerCount($serverData->getOnlinePlayers() + 1);
                            }
                        } else {
                            $player->sendPopup(TextFormat::GOLD . 'You are ' . TextFormat::AQUA . '#' . ($index + 1) . TextFormat::GOLD . ' in the queue!');
                        }
                    }
                }
            }
        }
    }

    /**
     * Add a player into the queue to the specified region.
     *
     * @param Player $player
     * @param DataServer $dataServer
     */
    public function addPlayerToQueue(Player $player, DataServer $dataServer): void
    {
        $this->removePlayerFromQueue($player);

        $this->regionQueue[$dataServer->getCluster()->getGameType()][$dataServer->getRegion()][] = $player->getName();
    }

    /**
     * @param Player|string $player
     */
    public function removePlayerFromQueue(Player|string $player): void
    {
        if ($player instanceof Player) {
            $player = $player->getName();
        }

        $this->regionQueue = $this->removeElementByValue($this->regionQueue, $player);
    }

    public function getRegions(): array
    {
        return $this->regionEnabled;
    }

    /**
     * @param string $region
     * @return DataServer|null
     */
    public function getFarlandsByRegion(string $region): ?DataServer
    {
        return $this->regionEnabled[ServerManager::GAME_TYPE_FARLANDS][$region];
    }

    /**
     * @param string $region
     * @return DataServer|null
     */
    public function getBadlandsByRegion(string $region): ?DataServer
    {
        return $this->regionEnabled[ServerManager::GAME_TYPE_BADLANDS][$region];
    }

    /**
     * @param array $arr
     * @param mixed $val
     * @return array
     */
    private function removeElementByValue(array $arr, mixed $val): array
    {
        $return = array();
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $return[$k] = $this->removeElementByValue($v, $val); //recursion
                continue;
            }
            if ($v == $val) continue;
            $return[$k] = $v;
        }

        return $return;
    }
}