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

use pocketmine\entity\Living;
use pocketmine\event\entity\EntityEvent;
use pocketmine\item\Item;

/**
 * @phpstan-extends EntityEvent<Living>
 */
class EntityArmorChangeEvent extends EntityEvent
{
    /** @var Item */
    private Item $oldItem;
    /** @var Item */
    private Item $newItem;
    /** @var int */
    private int $slot;

    public function __construct(Living $entity, Item $oldItem, Item $newItem, int $slot)
    {
        $this->entity = $entity;
        $this->oldItem = $oldItem;
        $this->newItem = $newItem;
        $this->slot = $slot;
    }

    public function getSlot(): int
    {
        return $this->slot;
    }

    public function getNewItem(): Item
    {
        return $this->newItem;
    }

    public function setNewItem(Item $item): void
    {
        $this->newItem = $item;
    }

    public function getOldItem(): Item
    {
        return $this->oldItem;
    }
}