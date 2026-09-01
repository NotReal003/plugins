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

namespace libMMO\entities;

use libMMO\entities\dummy\DummyEntity;
use libMMO\entities\projectile\EnderPearl;
use libMMO\entities\projectile\TrackableArrow;
use libMMO\MMOPlugin;
use libMMO\utils\BaseClass;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\World;
use function time;

abstract class EntityManager extends BaseClass
{
    protected array $despawnQueue = [];

    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct($plugin);

        $plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (): void {
            /**
             * @var Entity $entity
             */
            foreach ($this->despawnQueue as $idx => [$entity, $time]) {
                $alreadyDespawned = $entity->isFlaggedForDespawn() || $entity->isClosed();

                if ($time > time() && !$alreadyDespawned) {
                    continue;
                }

                if (!$alreadyDespawned) {
                    $entity->flagForDespawn();
                }

                unset($this->despawnQueue[$idx]);
            }

            $this->despawnQueue = [];
        }), 20);
        $plugin->getServer()->getPluginManager()->registerEvents(new EntityListener($plugin), $plugin);

        $entityFactory = EntityFactory::getInstance();
        $entityFactory->register(DummyEntity::class, function (World $world, CompoundTag $nbt): Entity {
            return new DummyEntity(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ['item_dummy', 'minecraft:item_dummy']);
        $entityFactory->register(TrackableArrow::class, function (World $world, CompoundTag $nbt): TrackableArrow {
            return new TrackableArrow(EntityDataHelper::parseLocation($nbt, $world), null, false, $nbt);
        }, ['Arrow', 'minecraft:arrow']);
        $entityFactory->register(OptimizedItemEntity::class, function (World $world, CompoundTag $nbt): Entity {
            $itemTag = $nbt->getCompoundTag("Item");

            if ($itemTag === null) {
                return new DummyEntity(EntityDataHelper::parseLocation($nbt, $world), $nbt);
            }

            $item = Item::nbtDeserialize($itemTag);
            if ($item->isNull()) {
                return new DummyEntity(EntityDataHelper::parseLocation($nbt, $world), $nbt);
            }

            return new OptimizedItemEntity(EntityDataHelper::parseLocation($nbt, $world), $item, $nbt);
        }, ['Item', 'minecraft:item']);
        $entityFactory->register(PlayerHead::class, function (World $world, CompoundTag $nbt): PlayerHead {
            return new PlayerHead(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ["ngskyblock:player_head"]);
        $entityFactory->register(EnderPearl::class, function (World $world, CompoundTag $nbt): EnderPearl {
            return new EnderPearl(EntityDataHelper::parseLocation($nbt, $world), null, $nbt);
        }, ['ThrownEnderpearl', 'minecraft:ender_pearl']);

        PlayerHead::setup($plugin);
    }

    public function queueForDespawn(Entity $entity, int $countdown = 0): void
    {
        $this->despawnQueue[] = [$entity, time() + $countdown];
    }
}
