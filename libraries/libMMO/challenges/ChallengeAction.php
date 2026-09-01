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

namespace libMMO\challenges;

abstract class ChallengeAction
{


    /** @var int */
    protected int $goal;

    public function __construct(int $goal)
    {
        $this->goal = $goal;
    }

    abstract public function toDisplayString(): string;

    abstract public function shouldIncreaseProgress(?object $object): bool;

    abstract public function getActionType(): int;

    public function reached(int $current): bool
    {
        return $current >= $this->getGoal();
    }

    public function getGoal(): int
    {
        return $this->goal;
    }
}