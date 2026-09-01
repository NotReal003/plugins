<?php
/**
 *        ______         _   _
 *       |  ____|       | | (_)
 *  __  _| |__ __ _  ___| |_ _  ___  _ __  ___
 *  \ \/ /  __/ _` |/ __| __| |/ _ \| '_ \/ __|
 *   >  <| | | (_| | (__| |_| | (_) | | | \__ \
 *  /_/\_\_|  \__,_|\___|\__|_|\___/|_| |_|___/
 *
 * Copyright (C) 2016-2021 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author larryTheCoder
 */

declare(strict_types=1);

namespace factions\entities;

use factions\entities\stackable\StackableCow;
use factions\entities\stackable\StackableIronGolem;
use factions\entities\stackable\StackableSheep;
use factions\entities\stackable\StackableSpider;
use factions\entities\stackable\StackableZombie;
use libVanilla\entity\registry\ActorList;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\utils\RegistryTrait;

/**
 * This doc-block is generated automatically, do not modify it manually.
 * This must be regenerated whenever registry members are added, removed or changed.
 * @see \pocketmine\utils\RegistryUtils::_generateMethodAnnotations()
 *
 * @method static ActorList ZOMBIE()
 * @method static ActorList SPIDER()
 * @method static ActorList COW()
 * @method static ActorList SHEEP()
 * @method static ActorList IRON_GOLEM()
 */
final class StackableRegistry
{
    use RegistryTrait;

    /**
     * @return object[]
     */
    public static function getAll(): array
    {
        return self::_registryGetAll();
    }

    protected static function setup(): void
    {
        self::register("zombie", new ActorList(StackableZombie::class, "Zombie", EntityIds::ZOMBIE));
        self::register("spider", new ActorList(StackableSpider::class, "Spider", EntityIds::SPIDER));
        self::register("cow", new ActorList(StackableCow::class, "Cow", EntityIds::COW));
        self::register("sheep", new ActorList(StackableSheep::class, "Sheep", EntityIds::SHEEP));
        self::register("iron_golem", new ActorList(StackableIronGolem::class, "IronGolem", EntityIds::IRON_GOLEM));
    }

    protected static function register(string $name, ActorList $mobList): void
    {
        self::_registryRegister($name, $mobList);
    }
}