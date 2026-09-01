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

class VaultStorage
{
    /** @var SharedInventory */
    public SharedInventory $player;
    /** @var SharedInventory */
    public SharedInventory $viewer;

    /** @var string */
    public string $playerName = '';
    /** @var string|null */
    public ?string $xuidToPlayer = null;
    /** @var string|null */
    public ?string $offlineObject = null; // When offline, this variable will be set to the last saved inventory hash.
    /** @var int */
    public int $vaultModified = 0;
}