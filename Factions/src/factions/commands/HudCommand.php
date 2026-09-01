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
use libMMO\player\MMOPlayer;

class HudCommand extends BaseCommand
{
    public function __construct(Factions $plugin)
    {
        parent::__construct("hud", $plugin);

        $this->setDescription("Disable Factions heads-up display");
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        if (!($sender instanceof \factions\player\MMOPlayer)) {
            return true;
        }

        if (isset($args[0])) {
            switch ($args[0]) {
                case 'on':
                    $sender->enableHud(true);

                    $this->sendMessage($sender, 'You turned on your heads-up display.');
                    break;
                case 'off':
                    $sender->enableHud(false);

                    $this->sendFailureMessage($sender, 'You turned off your heads-up display.');
                    break;
                default:
                    $this->sendFailureMessage($sender, 'Usage: /hud <on/off>');
            }
        } else {
            $this->sendFailureMessage($sender, 'Usage: /hud <on/off>');
        }

        return true;
    }
}