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
use factions\task\teleport\TransferHubTask;
use libMMO\MMOPlugin;
use NetherGames\NGEssentials\player\permissions\Permissions;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class HubCommand extends Command
{
    /** @var Factions */
    public Factions $plugin;

    public function __construct(Factions $plugin)
    {
        parent::__construct("hub");

        $this->plugin = $plugin;

        $this->setPermission(Permissions::DEFAULT_COMMAND_PERMISSION);
        $this->setDescription('Command used for transferring to Lobby server');
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if ($sender instanceof Player) {
            $scheduler = $this->getOwningPlugin()->getScheduler();
            if (isset(TeleportTask::$teleportList[$sender->getName()])) {
                $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Please wait for you to be teleported first.');
            } else {
                $scheduler->scheduleRepeatingTask(new TransferHubTask($sender), 20);
            }
        } else {
            $sender->sendMessage($this->getOwningPlugin()->getPrefix() . '§cThat command can only be run in-game.');
        }

        return true;
    }

    public function getOwningPlugin(): Factions
    {
        return $this->plugin;
    }
}