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

namespace factions\entities;

use factions\entities\explosive\PrimedTNT;
use libMMO\MMOPlugin;
use libVanilla\entity\registry\ActorList;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;

class EntityManager extends \libMMO\entities\EntityManager
{
    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct($plugin);

        $entityFactory = EntityFactory::getInstance();
        $entityFactory->register(PrimedTNT::class, function (World $world, CompoundTag $nbt): PrimedTNT {
            return new PrimedTNT(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ['PrimedTnt', 'PrimedTNT', 'minecraft:tnt']);

        /** @var ActorList $mob */
        foreach (StackableRegistry::getAll() as $mob) {
            $entityFactory->register($mob->getClass(), function (World $world, CompoundTag $nbt) use ($mob): Entity {
                $class = $mob->getClass();
                return new $class(EntityDataHelper::parseLocation($nbt, $world), $nbt);
            }, [$mob->getName(), $mob->getNewId()]);
        }

        $plugin->getLogger()->info(TextFormat::GREEN . 'Successfully registered all custom entities.');
    }
}