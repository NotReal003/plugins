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

use libMMO\utils\BaseClass;

class ChallengeManager extends BaseClass
{
    /** @var Challenge[] */
    private array $challenges = [];

    public function addChallenge(Challenge $challenge): void
    {
        $this->challenges[$challenge->getId()] = $challenge;
    }

    /**
     * @return Challenge[]
     */
    public function getChallenges(): array
    {
        return $this->challenges;
    }

    /**
     * @return Challenge[]
     */
    public function getDailyChallenges(): array
    {
        return array_filter($this->challenges, fn(Challenge $challenge) => $challenge->isDailyChallenge());
    }

    public function getChallenge(int $id): ?Challenge
    {
        return $this->challenges[$id] ?? null;
    }
}