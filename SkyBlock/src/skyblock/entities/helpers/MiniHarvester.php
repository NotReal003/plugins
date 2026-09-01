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

use libPhysX\PhysX;
use libPhysX\utility\MathX;
use pocketmine\block\Air;
use pocketmine\block\Beetroot;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Crops;
use pocketmine\block\Farmland;
use pocketmine\block\Melon;
use pocketmine\block\MelonStem;
use pocketmine\block\NetherWartPlant;
use pocketmine\block\PumpkinStem;
use pocketmine\block\SoulSand;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\Wheat;
use pocketmine\entity\Location;
use pocketmine\item\BeetrootSeeds;
use pocketmine\item\Carrot;
use pocketmine\item\Item;
use pocketmine\item\Potato;
use pocketmine\item\VanillaItems;
use pocketmine\item\WheatSeeds;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use skyblock\islands\Island;
use function count;

class MiniHarvester extends MiniHelper
{
    /** @var int */
    private int $cropCheckpoint = 0;
    /** @var Vector3[] */
    private array $cropList = [];
    /** @var Item|null */
    private ?Item $seed = null;

    public function __construct(Location $location, CompoundTag $nbt, ?Island $island = null, ?Player $player = null)
    {
        parent::__construct($location, $nbt, self::HARVESTER, $island, $player);

        $this->calculateCropList();
    }

    /**
     * Calculate the list of crops we have to consider.
     *
     * @return void
     */
    private function calculateCropList(): void
    {
        $this->cropList = [];
        $this->cropCheckpoint = 0;

        $accurateCircle = MathX::calculateFilledCircleXZ(new Vector3($this->getPosition()->x, $this->getPosition()->y, $this->getPosition()->z), 8);
        foreach ($accurateCircle as $accuratePoint) {
            $crop = $this->getWorld()->getBlock($accuratePoint);

            if ($this->isCrop($crop)) {
                $this->cropList[] = $crop->getPosition()->asVector3();
            }
        }
    }

    public function isCrop(Block $crop): bool
    {
        return ($crop instanceof Crops && !$crop instanceof MelonStem && !$crop instanceof PumpkinStem) || ($crop->getTypeId() === BlockTypeIds::PUMPKIN) || $crop instanceof Melon || $crop instanceof NetherWartPlant;
    }

    /**
     * @inheritDoc
     */
    protected function handleJob(): bool
    {
        if (count($this->cropList) > 0) {
            $crop = $this->getWorld()->getBlock($this->cropList[$this->cropCheckpoint]);
            $inventory = $this->getHelperMenu()->getInventory();

            if ($crop instanceof Air) {
                if ($this->seed === null) {
                    $this->jobStart = false;
                    $this->nextCheckpoint();
                    return false;
                }
            } elseif (!$this->isCrop($crop)) {
                $this->jobStart = false;
                $this->nextCheckpoint();
                return false;
            }

            if ($this->jobStart) {
                if ($this->jobTick === 0) {
                    $tile = $this->getWorld()->getTile($crop->getPosition());
                    if ($tile !== null) {
                        $tile->onBlockDestroyed();
                    }

                    $this->getWorld()->setBlock($crop->getPosition(), VanillaBlocks::AIR());

                    $inventory = $this->getHelperMenu()->getInventory();
                    foreach ($crop->getDrops($this->item) as $item) {
                        if ($inventory->canAddItem($item)) {
                            $inventory->addItem($item);
                        }

                        if ($item instanceof WheatSeeds || $item instanceof BeetrootSeeds || $item instanceof Carrot || $item instanceof Potato || $item->getBlock()->getTypeId() === BlockTypeIds::NETHER_WART) {
                            $seed = clone $item;
                            $this->seed = $seed->setCount(1);
                        }
                    }

                    if ($this->seed === null) {
                        $this->jobStart = false;
                        $this->nextCheckpoint();
                        return true;
                    }
                } elseif ($this->jobTick === -20) {
                    $blockBelow = $this->getWorld()->getBlock($crop->getPosition()->addVector(new Vector3(0, -1, 0)));

                    if ($blockBelow instanceof Farmland || $blockBelow instanceof SoulSand) {
                        $inventory = $this->getHelperMenu()->getInventory();
                        if ($inventory->contains($this->seed)) {
                            $inventory->removeItem($this->seed);
                        }

                        $this->getWorld()->setBlock($crop->getPosition(), $this->seed->getBlock(), true);
                    }

                    $this->seed = null;

                    $this->jobStart = false;
                    $this->nextCheckpoint();

                    $this->sendClientArmAnimation();
                    return true;
                } else {
                    $this->sendClientArmAnimation();
                    $this->sendCustomAnimation($crop);
                }

                $this->jobTick--;
            } elseif ($this->isFullGrown($crop)) {
                $jobTime = (int)$this->getBreakTime($this->item, $crop);
                $this->jobTick = ($jobTime * 20) + 1;

                $rotation = PhysX::calculateRotationEulerAngle($this->getPosition()->asVector3(), $crop->getPosition()->add(0.5, 0, 0.5));
                $this->getLocation()->yaw = $rotation->yaw;
                $this->getLocation()->pitch = $rotation->pitch;
                $this->broadcastMovement();

                $this->sendClientArmAnimation();
                $this->sendCustomAnimation($crop);
                $this->jobStart = true;
            } elseif ($inventory->contains($bonemeal = VanillaItems::BONE_MEAL())) {
                $inventory->removeItem($bonemeal);
                $crop->onInteract($bonemeal, 0, $crop->getPosition());

                $rotation = PhysX::calculateRotationEulerAngle($this->getPosition()->asVector3(), $crop->getPosition()->add(0.5, 0, 0.5));
                $this->getLocation()->yaw = $rotation->yaw;
                $this->getLocation()->pitch = $rotation->pitch;
                $this->broadcastMovement();

                return true;
            } else {
                $this->nextCheckpoint();
            }

            return false;
        }

        $this->calculateCropList();
        return false;
    }

    /**
     * Go to next checkpoint.
     *
     * @return void
     */
    private function nextCheckpoint(): void
    {
        $this->cropCheckpoint++;

        if (count($this->cropList) === $this->cropCheckpoint) {
            $this->calculateCropList();
        }
    }

    public function isFullGrown(Block $block): bool
    {
        if (($block instanceof Wheat || $block instanceof \pocketmine\block\Potato || $block instanceof \pocketmine\block\Carrot || $block instanceof Beetroot) && $block->getAge() >= $block::MAX_AGE) {
            return true;
        }

        if ($block->getTypeId() === BlockTypeIds::PUMPKIN || $block instanceof Melon) {
            return true;
        }

        if ($block instanceof NetherWartPlant && $block->getAge() >= $block::MAX_AGE) {
            return true;
        }

        return false;
    }
}