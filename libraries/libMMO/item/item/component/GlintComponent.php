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
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder
 */

namespace libMMO\item\item\component;

use NetherGames\NGEssentials\item\component\ItemComponent;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\Tag;

class GlintComponent extends ItemComponent
{
    public function getName(): string
    {
        return "minecraft:glint";
    }

    public function getValue(int $protocolId): Tag
    {
        return new ByteTag(1);
    }
}