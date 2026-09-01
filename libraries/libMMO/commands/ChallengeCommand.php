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
 * @author Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder, Studgi
 */

namespace libMMO\commands;

use libMMO\challenges\forms\ChallengeForms;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;

class ChallengeCommand extends BaseCommand
{
    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct('challenge', $plugin);

        $this->setAliases(['ch', 'challenges']);
        $this->setDescription('Open the challenges UI');
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        ChallengeForms::sendChallengeList($sender, $this->getOwningPlugin());

        return true;
    }
}