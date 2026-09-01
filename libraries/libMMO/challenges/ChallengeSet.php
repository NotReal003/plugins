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

abstract class ChallengeSet
{
    public const BREAK_BLOCKS = 1;
    public const PLACE_BLOCKS = 2;
    public const BANK_STORE_MONEY = 4;
    public const GET_ITEM_IN_INVENTORY = 5;
    public const ITEM_PICKUP = 6;
    public const KILL_ENTITY = 7;
    public const GRAVE_COLLECT = 8;
    //reserved
    public const COLLECT_BOUNTY = 10;
    public const CRATE_OPEN = 11;
    public const REPAIR_ITEM = 12;

    abstract public function setup(ChallengeManager $manager): void;
}