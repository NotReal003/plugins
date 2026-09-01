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

use factions\Factions;
use factions\player\MMOPlayer;
use libMMO\MMOPlugin;
use libproxy\ProxyNetworkInterface;
use NetherGames\NGEssentials\commands\PingCommand;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;

class CombatBroadcastTask extends Task
{
    /** @var Factions */
    private Factions $plugin;

    public function __construct(Factions $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onRun(): void
    {
        $plugin = $this->getPlugin();
        $server = $plugin->getServer();
        $playerManager = $plugin->getEssentials()->getPlayerManager();

        foreach ($server->getOnlinePlayers() as $player) {
            if (!($player instanceof MMOPlayer)) {
                continue;
            }

            if ($player->isCombatTimerActive() && ($damager = $player->getAttackedEntity()) && $damager instanceof MMOPlayer) {
                [$upstreamDamagerLatency, $downstreamDamagerLatency] = $damager->getLatencyData();
                [$upstreamPlayerLatency, $downstreamPlayerLatency] = $player->getLatencyData();

                $latency1 = PingCommand::parseColoredPing($upstreamDamagerLatency + $downstreamDamagerLatency);
                $latency2 = PingCommand::parseColoredPing($upstreamPlayerLatency + $downstreamPlayerLatency);

                $player->sendPopup(TextFormat::RED . "Attacker: " . TextFormat::GOLD . $playerManager->getPlayerName($damager) . TextFormat::WHITE . " - " . $latency1 . TextFormat::WHITE . TextFormat::EOL . TextFormat::GREEN . 'You ' . TextFormat::WHITE . ' - ' . $latency2);
            }
        }
    }

    /**
     * @return Factions
     */
    public function getPlugin(): Factions
    {
        return $this->plugin;
    }
}