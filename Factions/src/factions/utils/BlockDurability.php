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

namespace factions\utils;

use LevelDB;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\World;
use Symfony\Component\Filesystem\Path;

class BlockDurability
{
    public const BEDROCK_DURABILITY = 30;
    public const OBSIDIAN_DURABILITY = 15;

    private static ?LevelDB $levelDb = null;

    public static function init(World $world, string $saveLocation): void
    {
        self::$levelDb = new LevelDB(Path::join($saveLocation, $world->getFolderName(), "block-data"));
    }

    public static function close(): void
    {
        self::$levelDb = null;
    }

    public static function getDurability(Block $block): array
    {
        $blockPos = $block->getPosition();

        $durability = self::get(World::blockHash($blockPos->getX(), $blockPos->getY(), $blockPos->getZ()));
        if ($block->getTypeId() === VanillaBlocks::BEDROCK()->getTypeId()) {
            return [self::BEDROCK_DURABILITY, $durability];
        } elseif ($block->getTypeId() === VanillaBlocks::OBSIDIAN()->getTypeId()) {
            return [self::OBSIDIAN_DURABILITY, $durability];
        } else {
            return [0, 0];
        }
    }

    /**
     * @param int $key
     * @return int
     */
    public static function get(int $key): int
    {
        return (int)self::$levelDb?->get((string)$key);
    }

    /**
     * @param int $key
     * @param Block $blockId
     * @return bool
     */
    public static function checkHash(int $key, Block $blockId): bool
    {
        if (($durability = self::get($key)) > 0) {
            if ($blockId->getTypeId() === VanillaBlocks::BEDROCK()->getTypeId() && $durability >= self::BEDROCK_DURABILITY) {
                self::remove($key);
            } elseif ($blockId->getTypeId() === VanillaBlocks::OBSIDIAN()->getTypeId() && $durability >= self::OBSIDIAN_DURABILITY) {
                self::remove($key);
            } else {
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * @param int $key
     */
    public static function remove(int $key): void
    {
        self::$levelDb->delete((string)$key);
    }

    /**
     * @param int $key
     */
    public static function addCount(int $key): void
    {
        self::add($key, self::get($key) + 1);
    }

    /**
     * @param int $key
     * @param int $durability
     */
    public static function add(int $key, int $durability = 0): void
    {
        self::$levelDb->put((string)$key, (string)$durability);
    }
}