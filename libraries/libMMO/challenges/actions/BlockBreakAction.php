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

namespace libMMO\challenges\actions;

use libMMO\challenges\ChallengeAction;
use libMMO\challenges\ChallengeSet;
use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\utils\TextFormat;

class BlockBreakAction extends ChallengeAction
{
    /** @var Block */
    protected Block $block;

    public function __construct(Block $block, int $goal)
    {
        parent::__construct($goal);

        $this->block = $block;
    }

    public function toDisplayString(): string
    {
        return TextFormat::YELLOW . 'Break ' . TextFormat::GOLD . $this->getGoal() . TextFormat::YELLOW . ' blocks of ' . TextFormat::YELLOW . $this->block->getName();
    }

    public function shouldIncreaseProgress(?object $object): bool
    {
        return ($object instanceof BlockBreakEvent) && ($object->getBlock()->getTypeId() === $this->block->getTypeId());
    }

    public function equals(ChallengeAction $action): bool
    {
        return ($action instanceof self) && $action->block->getTypeId() === $this->block->getTypeId();
    }

    public function getActionType(): int
    {
        return ChallengeSet::BREAK_BLOCKS;
    }
}