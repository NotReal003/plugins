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

namespace skyblock\block;

use Closure;
use LogicException;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\World;
use skyblock\entities\stackable\StackableChicken;
use skyblock\entities\stackable\StackableCow;
use skyblock\entities\stackable\StackableIronGolem;
use skyblock\entities\stackable\StackableMooshroom;
use skyblock\entities\stackable\StackablePig;
use skyblock\entities\stackable\StackableRabbit;
use skyblock\entities\stackable\StackableSkeleton;
use skyblock\entities\stackable\StackableSpider;
use skyblock\entities\stackable\StackableZombie;

final class SpawnerLevel
{
    /** @var SpawnerLevel[] */
    private static array $levels;

    /** @var int */
    private int $level;
    /** @var int */
    private int $price;
    /**
     * @var Closure
     * @phpstan-var Closure(World, Vector3) : Entity
     */
    private Closure $entityCreateFn;
    /** @var string */
    private string $entityNetworkId;
    /**
     * @var string
     * @phpstan-var class-string<Entity>
     */
    private string $entityClass;

    /**
     * @phpstan-param Closure(World, Vector3) : Entity $entityCreateFn
     * @phpstan-param class-string<Entity> $entityClass
     * @param string $entityNetworkId ID used for network NBT for rendering
     */
    private function __construct(int $level, int $price, string $entityClass, Closure $entityCreateFn, string $entityNetworkId)
    {
        $this->level = $level;
        $this->price = $price;
        $this->entityCreateFn = $entityCreateFn;
        $this->entityNetworkId = $entityNetworkId;
        $this->entityClass = $entityClass;
    }

    public static function default(): SpawnerLevel
    {
        $result = self::get(1);
        if ($result === null) {
            throw new LogicException('Spawner level 1 missing, this should never happen');
        }
        return $result;
    }

    public static function get(int $id): ?SpawnerLevel
    {
        return self::$levels[$id] ?? self::init()[$id] ?? null;
    }

    /**
     * @return SpawnerLevel[]
     */
    private static function init(): array
    {
        self::$levels = [];

        foreach ([
                     new SpawnerLevel(1, 0, StackableChicken::class, static function (World $world, Vector3 $pos): Entity {
                         return new StackableChicken(Location::fromObject($pos, $world));
                     }, EntityIds::CHICKEN),
                     new SpawnerLevel(2, 10000, StackableRabbit::class, static function (World $world, Vector3 $pos): Entity {
                         return new StackableRabbit(Location::fromObject($pos, $world));
                     }, EntityIds::RABBIT),
                     new SpawnerLevel(3, 30000, StackableCow::class, static function (World $world, Vector3 $pos): Entity {
                         return new StackableCow(Location::fromObject($pos, $world));
                     }, EntityIds::COW),
                     new SpawnerLevel(4, 50000, StackableMooshroom::class, static function (World $world, Vector3 $pos): Entity {
                         return new StackableMooshroom(Location::fromObject($pos, $world));
                     }, EntityIds::MOOSHROOM),
                     new SpawnerLevel(5, 100000, StackablePig::class, static function (World $world, Vector3 $pos): Entity {
                         return new StackablePig(Location::fromObject($pos, $world));
                     }, EntityIds::PIG),
                     new SpawnerLevel(6, 150000, StackableZombie::class, static function (World $world, Vector3 $pos): Entity {
                         return new StackableZombie(Location::fromObject($pos, $world));
                     }, EntityIds::ZOMBIE),
                     new SpawnerLevel(7, 300000, StackableSpider::class, static function (World $world, Vector3 $pos): Entity {
                         return new StackableSpider(Location::fromObject($pos, $world));
                     }, EntityIds::SPIDER),
                     new SpawnerLevel(8, 600000, StackableSkeleton::class, static function (World $world, Vector3 $pos): Entity {
                         return new StackableSkeleton(Location::fromObject($pos, $world));
                     }, EntityIds::SKELETON),
                     new SpawnerLevel(9, 1200000, StackableIronGolem::class, static function (World $world, Vector3 $pos): Entity {
                         return new StackableIronGolem(Location::fromObject($pos, $world));
                     }, EntityIds::IRON_GOLEM)
                 ] as $level) {
            self::$levels[$level->getId()] = $level;
        }

        return self::$levels;
    }

    public function getId(): int
    {
        return $this->level;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    /**
     * @phpstan-return class-string<Entity>
     */
    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getEntityNetworkId(): string
    {
        return $this->entityNetworkId;
    }

    public function spawn(World $world, Vector3 $pos): Entity
    {
        /** @var Entity $entity */
        $entity = ($this->entityCreateFn)($world, $pos);
        $entity->spawnToAll();

        return $entity;
    }

    public function getNextLevel(): ?SpawnerLevel
    {
        return self::get($this->level + 1);
    }
}