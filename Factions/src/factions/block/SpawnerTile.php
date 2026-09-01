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

namespace factions\block;

use factions\entities\StackableRegistry;
use libMMO\entities\stackable\StackingEngine;
use libMMO\utils\Utils;
use libVanilla\entity\registry\ActorList;
use libVanilla\entity\utils\EntitySizeUtils;
use pocketmine\block\tile\Spawnable;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\world\World;
use RuntimeException;

final class SpawnerTile extends Spawnable
{
    public const DISPLAY_ENTITY_SCALE = 'DisplayEntityScale';
    public const ENTITY_IDENTIFIER = 'EntityIdentifier';
    public const NG_ENTITY_IDENTIFIER = 'NGEntityId';

    /** @var string|null */
    private ?string $spawnerId = null;

    public function __construct(World $world, Vector3 $pos)
    {
        parent::__construct($world, $pos);

        $pos = $this->getPosition();
        SpawnerBlock::scheduleBlockUpdate($pos->getWorld(), $pos, mt_rand(SpawnerBlock::SPAWN_INTERVAL_MIN, SpawnerBlock::SPAWN_INTERVAL_MAX) * 20);
    }

    public function isValid(): bool
    {
        return $this->spawnerId !== null;
    }

    public function stackEntity(): ?Entity
    {
        $entity = $this->getSpawnerEntity();

        $targetStack = StackingEngine::searchForStack($this->getPosition(), $entity->getClass());
        if ($targetStack !== null) {
            $targetStack->stack(1);

            return $targetStack;
        }

        return null;
    }

    public function spawn(World $world, Vector3 $pos): ?Entity
    {
        $entity = $this->getSpawnerEntity();

        $sizeInfo = $this->getEntitySizeInfo();
        $halfWidth = $sizeInfo->getWidth() / 2;

        $bbCheck = new AxisAlignedBB(
            $pos->x - $halfWidth,
            $pos->y,
            $pos->z - $halfWidth,
            $pos->x + $halfWidth,
            $pos->y + $sizeInfo->getHeight(),
            $pos->z + $halfWidth
        );

        if (count($this->getPosition()->getWorld()->getCollisionBlocks($bbCheck, true)) > 0) {
            return null;
        }

        $class = $entity->getClass();

        $nbt = CompoundTag::create()->setTag("Pos", new ListTag([
            new DoubleTag($pos->x),
            new DoubleTag($pos->y),
            new DoubleTag($pos->z)
        ]))->setTag("Motion", new ListTag([
            new DoubleTag(0.0),
            new DoubleTag(0.0),
            new DoubleTag(0.0)
        ]))->setTag("Rotation", new ListTag([
            new FloatTag(0.0),
            new FloatTag(0.0)
        ]))->setString('NGEntityName', $this->getSpawnerEntity()->getName());

        /** @var Entity $entity */
        $entity = new $class(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        $entity->spawnToAll();

        return $entity;
    }

    public function getSpawnerEntity(): ActorList
    {
        return match ($this->spawnerId) {
            StackableRegistry::IRON_GOLEM()->getNewId() => StackableRegistry::IRON_GOLEM(),
            StackableRegistry::COW()->getNewId() => StackableRegistry::COW(),
            StackableRegistry::SHEEP()->getNewId() => StackableRegistry::SHEEP(),
            StackableRegistry::SPIDER()->getNewId() => StackableRegistry::SPIDER(),
            StackableRegistry::ZOMBIE()->getNewId() => StackableRegistry::ZOMBIE(),
            default => throw new RuntimeException("Entity spawner id must always exists.")
        };
    }

    private function getEntitySizeInfo(): EntitySizeInfo
    {
        static $entitySizeInfo = [];
        if (empty($entitySizeInfo)) {
            $entitySizeInfo[StackableRegistry::IRON_GOLEM()->getNewId()] = EntitySizeUtils::upright(2.9, 1.4);
            $entitySizeInfo[StackableRegistry::ZOMBIE()->getNewId()] = EntitySizeUtils::upright(1.9, 0.6);
            $entitySizeInfo[StackableRegistry::COW()->getNewId()] = new EntitySizeInfo(1.3, 0.9);
            $entitySizeInfo[StackableRegistry::SHEEP()->getNewId()] = new EntitySizeInfo(1.3, 0.9);
            $entitySizeInfo[StackableRegistry::SPIDER()->getNewId()] = new EntitySizeInfo(0.9, 1.4);
        }

        return $entitySizeInfo[$this->spawnerId] ?? throw new RuntimeException("Entity spawner id must always exists.");
    }

    public function addAdditionalSpawnData(CompoundTag $nbt, TypeConverter $typeConverter): void
    {
        $nbt->setString(self::TAG_ID, 'MobSpawner');

        if (!$this->isValid()) {
            return;
        }

        $nbt->setString(self::ENTITY_IDENTIFIER, $this->getSpawnerEntity()->getNewId());
        $nbt->setFloat(self::DISPLAY_ENTITY_SCALE, 1.0);
    }

    public function readSaveData(CompoundTag $nbt): void
    {
        if (!Utils::hasTag($nbt, self::NG_ENTITY_IDENTIFIER, StringTag::class)) {
            return;
        }

        $spawnId = $nbt->getString(self::NG_ENTITY_IDENTIFIER, "");

        if (!empty($spawnId)) {
            $this->spawnerId = $spawnId;
        }
    }

    protected function writeSaveData(CompoundTag $nbt): void
    {
        if (!$this->isValid()) {
            return;
        }

        $nbt->setString(self::NG_ENTITY_IDENTIFIER, $this->spawnerId);
    }
}