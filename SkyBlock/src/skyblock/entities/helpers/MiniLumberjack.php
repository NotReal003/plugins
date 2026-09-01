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

namespace skyblock\entities\helpers;

use libMMO\utils\Utils;
use libPhysX\internal\Rotation;
use libPhysX\PhysX;
use pocketmine\block\Leaves;
use pocketmine\block\Sapling;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\Wood;
use pocketmine\entity\Location;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\player\Player;
use skyblock\islands\Island;

class MiniLumberjack extends MiniHelper
{
    private const BLOCK_REACH_LIMIT = 4;

    /** @var Vector3 */
    private $forward;
    /** @var null|Vector3 */
    private ?Vector3 $next = null;
    /** @var float|int */
    private $heightLevel = 0;

    public function __construct(Location $location, CompoundTag $nbt, ?Island $island = null, ?Player $player = null)
    {
        parent::__construct($location, $nbt, self::LUMBERJACK, $island, $player);

        if ($island === null) {
            /** @var int[] $pos */
            $pos = $nbt->getListTag('ForwardBlock')->getAllValues();
            $this->forward = new Vector3($pos[0], $pos[1], $pos[2]);

            if (Utils::hasTag($nbt, 'NextBlock')) {
                /** @var int[] $pos */
                $pos = $nbt->getListTag('NextBlock')->getAllValues();
                $this->next = new Vector3($pos[0], $pos[1], $pos[2]);

                $this->heightLevel = $this->next->subtractVector($this->forward)->getY();
            }
        } else {
            $this->forward = $this->getPosition()->addVector(PhysX::getRelativeDirectionVector(new Rotation($this->getLocation()->yaw, $this->getLocation()->pitch), PhysX::RELATIVE_DIRECTION_FORWARD));
        }

        $rotation = PhysX::calculateRotationEulerAngle($this->getPosition()->asVector3(), $this->forward);
        $this->getLocation()->yaw = $rotation->yaw;
        $this->getLocation()->pitch = $rotation->pitch;
        $this->broadcastMovement();
    }

    public function saveNBT(): CompoundTag
    {
        $forward = $this->forward->floor();
        $nbt = parent::saveNBT();

        if ($this->next !== null) {
            $next = $this->next->floor();

            $nbt->setTag('NextBlock', new ListTag([
                new IntTag((int)$next->getX()),
                new IntTag((int)$next->getY()),
                new IntTag((int)$next->getZ())
            ]));
        }

        $nbt->setTag('ForwardBlock', new ListTag([
            new IntTag((int)$forward->getX()),
            new IntTag((int)$forward->getY()),
            new IntTag((int)$forward->getZ())
        ]));

        return $nbt;
    }

    /**
     * @inheritDoc
     */
    protected function handleJob(): bool
    {
        if ($this->next === null) {
            $block = $this->getWorld()->getBlock($this->forward);
        } else {
            $block = $this->getWorld()->getBlock($this->next);
        }

        if ($block instanceof Wood || $block instanceof Leaves) {
            if ($this->jobStart) {
                if ($this->jobTick === 0) {
                    $tile = $this->getWorld()->getTile($block->getPosition()->asVector3());
                    if ($tile !== null) {
                        $tile->onBlockDestroyed();
                    }

                    $this->getWorld()->setBlock($block->getPosition()->asVector3(), VanillaBlocks::AIR());

                    $inventory = $this->getHelperMenu()->getInventory();
                    foreach ($block->getDrops($this->item) as $item) {
                        if ($inventory->canAddItem($item)) {
                            $inventory->addItem($item);
                        }
                    }

                    return $this->searchNearBlock();
                }
            } else {
                $jobTime = (int)$this->getBreakTime($this->item, $block);
                $this->jobTick = ($jobTime * 20) + 1;

                $rotation = PhysX::calculateRotationEulerAngle($this->getPosition()->asVector3(), $block->getPosition()->asVector3()->add(0.5, 0, 0.5));
                $this->getLocation()->yaw = $rotation->yaw;
                $this->getLocation()->pitch = $rotation->pitch;
                $this->broadcastMovement();

                $this->jobStart = true;
            }

            $this->sendClientArmAnimation();
            $this->sendCustomAnimation($block);

            $this->jobTick--;
            return false;
        }

        if ($this->next !== null) {
            return $this->searchNearBlock();
        }

        if ($block instanceof Sapling) {
            $bonemeal = VanillaItems::BONE_MEAL();
            $inventory = $this->getHelperMenu()->getInventory();

            if ($inventory->contains($bonemeal)) {
                $inventory->removeItem($bonemeal);
                $block->onInteract($bonemeal, 0, $block->getPosition());//the face and click vector is useless..
                return true;
            }

            return false;
        }

        $this->next = null;
        return true;
    }

    public function searchNearBlock(): bool
    {
        $vector = $this->forward->add(0, $this->heightLevel, 0);
        $this->next = null;

        for ($x = -self::BLOCK_REACH_LIMIT; $x < self::BLOCK_REACH_LIMIT; $x++) {
            for ($z = -self::BLOCK_REACH_LIMIT; $z < self::BLOCK_REACH_LIMIT; $z++) {
                $nextVector = $vector->add($x, 0, $z);
                $nextBlock = $this->getWorld()->getBlock($nextVector);

                if ($nextBlock instanceof Wood || $nextBlock instanceof Leaves) {
                    $this->next = $nextVector;
                }
            }
        }

        if ($this->next === null) {
            $nextVector = $vector->add(0, 1, 0);
            $nextBlock = $this->getWorld()->getBlock($nextVector);

            if (($nextBlock instanceof Wood || $nextBlock instanceof Leaves) && $this->heightLevel < 10) {
                $this->heightLevel++;

                $this->next = $nextVector;
            } else {
                $rotation = PhysX::calculateRotationEulerAngle($this->getPosition()->asVector3(), $this->forward->add(0.5, 0, 0.5));
                $this->getLocation()->yaw = $rotation->yaw;
                $this->getLocation()->pitch = $rotation->pitch;
                $this->broadcastMovement();

                $this->tryPlantTree();

                $this->heightLevel = 0;

                $this->jobStart = false;
                return true;
            }
        }

        $this->jobStart = false;
        return false;
    }

    /**
     * Try to plant a tree from inventory.
     *
     * @return void
     */
    private function tryPlantTree(): void
    {
        $inventory = $this->getHelperMenu()->getInventory();

        foreach ($inventory->getContents() as $slot => $item) {
            if (($block = $item->getBlock()) instanceof Sapling) {
                $item->pop();
                $inventory->setItem($slot, $item);

                $this->getWorld()->setBlock($this->forward, $block);
                return;
            }
        }

        $this->getWorld()->setBlock($this->forward, VanillaBlocks::OAK_SAPLING());
    }
}