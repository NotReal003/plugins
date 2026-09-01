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
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew
 *
 */
declare(strict_types=1);

namespace skyblock\entities;

use pocketmine\entity\Entity;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\World;
use skyblock\entities\helpers\HelperManager;
use skyblock\entities\island\IslandNPC;
use skyblock\entities\stackable\StackableChicken;
use skyblock\entities\stackable\StackableCow;
use skyblock\entities\stackable\StackableIronGolem;
use skyblock\entities\stackable\StackableMooshroom;
use skyblock\entities\stackable\StackablePig;
use skyblock\entities\stackable\StackableRabbit;
use skyblock\entities\stackable\StackableSkeleton;
use skyblock\entities\stackable\StackableSpider;
use skyblock\entities\stackable\StackableZombie;
use skyblock\SkyBlock;

class EntityManager extends \libMMO\entities\EntityManager
{
    /** @var HelperManager */
    private HelperManager $helperManager;

    public function __construct(SkyBlock $plugin)
    {
        parent::__construct($plugin);

        /** @var EntityFactory $entityFactory */
        $entityFactory = EntityFactory::getInstance();

        if (!$plugin->isAgora()) {
            $entityFactory->register(IslandNPC::class, function (World $world, CompoundTag $nbt): IslandNPC {
                return new IslandNPC(EntityDataHelper::parseLocation($nbt, $world), $nbt);
            }, ["ngskyblock:island_npc"]);

            $this->helperManager = new HelperManager($plugin);
        }

        $entityFactory->register(StackableChicken::class, function (World $world, CompoundTag $nbt): Entity {
            return new StackableChicken(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ["Chicken", EntityIds::CHICKEN]);
        $entityFactory->register(StackableCow::class, function (World $world, CompoundTag $nbt): Entity {
            return new StackableCow(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ["Cow", EntityIds::COW]);
        $entityFactory->register(StackableIronGolem::class, function (World $world, CompoundTag $nbt): Entity {
            return new StackableIronGolem(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ["IronGolem", EntityIds::IRON_GOLEM]);
        $entityFactory->register(StackableMooshroom::class, function (World $world, CompoundTag $nbt): Entity {
            return new StackableMooshroom(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ["Mooshroom", EntityIds::MOOSHROOM]);
        $entityFactory->register(StackablePig::class, function (World $world, CompoundTag $nbt): Entity {
            return new StackablePig(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ["Pig", EntityIds::PIG]);
        $entityFactory->register(StackableRabbit::class, function (World $world, CompoundTag $nbt): Entity {
            return new StackableRabbit(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ["Rabbit", EntityIds::RABBIT]);
        $entityFactory->register(StackableSkeleton::class, function (World $world, CompoundTag $nbt): Entity {
            return new StackableSkeleton(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ["Skeleton", EntityIds::SKELETON]);
        $entityFactory->register(StackableSpider::class, function (World $world, CompoundTag $nbt): Entity {
            return new StackableSpider(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ["Spider", EntityIds::SPIDER]);
        $entityFactory->register(StackableZombie::class, function (World $world, CompoundTag $nbt): Entity {
            return new StackableZombie(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ["Zombie", EntityIds::ZOMBIE]);
    }

    public function getHelperManager(): HelperManager
    {
        return $this->helperManager;
    }
}