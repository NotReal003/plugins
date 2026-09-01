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
use pocketmine\item\Item;
use pocketmine\utils\TextFormat;

class GetItemInInventoryAction extends ChallengeAction
{
    /** @var Item */
    protected Item $item;

    public function __construct(int $goal, Item $item)
    {
        parent::__construct($goal);
        $this->item = $item;
    }

    public function getActionType(): int
    {
        return ChallengeSet::GET_ITEM_IN_INVENTORY;
    }

    public function shouldIncreaseProgress(?object $object): bool
    {
        if ($object instanceof Item) {
            return $object->getTypeId() === $this->item->getTypeId();
        }
        return false;
    }

    public function toDisplayString(): string
    {
        return TextFormat::YELLOW . 'Obtain ' . TextFormat::GOLD . $this->getGoal() . TextFormat::YELLOW . ' items of ' . TextFormat::GOLD . $this->item->getName();
    }
}