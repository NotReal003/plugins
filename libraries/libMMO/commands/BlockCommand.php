<?php
/**
 *   _ _ _     __  __ __  __  ____
 *  | (_) |   |  \/  |  \/  |/ __ \
 *  | |_| |__ | \  / | \  / | |  | |
 *  | | | '_ \| |\/| | |\/| | |  | |
 *  | | | |_) | |  | | |  | | |__| |
 *  |_|_|_.__/|_|  |_|_|  |_|\____/
 *
 * Copyright (C) 2016-2024 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder
 */

namespace libMMO\commands;

use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use libMMO\player\PlayerData;
use pocketmine\utils\TextFormat;

class BlockCommand extends BaseCommand
{

    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct('block', $plugin);

        $this->setDescription('Toggle blocking requests from other players feature');
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        $playerData = $this->getOwningPlugin()->getPlayerData();
        if (isset($args[0])) {
            switch ($args[0]) {
                case 'on':
                    $playerData->setValue($sender, PlayerData::FORM_BLOCKED, true);
                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Blocked all form requests. Other players will no longer be able to send you requests.');
                    break;
                case 'off':
                    $playerData->setValue($sender, PlayerData::FORM_BLOCKED, false);
                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . 'Allowed all form requests. Other players will now be able to send you requests.');
                    break;
                default:
                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Usage: /block <on/off>');
            }
        } else {
            $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Usage: /block <on/off>');
        }

        return true;
    }
}