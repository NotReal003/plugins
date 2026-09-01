<?php
/**
 *         _____ _          _     _            _
 *        / ____| |        | |   | |          | |
 *  __  _| (___ | | ___   _| |__ | | ___   ___| | __
 *  \ \/ /\___ \| |/ / | | | '_ \| |/ _ \ / __| |/ /
 *   >  < ____) |   <| |_| | |_) | | (_) | (__|   <
 *  /_/\_\_____/|_|\_\\__, |_.__/|_|\___/ \___|_|\_\
 *                     __/ |
 *                    |___/
 *
 * Copyright (C) 2016-2022 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder
 */

declare(strict_types=1);

namespace skyblock\entities\stackable;


use libMMO\entities\stackable\StackableInterface;
use libMMO\entities\stackable\StackableTrait;
use libVanilla\entity\neutral\IronGolem;
use pocketmine\entity\Entity;

class StackableIronGolem extends IronGolem implements StackableInterface
{
    use StackableTrait;

    public function getStackedEntity(): StackableInterface|Entity
    {
        return $this;
    }

    public function getCustomName(): string
    {
        return "Iron Golem";
    }
}