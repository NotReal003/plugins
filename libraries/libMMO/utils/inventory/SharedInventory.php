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

declare(strict_types=1);

namespace libMMO\utils\inventory;

use libMMO\utils\InvestigationManager;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\InventoryListener;
use pocketmine\item\Item;

class SharedInventory implements InventoryListener
{
    /** @var Inventory|null */
    private ?Inventory $inventory;
    /** @var SharedInventory|null */
    private ?SharedInventory $object = null;
    /** @var bool */
    private bool $doModification = true; // To prevent deadlocking the server

    public function __construct(?Inventory $inventory)
    {
        $this->inventory = $inventory;
    }

    public function startModification(): void
    {
        $this->doModification = false;
    }

    public function stopModification(): void
    {
        $this->doModification = true;
    }

    public function setLinkedInventory(SharedInventory $object): void
    {
        $this->object = $object;
    }

    public function setInventory(?Inventory $inventory): void
    {
        $this->inventory = $inventory;
    }

    public function onSlotChange(Inventory $inventory, int $slot, Item $oldItem): void
    {
        if (!$this->doModification || $this->inventory === null) {
            return;
        }

        $this->object?->startModification();

        if ($inventory instanceof ArmorInventory) {
            $this->inventory->setItem(InvestigationManager::ARMOR_INVENTORY_MENU_SLOTS[$slot], $inventory->getItem($slot));
        } else {
            $this->inventory->setItem($slot, $inventory->getItem($slot));
        }

        $this->object?->stopModification();
    }

    public function onContentChange(Inventory $inventory, array $oldContents): void
    {
        if (!$this->doModification || $this->inventory === null) {
            return;
        }

        $this->object?->startModification();

        if ($inventory instanceof ArmorInventory) {
            foreach ($inventory->getContents(true) as $slot => $item) {
                $this->inventory->setItem(InvestigationManager::ARMOR_INVENTORY_MENU_SLOTS[$slot], $item);
            }
        } else {
            foreach ($inventory->getContents(true) as $slot => $item) {
                $this->inventory->setItem($slot, $item);
            }
        }

        $this->object?->stopModification();
    }

    public function getInventory(): Inventory
    {
        return $this->inventory;
    }
}