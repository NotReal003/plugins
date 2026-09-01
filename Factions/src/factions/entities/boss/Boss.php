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

namespace factions\entities\boss;

use Exception;
use libPhysX\internal\Rotation;
use libPhysX\PhysX;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\ChunkListener;
use pocketmine\world\ChunkListenerNoOpTrait;
use function abs;
use function count;
use function floor;
use function in_array;
use function mt_rand;

abstract class Boss extends Human implements BossId, ChunkListener
{
    use ChunkListenerNoOpTrait;

    protected const JUMP_OFFSET = 0.3;
    protected const SCALE_DEFAULT = 2;
    protected const SPP_TICK_DEFAULT = 0;
    protected const SPP_TICK_MAX = 20;
    protected const MINION_MAX = 50;
    protected const SPAWN_MINION_TICK_DEFAULT = 0;
    protected const SPAWN_MINION_TICK_MAX = 100;
    protected const HIT_TICK_DEFAULT = 0;
    protected const HIT_TICK_MAX = 20;
    protected const HIT_VERTICAL_REACH_DEFAULT = 3.0;
    protected const HEALTH_DEFAULT = 100; // 50 hearts
    protected const MAX_DISTANCE = 100;
    protected const BOSS_DAMAGE_LEVEL_MULTIPLIER = 0.35;
    protected const BOSS_HEALTH_LEVEL_MULIPLIER = 0.35;

    /** @var int */
    protected int $bossId;
    /** @var float */
    protected float $speed;
    /** @var Vector3 */
    protected Vector3 $previousPosition;
    /** @var int - Set previous position tick */
    protected int $sppTick = self::SPP_TICK_DEFAULT; // set to false as minion skin doesn't exist.
    /** @var bool */
    protected bool $spawnMinion = false;
    /** @var int */
    protected int $maximumMinion = self::MINION_MAX;
    /** @var int */
    protected int $spawnMinionTick = self::SPAWN_MINION_TICK_DEFAULT;
    /** @var bool */
    protected bool $nearTarget = false;
    /** @var int */
    protected int $hitTick = self::HIT_TICK_DEFAULT;
    /** @var float */
    protected float $hitVerticalReach = self::HIT_VERTICAL_REACH_DEFAULT;
    /** @var int[] */
    protected array $acceptedHitTickList = [];
    /** @var int */
    protected int $minimumCPS = 1;
    /** @var int */
    protected int $maximumCPS = 2;
    /** @var float */
    protected float $damage;
    /** @var int */
    protected int $spawnHealth = self::HEALTH_DEFAULT;
    /** @var int */
    protected int $bossLevel = 1;

    public function __construct(Location $location, Skin $skin)
    {
        $this->skin = $skin;

        parent::__construct($location, $skin);

        $this->jumpVelocity += self::JUMP_OFFSET;

        $this->setMaxHealth((int)($this->spawnHealth + ($this->spawnHealth * $this->getBossLevel() * self::BOSS_HEALTH_LEVEL_MULIPLIER)));
        $this->setHealth((int)($this->spawnHealth + ($this->spawnHealth * $this->getBossLevel() * self::BOSS_HEALTH_LEVEL_MULIPLIER)));
        $this->setCanSaveWithChunk(false);
    }

    /**
     * @return int
     */
    public function getBossLevel(): int
    {
        return $this->bossLevel;
    }

    /**
     * @param int $bossLevel
     */
    public function setBossLevel(int $bossLevel): void
    {
        $this->bossLevel = $bossLevel;
    }

    public function setHealth(float $amount): void
    {
        parent::setHealth($amount);
        $hearts = '';
        $heartValue = floor($this->getMaxHealth() / 10);

        for ($i = 1; $i <= $this->getMaxHealth(); $i++) {
            if ($i % $heartValue === 0) {
                if ($i <= $this->getHealth()) {
                    $hearts .= TextFormat::GREEN . ' ■';
                } else {
                    $hearts .= TextFormat::RED . ' ■';
                }
            }
        }
        $this->setNameTag(TextFormat::GOLD . TextFormat::BOLD . 'BOSS (Level ' . $this->getBossLevel() . ') ' . TextFormat::RESET . TextFormat::EOL . $hearts);
    }

    /**
     * Runs at the entity's tick change.
     *
     * @param int $tickDiff
     * @return bool
     * @throws Exception
     */
    public function entityBaseTick(int $tickDiff = 1): bool
    {
        if ($this->sppTick === self::SPP_TICK_MAX) {
            $this->sppTick = self::SPP_TICK_DEFAULT;
            $this->previousPosition = $this->getPosition()->asVector3();
        } else {
            $this->sppTick++;
        }

        if ($this->getTargetEntity() === null || $this->getTargetEntity()->getPosition()->distance($this->getPosition()->asVector3()) > self::MAX_DISTANCE || $this->getTargetEntity()->getGamemode() === GameMode::SPECTATOR() || mt_rand(0, 10) === 0) {
            $this->setTargetEntity($this->getNearestPlayer());
        } else {
            $this->moveToTargetEntity();
            $this->attackTargetAtCPS();
        }
        $this->getHungerManager()->setFood(20);

        return parent::entityBaseTick($tickDiff);
    }

