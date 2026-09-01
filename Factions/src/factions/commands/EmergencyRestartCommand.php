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
use factions\task\ServerRestartTask;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use NetherGames\NGEssentials\NGEssentials;
use pocketmine\utils\TextFormat;

class EmergencyRestartCommand extends BaseCommand
{
    /** @var bool */
    private static bool $shutdownStart = false;

    public function __construct(Factions $owningPlugin)
    {
        parent::__construct('emergencyrestart', $owningPlugin);

        $this->setAliases(['er']);
        $this->setPermission("nethergames.staff;nethergames.developer");
        $this->setDescription('Force restart server');
        $this->setUsage(TextFormat::RED . '/er');
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        if (!$this->testPermission($sender)) {
            return true;
        }

        if (self::$shutdownStart) {
            $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Shutdown sequence has already been initiated.");
        } else {
            $scheduler = Factions::getInstance()->getScheduler();
            $scheduler->scheduleRepeatingTask(new ServerRestartTask(NGEssentials::getInstance()), 20);

            self::$shutdownStart = true;

            $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Shutdown sequence initiated.");
            $this->getOwningPlugin()->getServer()->broadcastMessage(MMOPlugin::getPrefix() . TextFormat::YELLOW . "The server will restart in 30 seconds.");
        }

        return true;
    }
}