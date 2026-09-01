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

namespace libMMO\event;

use libMMO\challenges\RunningChallenge;
use pocketmine\event\player\PlayerEvent;
use pocketmine\player\Player;

class ChallengeUpdatedEvent extends PlayerEvent
{

    /** @var RunningChallenge|null */
    private ?RunningChallenge $runningChallenge;

    public function __construct(Player $player, ?RunningChallenge $challenge = null)
    {
        $this->runningChallenge = $challenge;
        $this->player = $player;
    }

    /**
     * @return null|RunningChallenge
     */
    public function getRunningChallenge(): ?RunningChallenge
    {
        return $this->runningChallenge;
    }
}