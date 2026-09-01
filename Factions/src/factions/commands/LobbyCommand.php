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
use factions\forms\Forms;
use factions\task\teleport\TeleportTask;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use NetherGames\NGEssentials\NGEssentials;
use pocketmine\utils\TextFormat;

class LobbyCommand extends BaseCommand
{
    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct('lobby', $plugin);

        $this->setAliases(['spawn', 'l']);
        $this->setDescription('Command used for returning to the lobby');
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        $scheduler = $this->getOwningPlugin()->getScheduler();

        if (isset(TeleportTask::$teleportList[$sender->getName()])) {
            $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Please hold, you already are trying to teleport somewhere else.');
        } elseif (!Factions::isBadlands()) {
            $scheduler->scheduleRepeatingTask(new TeleportTask($sender, NGEssentials::getInstance()->getServerManager()->getSpawn()), 20);
        } else {
            Forms::sendWildernessSelector($sender);
        }

        return true;
    }
}