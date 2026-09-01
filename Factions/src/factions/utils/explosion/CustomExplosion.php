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

namespace factions\utils\explosion;

use factions\utils\BlockDurability;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Liquid;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\entity\Entity;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\Explosion;
use pocketmine\world\format\SubChunk;
use pocketmine\world\Position;
use pocketmine\world\utils\SubChunkExplorer;
use pocketmine\world\utils\SubChunkExplorerStatus;
use pocketmine\world\World;

class CustomExplosion extends Explosion
{
    /** @var int */
    private int $rays = 16;
    /** @var SubChunkExplorer */
    private SubChunkExplorer $subChunkExplorer;
    /** @var true[] */
    private array $loggedBlocks = [];
    /** @var bool */
    private bool $worksUnderwater;

    /**
     * @param Position $center
     * @param bool $worksUnderwater
     * @param float $size
     * @param Entity|Block|null $what
     */
    public function __construct(Position $center, bool $worksUnderwater, float $size, Entity|Block|null $what = null)
    {
        parent::__construct($center, $size, $what);

        $this->worksUnderwater = $worksUnderwater;
        $this->subChunkExplorer = new SubChunkExplorer($this->world);
    }

    /**
     * Calculates which blocks will be destroyed by this explosion. If explodeB() is called without calling this, no blocks
     * will be destroyed.
     */
    public function explodeA(): bool
    {
        if ($this->radius < 0.1) {
            return false;
        }

        $blockFactory = RuntimeBlockStateRegistry::getInstance();
        $blockBreaking = $this->worksUnderwater && mt_rand(0, 2) === 1;

        $mRays = $this->rays - 1;
        for ($i = 0; $i < $this->rays; ++$i) {
            for ($j = 0; $j < $this->rays; ++$j) {
                for ($k = 0; $k < $this->rays; ++$k) {
                    if ($i === 0 || $i === $mRays || $j === 0 || $j === $mRays || $k === 0 || $k === $mRays) {
                        //this could be written as new Vector3(...)->normalize()->multiply(stepLen), but we're avoiding Vector3 for performance here
                        [$shiftX, $shiftY, $shiftZ] = [$i / $mRays * 2 - 1, $j / $mRays * 2 - 1, $k / $mRays * 2 - 1];
                        $len = sqrt($shiftX ** 2 + $shiftY ** 2 + $shiftZ ** 2);
                        [$shiftX, $shiftY, $shiftZ] = [($shiftX / $len) * $this->stepLen, ($shiftY / $len) * $this->stepLen, ($shiftZ / $len) * $this->stepLen];
                        $pointerX = $this->source->x;
                        $pointerY = $this->source->y;
                        $pointerZ = $this->source->z;

                        for ($blastForce = $this->radius * (mt_rand(700, 1300) / 1000); $blastForce > 0; $blastForce -= $this->stepLen * 0.75) {
                            $x = (int)$pointerX;
                            $y = (int)$pointerY;
                            $z = (int)$pointerZ;
                            $vBlockX = $pointerX >= $x ? $x : $x - 1;
                            $vBlockY = $pointerY >= $y ? $y : $y - 1;
                            $vBlockZ = $pointerZ >= $z ? $z : $z - 1;

                            $pointerX += $shiftX;
                            $pointerY += $shiftY;
                            $pointerZ += $shiftZ;

                            if ($this->subChunkExplorer->moveTo($vBlockX, $vBlockY, $vBlockZ) === SubChunkExplorerStatus::INVALID) {
                                continue;
                            }
                            $subChunk = $this->subChunkExplorer->currentSubChunk;
                            if ($subChunk === null) {
                                throw new AssumptionFailedError("SubChunkExplorer subchunk should not be null here");
                            }

                            $state = $subChunk->getBlockStateId($vBlockX & SubChunk::COORD_MASK, $vBlockY & SubChunk::COORD_MASK, $vBlockZ & SubChunk::COORD_MASK);

                            if ($state !== BlockTypeIds::AIR) {
                                $_block = RuntimeBlockStateRegistry::getInstance()->fromStateId($state);

                                $index = World::blockHash($vBlockX, $vBlockY, $vBlockZ);
                                if (($_block->getTypeId() === BlockTypeIds::BEDROCK || $_block->getTypeId() === BlockTypeIds::OBSIDIAN) && !isset($this->loggedBlocks[$index])) {
                                    $this->loggedBlocks[$index] = true;

                                    BlockDurability::addCount($index);
                                    if (BlockDurability::checkHash($index, $_block)) {
                                        $_block->position($this->world, $vBlockX, $vBlockY, $vBlockZ);
                                        $this->affectedBlocks[World::blockHash($vBlockX, $vBlockY, $vBlockZ)] = $_block;
                                    }
                                }
                            }

                            $blastResistance = $blockFactory->blastResistance[$state] ?? 0;
                            if ($blastResistance >= 0) {
                                $blastResistance = ($blastResistance > 450 && $blockBreaking) ? 0 : $blastResistance;
                                $blastForce -= ($blastResistance / 5 + 0.3) * $this->stepLen;
                                if ($blastForce > 0 && !isset($this->affectedBlocks[World::blockHash($vBlockX, $vBlockY, $vBlockZ)])) {
                                    $_block = $this->world->getBlockAt($vBlockX, $vBlockY, $vBlockZ, true, false);
                                    foreach ($_block->getAffectedBlocks() as $_affectedBlock) {
                                        if ($_affectedBlock instanceof Liquid) {
                                            continue;
                                        }

                                        $_affectedBlockPos = $_affectedBlock->getPosition();
                                        $this->affectedBlocks[World::blockHash($_affectedBlockPos->x, $_affectedBlockPos->y, $_affectedBlockPos->z)] = $_affectedBlock;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return true;
    }
}