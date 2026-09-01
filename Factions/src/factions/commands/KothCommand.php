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
use factions\koth\task\CountDownTask;
use factions\player\MMOPlayer;
use libMMO\MMOPlugin;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;

class KothCommand extends BaseCommand
{
    public function __construct(Factions $plugin)
    {
        parent::__construct("koth", $plugin);

        $this->setDescription("Command for King of The Hill.");
    }

    public function executeCommand(Player $sender, string $commandLabel, array $args): bool
    {
        if (!isset($args[0])) {
            $sender->sendMessage("Unknown command, use /koth help for more commands.");
        } elseif (!($sender instanceof MMOPlayer)) {
            $sender->sendMessage("Uh uh, something went wrong, you weren't supposed to know this yet?");
        } else {
            $koth = $this->getOwningPlugin()->getKoth();
            if ($koth === null) {
                $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "This command is disabled in " . TextFormat::GOLD . "Badlands" . TextFormat::RED . "! Return to spawn and run that command again.");
                return true;
            }

            switch (strtolower($args[0])) {
                case "create":
                    if (!$sender->hasPermission('nethergames.developer')) {
                        $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You don't have permission to execute that command.");
                    } else if ($koth->isKothStarting()) {
                        $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "The koth is already started, wait until it finished first.");
                    } else {
                        $this->getPlugin()->getScheduler()->scheduleRepeatingTask(new CountDownTask($koth), 20);
                    }

                    break;
                case "join":
                    if (!$koth->isKothStarting()) {
                        $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'There are no matches running.');
                    } else if ($koth->inMatch($sender)) {
                        $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You're already in a KOTH match!");
                    } else if (!empty($sender->getInventory()->getContents()) or !empty($sender->getArmorInventory()->getContents())) {
                        $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You must clear your inventory before you play.");
                    } else {
                        $koth->addPlayer($sender);

                        $sender->sendMessage(MMOPlugin::getPrefix() . 'You joined the match.');
                    }
                    break;
                case "quit":
                    if (!$koth->inMatch($sender)) {
                        $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You're not in a KOTH game.");
                    } else {
                        $koth->removePlayer($sender);

                        $sender->setCombatTimer(0);
                        $sender->sendMessage(MMOPlugin::getPrefix() . 'You left the match.');
                    }
                    break;
                default:

            }
        }

        return true;
    }

    /**
     * @return Factions
     */
    public function getPlugin(): Plugin
    {
        /** @var Factions $plugin */
        $plugin = parent::getOwningPlugin();

        return $plugin;
    }
}