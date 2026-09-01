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
use factions\task\teleport\TeleportTask;
use factions\task\teleport\TransferServerLogicTask;
use factions\utils\Utils;
use libMMO\player\MMOPlayer;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class HomeCommand extends BaseCommand
{

    public function __construct(Factions $owningPlugin)
    {
        parent::__construct('home', $owningPlugin);

        $this->setDescription('Teleport to a home');
    }

    public function executeCommand(Player $sender, string $commandLabel, array $args): bool
    {
        $cluster = Factions::getFactionsCluster(); // By default, this will return Farlands cluster

        $ess = $this->getOwningPlugin()->getEssentials();
        $playerManager = $ess->getPlayerManager();

        $scheduler = $this->getOwningPlugin()->getScheduler();
        $playerData = $this->getOwningPlugin()->getPlayerData();
        $homeLocation = $playerData->getHomeByName($sender, $args[0] ?? "");

        /** @var MMOPlayer $sender */
        if ($homeLocation === null) {
            $homes = $playerData->getHomes($sender);
            if (empty($homes)) {
                $this->sendFailureMessage($sender, 'You do not have any homes saved.');
            } else {
                $this->sendMessage($sender, 'Your homes: ' . Utils::niceArrayString(array_keys($homes)));
            }
        } elseif ($sender->isCombatTimerActive()) {
            $this->sendFailureMessage($sender, "You can't transfer to another server while combat tagged.");
        } else if (isset(TeleportTask::$teleportList[$sender->getName()])) {
            $this->sendFailureMessage($sender, 'Please wait for you to be teleported first.');
        } else if (!$homeLocation->isValidServer()) {
            if (($transportServer = Factions::getInstance()->getRegionManager()->getFarlandsByRegion($homeLocation->getServerRegion())) === null) {
                $sender->sendMessage(Factions::getPrefix() . TextFormat::RED . 'The server you are teleporting into is currently offline.');
            } else {
                $this->getOwningPlugin()->getScheduler()->scheduleRepeatingTask(new TransferServerLogicTask($sender, $homeLocation, $transportServer), 20);
            }
        } else if ($homeLocation->getPosition()->isValid() && $homeLocation->getPosition()->getWorld()->getFolderName() === "wild") {
            $scheduler->scheduleRepeatingTask(new TeleportTask($sender, $homeLocation->getPosition()), 20);
        } else {
            $playerData->removeHome($sender, $homeLocation->getHomeName());
        }

        return true;
    }
}