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
use factions\player\PlayerData;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class BlockCommand extends BaseCommand
{

    public function __construct(Factions $plugin)
    {
        parent::__construct('block', $plugin);

        $this->setDescription('Toggle blocking requests from other players feature');
    }

    public function executeCommand(Player $sender, string $commandLabel, array $args): bool
    {
        $playerData = $this->getOwningPlugin()->getPlayerData();
        if (isset($args[0])) {
            switch ($args[0]) {
                case 'on':
                    $playerData->setValue($sender, PlayerData::FORM_BLOCKED, true);
                    $this->sendMessage($sender, TextFormat::RED . 'Blocked all form requests. Other players will no longer be able to send you requests.');
                    break;
                case 'off':
                    $playerData->setValue($sender, PlayerData::FORM_BLOCKED, false);
                    $this->sendMessage($sender, 'Allowed all form requests. Other players will now be able to send you requests.');
                    break;
                default:
                    $this->sendFailureMessage($sender, 'Usage: /block <on/off>');
            }
        } else {
            $this->sendFailureMessage($sender, 'Usage: /block <on/off>');
        }

        return true;
    }
}