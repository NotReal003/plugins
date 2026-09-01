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
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder
 */
declare(strict_types=1);

namespace skyblock\islands\feature\block;

use libVanilla\block\Hopper;
use pocketmine\block\Block;
use pocketmine\block\Flowable;
use skyblock\block\SpawnerBlock;

class BlockCounter
{
    private const PLACEMENT_LIMIT = 0;
    private const VIP_PLACEMENT_LIMIT = 1;

    private const LOGGING_BLOCKS = [
        Hopper::class => [
            self::PLACEMENT_LIMIT => 25,
            self::VIP_PLACEMENT_LIMIT => 50,
        ],
        SpawnerBlock::class => [
            self::PLACEMENT_LIMIT => 15,
            self::VIP_PLACEMENT_LIMIT => 30,
        ],
    ];

    public const TILE_NAME_TO_CLASS_IDENTIFIER = [
        "nethergames:spawner" => SpawnerBlock::class,
        "Hopper" => Hopper::class,
    ];

    /** @var int[] */
    private array $data = [];

    public function setData(array $objects): void
    {
        foreach ($objects as $tileName => $totalBlocks) {
            $tileName = self::TILE_NAME_TO_CLASS_IDENTIFIER[$tileName] ?? null;

            if ($tileName !== null) {
                $this->data[$tileName] = $totalBlocks;
            }
        }
    }

    public static function needLogging(Block $block): bool
    {
        return !$block instanceof Flowable && (isset(self::LOGGING_BLOCKS[$block::class]));
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function addBlock(Block $block, bool $isVIPIsland): bool
    {
        $blockId = $block::class;

        if (isset($this->data[$blockId])) {
            if ($this->data[$blockId] >= self::getLimit($block, $isVIPIsland)) {
                return false;
            }

            $this->data[$blockId]++;
        } else {
            $this->data[$blockId] = 1;
        }

        return true;
    }

    private static function getLimit(Block $block, bool $isVIPIsland): int
    {
        if ($isVIPIsland) {
            return self::LOGGING_BLOCKS[$block::class][self::VIP_PLACEMENT_LIMIT] ?? 50;
        }

        return self::LOGGING_BLOCKS[$block::class][self::PLACEMENT_LIMIT] ?? 25;
    }

    public function removeBlock(Block $block): void
    {
        $blockId = $block::class;

        if (isset($this->data[$blockId])) {
            $this->data[$blockId]--;

            if ($this->data[$blockId] === 0) {
                unset($this->data[$blockId]);
            }
        }
    }
}