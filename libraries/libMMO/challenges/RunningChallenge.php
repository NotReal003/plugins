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

use libMMO\challenges\reward\CustomReward;
use libMMO\event\ChallengeUpdatedEvent;
use libMMO\MMOPlugin;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function is_bool;


/**
 * Class RunningChallenge
 * Each player holds an array of those Objects for each challenge they have activated, tracking their progress
 */
class RunningChallenge
{
    public const PROGRESS = 0;
    public const CLAIMED = 1;
    public const DONE = 2;
    public const START_TIME = 3;
    public const EXPIRY_TIME = 4;

    private bool $done;

    /**
     * @param Challenge $challenge
     * @param int[] $progress Every action of the challenge has their own entry, ordered in the exact same way the challenges in the Challenge class are ordered
     * @param bool $claimed
     * @param int $startTime
     * @param int $expiryTime
     */
    public function __construct(
        private readonly Challenge $challenge,
        private array              $progress = [],
        private bool               $claimed = false,
        private int                $startTime = -1,
        private int                $expiryTime = -1
    )
    {
        if ($this->startTime < 0) {
            $this->startTime = time();
        }
        if ($this->challenge->isDailyChallenge() && $this->expiryTime < 0) {
            $this->expiryTime = $this->startTime + (24 * 60 * 60);
        }
        $this->done = $this->isGoalsCompleted();
    }

    public static function fromArray(Challenge $challenge, array $data): RunningChallenge
    {
        return new self(
            $challenge,
            !is_bool($data[self::PROGRESS]) ? $data[self::PROGRESS] : [],
            $data[self::CLAIMED],
            $data[self::START_TIME] ?? time(), // we set a new time upon read
            $data[self::EXPIRY_TIME] ?? -1
        );
    }

    /**
     * @return int[]
     */
    public function getProgress(): array
    {
        return $this->progress;
    }

    public function isExpired(): bool
    {
        if ($this->expiryTime < 0) {
            return false;
        }

        return $this->expiryTime < time();
    }

    public function isWithinTime(): bool
    {
        return $this->startTime <= time() && !$this->isExpired();
    }

    public function increaseProgress(Player $player, int $type, ?object $object = null, int $increase = 1): void
    {
        if (!$this->isWithinTime()) {
            return;
        }
        foreach ($this->challenge->getChallengeActions() as $key => $action) {
            if ($action->getActionType() === $type) {
                if (!isset($this->progress[$key])) {
                    $this->progress[$key] = 0;
                }

                if ($action->shouldIncreaseProgress($object)) {
                    if ($action->getGoal() <= ($this->progress[$key] + $increase)) {
                        $this->progress[$key] = $action->getGoal();
                    } else {
                        $this->progress[$key] += $increase;
                        $player->sendPopup(MMOPlugin::getPrefix() . TextFormat::GREEN . $this->challenge->getName() . TextFormat::YELLOW . ' Challenge progress update - ' . TextFormat::GRAY . '(' . TextFormat::GOLD . $this->progress[$key] . TextFormat::GRAY . '/' . TextFormat::GOLD . $action->getGoal() . TextFormat::GRAY . ')');
                    }
                }
            }
        }

        if ($this->isGoalsCompleted()) {
            $player->sendMessage(TextFormat::GREEN . 'You completed the ' . TextFormat::GOLD . $this->getChallenge()->getName() . TextFormat::GREEN . ' challenge! Claim your rewards in the Challenges UI.');
            $this->done = true;
        }

        $event = new ChallengeUpdatedEvent($player, $this);
        $event->call();
    }

    public function isGoalsCompleted(): bool
    {
        foreach ($this->challenge->getChallengeActions() as $key => $action) {
            if (!isset($this->progress[$key]) || !$action->reached($this->progress[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return Challenge
     */
    public function getChallenge(): Challenge
    {
        return $this->challenge;
    }

    public function giveRewards(Player $player): void
    {
        foreach ($this->getChallenge()->getReward() as $reward) {
            if ($reward instanceof CustomReward) {
                $reward->give($player);
            } else {
                $player->getInventory()->addItem($reward);
            }
        }
    }

    /**
     * @return int Total count of all goal actions
     */
    public function getAllGoals(): int
    {
        $overallGoalCount = 0;

        foreach ($this->challenge->getChallengeActions() as $action) {
            $overallGoalCount += $action->getGoal();
        }

        return $overallGoalCount;
    }

    public function toArray(): array
    {
        return [
            self::PROGRESS => $this->progress,
            self::CLAIMED => $this->done && $this->claimed,
            self::DONE => $this->done && !$this->claimed,
            self::START_TIME => $this->startTime,
            self::EXPIRY_TIME => $this->expiryTime,
        ];
    }

    public function isDone(): bool
    {
        return $this->done;
    }

    public function isClaimed(): bool
    {
        return $this->claimed;
    }

    public function getExpiryTime(): int
    {
        return $this->expiryTime;
    }

    public function getStartTime(): int
    {
        return $this->startTime;
    }

    public function setClaimed(bool $claimed): void
    {
        $this->claimed = $claimed;
    }

    public function setStartTime(int $startTime): void
    {
        $this->startTime = $startTime;
    }

    public function setExpiryTime(int $expiryTime): void
    {
        $this->expiryTime = $expiryTime;
    }

    public function unsetExpiryTime(): void
    {
        $this->expiryTime = -1;
    }
}