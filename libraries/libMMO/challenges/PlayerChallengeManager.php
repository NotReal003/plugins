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
use pocketmine\player\Player;

class PlayerChallengeManager extends BaseClass
{
    public const MAX_PLAYER_CHALLENGES = 1;
    public const MAX_DAILY_CHALLENGES = 1;

    /** @var RunningChallenge[][] */
    private array $runningChallenges = [];

    public function addChallenge(Player $player, RunningChallenge $challenge): bool
    {
        $playerName = $player->getName();
        $challengeId = $challenge->getChallenge()->getId();

        if ($challenge->isDone()) {
            $this->runningChallenges[$playerName][$challengeId] = $challenge;
            return true;
        }

        if (isset($this->runningChallenges[$playerName])) {
            $currentChallengeCount = 0;
            $currentDailyChallengeCount = 0;

            foreach ($this->runningChallenges[$playerName] as $runningChallenge) {
                if (!$runningChallenge->isWithinTime()) {
                    continue;
                }
                if ($runningChallenge->getChallenge()->isDailyChallenge()) {
                    $currentDailyChallengeCount++;

                    continue;
                }
                if ($runningChallenge->isDone()) {
                    continue; // we don't want to add more daily challenges even if it was finished
                }
                $currentChallengeCount++;
            }

            if ($challenge->getChallenge()->isDailyChallenge() && $currentDailyChallengeCount >= self::MAX_DAILY_CHALLENGES) {
                return false;
            } else if ($currentChallengeCount >= self::MAX_PLAYER_CHALLENGES) {
                return false;
            }

            $this->runningChallenges[$playerName][$challengeId] = $challenge;
        } else {
            if (isset($this->runningChallenges[$playerName][$challengeId])) {
                return false;
            }
            $this->runningChallenges[$playerName] = [$challengeId => $challenge];
        }
        return true;
    }

    /**
     * Returns a list of challenges mapped
     * challengeId => (Either RunningChallenge if the Challenge is in Progress or Done, or Challenge if the Challenge hasnt been started yet)
     *
     * @param Player $player
     * @return Challenge[]|RunningChallenge[]
     */
    public function getAllChallenges(Player $player): array
    {
        $challenges = [];

        if (isset($this->runningChallenges[$player->getName()])) {
            foreach ($this->runningChallenges[$player->getName()] as $challengeId => $challenge) {
                $challenges[$challengeId] = $challenge;
            }
        }

        foreach ($this->getPlugin()->getChallengeManager()->getChallenges() as $challengeId => $challenge) {
            if (!isset($challenges[$challengeId])) {
                $challenges[$challengeId] = $challenge;
            }
        }

        return $challenges;
    }

    public function removeChallenge(Player $player, int $challengeId): void
    {
        if (isset($this->runningChallenges[$player->getName()])) {
            unset($this->runningChallenges[$player->getName()][$challengeId]);
        }
    }

    /**
     * @param Player $player
     * @return RunningChallenge[]
     */
    public function getActiveChallenges(Player $player): array
    {
        $challenges = [];

        if (isset($this->runningChallenges[$player->getName()])) {
            foreach ($this->runningChallenges[$player->getName()] as $challenge) {
                if (!$challenge->isDone()) {
                    $challenges[] = $challenge;
                }
            }
        }

        return $challenges;
    }

    /**
     * @param Player $player
     * @return RunningChallenge[]
     */
    public function getAllSelectedChallenges(Player $player): array
    {
        $challenges = [];

        if (isset($this->runningChallenges[$player->getName()])) {
            foreach ($this->runningChallenges[$player->getName()] as $challenge) {
                $challenges[] = $challenge;
            }
        }

        return $challenges;
    }

    public function removePlayer(Player $player): void
    {
        unset($this->runningChallenges[$player->getName()]);
    }

    public function getPlayersChallengesAsArray(Player $player): array
    {
        $result = [];

        if (isset($this->runningChallenges[$player->getName()])) {
            foreach ($this->runningChallenges[$player->getName()] as $challengeId => $challenge) {
                $result[$challengeId] = $challenge->toArray();
            }
        }

        return $result;
    }
}

