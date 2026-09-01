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

use libPhysX\internal\Rotation;
use libPhysX\PhysX;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\player\Player;
use skyblock\islands\Island;

class MiniMiner extends MiniHelper
{
    public const NBT_FORWARD_BLOCK = 'ForwardBlock';

    /** @var Vector3 */
    private $forward;

    public function __construct(Location $location, CompoundTag $nbt, ?Island $island = null, ?Player $player = null)
    {
        parent::__construct($location, $nbt, self::MINER, $island, $player);

        if ($island === null) {
            /** @var int[] $pos */
            $pos = $nbt->getListTag(self::NBT_FORWARD_BLOCK)->getAllValues();
            $this->forward = new Vector3($pos[0], $pos[1], $pos[2]);
        } else {
            $this->forward = $this->getPosition()->addVector(PhysX::getRelativeDirectionVector(new Rotation($this->getLocation()->getYaw(), $this->getLocation()->getPitch()), PhysX::RELATIVE_DIRECTION_FORWARD));

            $rotation = PhysX::calculateRotationEulerAngle($this->getPosition()->asVector3(), $this->forward);
            $this->getLocation()->yaw = $rotation->yaw;
            $this->getLocation()->pitch = $rotation->pitch;
            $this->broadcastMovement();
        }
    }

    public function saveNBT(): CompoundTag
    {
        $nbt = parent::saveNBT();
        $forward = $this->forward->floor();

        $nbt->setTag(self::NBT_FORWARD_BLOCK, new ListTag([
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
        $forwardBlock = $this->getWorld()->getBlock($this->forward);

        if ($forwardBlock->isSolid()) {
            if ($this->jobStart) {
                if ($this->jobTick === 0) {
                    $tile = $this->getWorld()->getTile($this->forward);
                    if ($tile !== null) {
                        $tile->onBlockDestroyed();
                    }

                    $this->getWorld()->setBlock($this->forward, VanillaBlocks::AIR());

                    $inventory = $this->getHelperMenu()->getInventory();
                    foreach ($forwardBlock->getDrops($this->item) as $item) {
                        if ($inventory->canAddItem($item)) {
                            $inventory->addItem($item);
                        }
                    }

                    $this->jobStart = false;
                    return true;
                }
            } else {
                $jobTime = (int)$this->getBreakTime($this->item, $forwardBlock);
                $this->jobTick = ($jobTime * 20) + 1;
                $this->jobStart = true;
            }

            $this->sendClientArmAnimation();
            $this->sendCustomAnimation($forwardBlock);

            $this->jobTick--;
            return false;
        }

        return false;
    }
}