    /**
     * @return Player|null
     */
    public function getTargetEntity(): ?Entity
    {
        /** @var Player|null $target */
        $target = parent::getTargetEntity();

        return $target;
    }

    public function getNearestPlayer(): ?Player
    {
        $player = null;
        $lastDistance = PHP_INT_MAX;

        foreach ($this->getViewers() as $p) {
            if ($p->getGamemode() !== GameMode::SPECTATOR()) {
                $distance = $this->getPosition()->distance($p->getPosition()->asVector3());
                if ($distance < $lastDistance) {
                    $lastDistance = $distance;
                    $player = $p;
                }
            }
        }
        return $player;
    }

    /**
     * Initiates movement to the target entity.
     *
     * @return void
     */
    protected function moveToTargetEntity(): void
    {
        /** @var Vector3 $motion */
        /** @var Rotation $rotation */
        [$motion, $rotation] = PhysX::calculateMRPhysic($this->getPosition()->asVector3(), $this->getTargetEntity()->getPosition()->asVector3(), $this->speed / 2, true, false, 4);

        $this->move($motion->getX(), $motion->getY(), $motion->getZ());
        $this->setRotation($rotation->yaw, $rotation->pitch);

        $this->jumpIfNecessary();

        if ((float)$this->motion->getX() === 0.0 || (float)$this->motion->getZ() === 0.0) {
            $this->nearTarget = true;
        } else {
            $this->nearTarget = false;
        }
    }

    /**
     * Checks if a jump is necessary. If it is, it calls the jump method making the entity initiate a jump.
     *
     * @return void
     */
    protected function jumpIfNecessary(): void
    {
        if (isset($this->previousPosition)) {
            $distance = $this->getPosition()->distance($this->previousPosition);
            if ($distance < 1) {
                $this->jump();
            }
        }
    }

    /**
     * Attacks the target entity depending on CPS.
     *
     * @return void
     */
    protected function attackTargetAtCPS(): void
    {
        if (count($this->acceptedHitTickList) === 0) {
            $this->generateAcceptedHitTickListWithCPS();
            $this->attackTargetAtCPS();
        }

        foreach ($this->acceptedHitTickList as $acceptedHitTick) {
            if ($this->hitTick = $acceptedHitTick) {
                if ($this->hitTick === self::HIT_TICK_MAX) {
                    $this->hitTick = self::HIT_TICK_DEFAULT;
                    $this->generateAcceptedHitTickListWithCPS();
                }
                $this->attackTarget();
            }
        }

        $this->hitTick++;
    }

    /**
     * Generates an accepted hit tick list depending on minimum and maximum CPS.
     *
     * @return void
     */
    protected function generateAcceptedHitTickListWithCPS(): void
    {
        $cps = mt_rand($this->minimumCPS, $this->maximumCPS);
        $this->acceptedHitTickList = [];

        for ($i = $this->minimumCPS; $i <= $cps; $i++) {
            $possibleTick = mt_rand(self::HIT_TICK_DEFAULT, self::HIT_TICK_MAX);

            while (in_array($possibleTick, $this->acceptedHitTickList, true)) {
                // make sure of no duplicates.
                $possibleTick = mt_rand(self::HIT_TICK_DEFAULT, self::HIT_TICK_MAX);
            }

            $this->acceptedHitTickList[] = $possibleTick;
        }
    }

    /**
     * Attacks the target if near the target.
     *
     * @return void
     */
    protected function attackTarget(): void
    {
        if ($this->nearTarget) {
            $target = $this->getTargetEntity();

            if ($target instanceof Player) {
                $deltaY = $target->getPosition()->getY() - $this->getPosition()->getY();
                if (abs($deltaY) < $this->hitVerticalReach) {
                    $event = new EntityDamageByEntityEvent($this, $target, EntityDamageByEntityEvent::CAUSE_ENTITY_ATTACK, ($this->damage + ($this->damage * ($this->getBossLevel() * self::BOSS_DAMAGE_LEVEL_MULTIPLIER))));
                    $target->attack($event);
                }
            }
        }
    }

    /**
     * @param float $damage
     */
    public function setDamage(float $damage): void
    {
        $this->damage = $damage;
    }

    protected function syncNetworkData(EntityMetadataCollection $properties): void
    {
        parent::syncNetworkData($properties);

        if ($this->nearTarget) {
            $this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::IDLING, true);
        } else {
            $this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::MOVING, true);
        }
    }
}