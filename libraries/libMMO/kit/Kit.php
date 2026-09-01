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

namespace libMMO\kit;

use pocketmine\item\Item;
use pocketmine\utils\TextFormat;

class Kit
{
    /** @var string */
    private string $title;
    /** @var int */
    private int $cooldown;
    /** @var Item[] */
    private array $items;
    /** @var string */
    private string $permission;
    /** @var string */
    private string $color;

    /**
     * Constructs a new kit for KitManager.
     * Cooldowns should be in minutes.
     *
     * @param string $title
     * @param int $cooldown
     * @param array $items
     * @param string $permission
     * @param string $color
     */
    public function __construct(string $title, int $cooldown, array $items, string $permission, string $color = TextFormat::WHITE)
    {
        $this->title = $title;
        $this->cooldown = $cooldown;
        $this->items = $items;
        $this->permission = $permission;
        $this->color = $color;
    }

    /**
     * Returns the name of the kit.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Returns the cooldown time in minutes.
     *
     * @return int
     */
    public function getCooldown(): int
    {
        return $this->cooldown;
    }

    /**
     * Returns the items that the kit will redeem.
     *
     * @return Item[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Permission to use the kit, if none returns an empty string
     *
     * @return string
     */
    public function getPermission(): string
    {
        return $this->permission;
    }

    /**
     * Returns the color of the kit item name
     *
     * @return string
     */
    public function getColor(): string
    {
        return $this->color;
    }
}