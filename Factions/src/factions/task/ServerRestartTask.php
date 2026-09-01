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

use factions\player\MMOPlayer;
use libMMO\event\TradeCancelEvent;
use libMMO\utils\trade\TradeManager;
use NetherGames\NGEssentials\events\NGRestartEvent;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\player\PlayerData;
use NetherGames\NGEssentials\tasks\BaseTask;

/**
 * Server restart task, but even more aggressive and it should never cause the server to freeze
 * after a server restart, all the players will be gone by this point and no events should be fired after this task.
 *
 * @package factions\task
 */
class ServerRestartTask extends BaseTask
{
    /** @var int */
    private int $time;

    public function __construct(NGEssentials $plugin)
    {
        $this->time = 15;

        TradeManager::$tradesEnabled = false;

        $ev = new TradeCancelEvent();
        $ev->call();

        parent::__construct($plugin);
    }

    public function onRun(): void
    {
        switch ($this->time) {
            case 5:
                $ev = new NGRestartEvent();
                $ev->call();
                break;
            case 1:
                $playerManager = $this->getPlugin()->getPlayerManager();
                $playerData = $this->getPlugin()->getPlayerData();

                foreach ($this->getPlugin()->getServer()->getOnlinePlayers() as $player) {
                    if ($player instanceof MMOPlayer) {
                        $player->setCombatTimer(0);
                    }

                    if (!$playerData->getBool($player, PlayerData::TRANSFER)) {
                        $playerManager->forceTransfer($player);
                    }
                }
                break;
            case -6:
                $this->getPlugin()->getServer()->shutdown();
                break;
        }

        $this->time--;
        $this->getPlugin()->getServer()->broadcastPopup('§o§l§eN§6G§r§7: §bRestarting in ' . $this->time . '...');
        $this->getPlugin()->getLogger()->debug('Server closing in ' . $this->time . ' second' . ($this->time > 1 ? 's' : ''));
    }
}