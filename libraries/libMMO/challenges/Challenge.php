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
use libMMO\utils\RomanNumbers;
use pocketmine\item\Item;
use pocketmine\lang\Translatable;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class Challenge
{
    /** @var int */
    private int $id;
    /** @var string */
    private string $name;
    /** @var string */
    private string $description;
    /** @var array<CustomReward|Item> */
    private array $reward;
    /** @var bool */
    private bool $dailyChallenge;
    /** @var ChallengeAction[] */
    private array $challengeActions;

    public function __construct(int $id, string $name, string $description, array $reward, array $challengeActions, bool $isDailyChallenge = false)
    {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->reward = $reward;
        $this->challengeActions = $challengeActions;
        $this->dailyChallenge = $isDailyChallenge;
    }


    public function addAction(ChallengeAction $action): void
    {
        $this->challengeActions[] = $action;
    }

    /**
     * @return ChallengeAction[]
     */
    public function getChallengeActions(): array
    {
        return $this->challengeActions;
    }


    /**
     * @param int $type
     * @return ChallengeAction[]
     */
    public function getActionsOfType(int $type): array
    {
        return $this->challengeActions[$type] ?? [];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function isDailyChallenge(): bool
    {
        return $this->dailyChallenge;
    }

    public function getRewardsFormatted(): string
    {
        $lines = [];

        foreach ($this->getReward() as $rewardItem) {
            if ($rewardItem instanceof CustomReward) {
                $lines[] = $rewardItem->getFormat();
            } else {
                $enchants = [];

                foreach ($rewardItem->getEnchantments() as $enchantment) {
                    $name = $enchantment->getType()->getName();
                    if ($name instanceof Translatable) {
                        $name = Server::getInstance()->getLanguage()->translate($name);
                    }

                    $enchants[] = $name . ' ' . RomanNumbers::getRomanNumber($enchantment->getLevel());
                }

                $lines[] = $rewardItem->getCount() . 'x ' . TextFormat::clean($rewardItem->getName()) . (count($enchants) > 0 ? ' (' . implode(', ', $enchants) . ')' : '');
            }
        }

        return implode(TextFormat::EOL, $lines);
    }

    /**
     * @return array
     */
    public function getReward(): array
    {
        return $this->reward;
    }

    /**
     * @param object[] $reward
     */
    public function setReward(array $reward): void
    {
        $this->reward = $reward;
    }

}