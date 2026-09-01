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

use Closure;
use libMMO\item\ItemStorage;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\entity\ItemDespawnEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;

class OptimizedItemEntity extends ItemEntity
{
    /** @var Closure[] */
    private static array $validators = [];

    private const DESPAWN_DELAY = 2 * 60 * 20;
    private const STACK_DISTANCE = 3;

    private const YELLOW = 30;
    private const RED = 10;

    /**
     * Add a validation to this entity to check if it is able to spawn
     * name tags.
     *
     * @phpstan-param Closure(OptimizedItemEntity $entity) : bool $validator
     */
    public static function addValidator(Closure $validator): void
    {
        Utils::validateCallableSignature(function (OptimizedItemEntity $entity): bool {
            return true;
        }, $validator);

        self::$validators[] = $validator;
    }

    /**
     * Runs at the entity's base tick.
     *
     * @param int $tickDiff
     * @return bool
     */
    public function entityBaseTick(int $tickDiff = 1): bool
    {
        if ($this->despawnDelay % 20 === 0) {
            if ($this->despawnDelay <= 0) {
                $event = new ItemDespawnEvent($this);
                $event->call();

                if ($event->isCancelled()) {
                    $this->refreshAge();
                } else {
                    $this->flagForDespawn();
                    return false;
                }
            }

            $parentTick = parent::entityBaseTick($tickDiff);

            if ($this->motion->lengthSquared() == 0.0) {
                $timeLeft = (int)($this->despawnDelay / 20);

                if ($timeLeft <= self::RED) {
                    $this->updateNameTag(TextFormat::RED . 'Removed in ' . $timeLeft . 's');
                } elseif ($timeLeft <= self::YELLOW) {
                    if ($timeLeft % 5 === 0) {
                        $this->updateNameTag(TextFormat::YELLOW . 'Removed in ' . $timeLeft . 's');
                    }
                } elseif ($timeLeft % 10 === 0) {
                    $this->updateNameTag(TextFormat::GREEN . 'Removed in ' . $timeLeft . 's');
                }
            } else {
                $this->setNameTag('');
                $this->setNameTagVisible(false);
                $this->setNameTagAlwaysVisible(false);
            }
        } else {
            $parentTick = parent::entityBaseTick($tickDiff);
        }

        return $parentTick;
    }

    /**
     * Refresh the item entity's age.
     *
     * @return void
     */
    public function refreshAge(): void
    {
        $this->despawnDelay = self::DESPAWN_DELAY;
    }

    public function onDeath(): void
    {
        parent::onDeath();

        $item = $this->getItem();
        if (ItemStorage::hasValidationId($item)) {
            ItemStorage::removeValidationId($item);
        }
    }

    /**
     * Initialize the entity.
     *
     * @param CompoundTag $nbt
     * @return void
     */
    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);

        if ($nbt->getShort("Age", 0) === 0) {
            $this->refreshAge();

            $world = $this->getWorld();
            $item = $this->getItem();

            $bb = $this->boundingBox->expandedCopy(self::STACK_DISTANCE, self::STACK_DISTANCE, self::STACK_DISTANCE);
            $nearbyEntityList = $world->getNearbyEntities($bb, $this);
            foreach ($nearbyEntityList as $otherEntity) {
                if ($otherEntity instanceof self) {
                    $otherItem = $otherEntity->getItem();

                    if (!$otherEntity->isFlaggedForDespawn() && $otherItem->equals($item, true, true)) {
                        $totalCount = $otherItem->getCount() + $item->getCount();

                        if ($totalCount <= $item->getMaxStackSize()) {
                            $otherItem->setCount($totalCount);
                            $otherEntity->refreshAge();

                            if ($totalCount === 2 || $totalCount === 6 || $totalCount === 21) {
                                $otherEntity->respawnToAll();
                            }

                            $this->flagForDespawn();
                        }

                        return;
                    }
                }
            }
        }
    }

    /**
     * Updates the name-tag to a smart name-tag
     * which displays important information
     * about the dropped entity.
     *
     * @param string $timeLeft
     * @return void
     */
    private function updateNameTag(string $timeLeft): void
    {
        foreach (self::$validators as $validator) {
            if (!$validator($this)) {
                return;
            }
        }

        $this->setNameTag(TextFormat::BOLD . TextFormat::GOLD . $this->getItem()->getCount() . 'x' . TextFormat::EOL . $timeLeft);
        $this->setNameTagVisible(true);
        $this->setNameTagAlwaysVisible(true);
    }
}
