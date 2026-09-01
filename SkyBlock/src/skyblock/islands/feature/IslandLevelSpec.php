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

namespace skyblock\islands\feature;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use skyblock\islands\feature\block\BlockToWeightPair;
use skyblock\islands\feature\block\CobbleGenWeightTable;

/**
 * Contains information about island levels, such as their cost and what can be done on each level.
 */
final class IslandLevelSpec
{
    /** @var IslandLevelSpec[]|null */
    private static ?array $levels = null;

    /** @var int */
    private int $id;
    /** @var int */
    private int $price;
    /** @var int */
    private int $areaLengthWidth;
    /** @var CobbleGenWeightTable */
    private CobbleGenWeightTable $cobbleWeightTable;

    private function __construct(int $id, int $price, int $areaLengthWidth, CobbleGenWeightTable $cobbleWeightTable)
    {
        $this->id = $id;
        $this->price = $price;
        $this->areaLengthWidth = $areaLengthWidth;
        $this->cobbleWeightTable = $cobbleWeightTable;
    }

    /**
     * @return int
     */
    public function getPrice(): int
    {
        return $this->price;
    }

    /**
     * @return int
     */
    public function getAreaLengthWidth(): int
    {
        return $this->areaLengthWidth;
    }

    public function getCobbleWeightTable(): CobbleGenWeightTable
    {
        return $this->cobbleWeightTable;
    }

    public function getNextLevel(): ?IslandLevelSpec
    {
        return self::get($this->id + 1);
    }

    public static function get(int $level): ?IslandLevelSpec
    {
        return (self::$levels ?? self::init())[$level] ?? null;
    }

    /**
     * @return IslandLevelSpec[]
     */
    private static function init(): array
    {
        self::$levels = [];

        foreach (
            [
                new IslandLevelSpec(1, 0, 25, new CobbleGenWeightTable([
                    new BlockToWeightPair(VanillaBlocks::COAL_ORE(), 5),
                    new BlockToWeightPair(VanillaBlocks::IRON_ORE(), 2),
                    new BlockToWeightPair(VanillaBlocks::GOLD_ORE(), 1),
                    new BlockToWeightPair(VanillaBlocks::REDSTONE_ORE(), 1)
                ])),
                new IslandLevelSpec(2, 4 ** 2 * 100, 50, new CobbleGenWeightTable([
                    new BlockToWeightPair(VanillaBlocks::COAL_ORE(), 10),
                    new BlockToWeightPair(VanillaBlocks::IRON_ORE(), 4),
                    new BlockToWeightPair(VanillaBlocks::GOLD_ORE(), 3),
                    new BlockToWeightPair(VanillaBlocks::REDSTONE_ORE(), 2)
                ])),
                new IslandLevelSpec(3, 4 ** 3 * 100, 75, new CobbleGenWeightTable([
                    new BlockToWeightPair(VanillaBlocks::COAL_ORE(), 15),
                    new BlockToWeightPair(VanillaBlocks::IRON_ORE(), 6),
                    new BlockToWeightPair(VanillaBlocks::GOLD_ORE(), 4),
                    new BlockToWeightPair(VanillaBlocks::REDSTONE_ORE(), 3)
                ])),
                new IslandLevelSpec(4, 4 ** 4 * 100, 100, new CobbleGenWeightTable([
                    new BlockToWeightPair(VanillaBlocks::COAL_ORE(), 20),
                    new BlockToWeightPair(VanillaBlocks::IRON_ORE(), 8),
                    new BlockToWeightPair(VanillaBlocks::GOLD_ORE(), 6),
                    new BlockToWeightPair(VanillaBlocks::REDSTONE_ORE(), 4)
                ])),
                new IslandLevelSpec(5, 4 ** 5 * 100, 125, new CobbleGenWeightTable([
                    new BlockToWeightPair(VanillaBlocks::COAL_ORE(), 25),
                    new BlockToWeightPair(VanillaBlocks::IRON_ORE(), 10),
                    new BlockToWeightPair(VanillaBlocks::GOLD_ORE(), 8),
                    new BlockToWeightPair(VanillaBlocks::REDSTONE_ORE(), 5),
                    new BlockToWeightPair(VanillaBlocks::DIAMOND_ORE(), 3)
                ])),
                new IslandLevelSpec(6, 4 ** 6 * 100, 150, new CobbleGenWeightTable([
                    new BlockToWeightPair(VanillaBlocks::COAL_ORE(), 25),
                    new BlockToWeightPair(VanillaBlocks::IRON_ORE(), 13),
                    new BlockToWeightPair(VanillaBlocks::GOLD_ORE(), 10),
                    new BlockToWeightPair(VanillaBlocks::REDSTONE_ORE(), 6),
                    new BlockToWeightPair(VanillaBlocks::DIAMOND_ORE(), 4)
                ])),
                new IslandLevelSpec(7, 4 ** 7 * 100, 175, new CobbleGenWeightTable([
                    new BlockToWeightPair(VanillaBlocks::COAL_ORE(), 25),
                    new BlockToWeightPair(VanillaBlocks::IRON_ORE(), 15),
                    new BlockToWeightPair(VanillaBlocks::GOLD_ORE(), 10),
                    new BlockToWeightPair(VanillaBlocks::REDSTONE_ORE(), 7),
                    new BlockToWeightPair(VanillaBlocks::DIAMOND_ORE(), 5)
                ])),
                new IslandLevelSpec(8, 4 ** 8 * 100, 200, new CobbleGenWeightTable([
                    new BlockToWeightPair(VanillaBlocks::COAL_ORE(), 20),
                    new BlockToWeightPair(VanillaBlocks::IRON_ORE(), 20),
                    new BlockToWeightPair(VanillaBlocks::GOLD_ORE(), 15),
                    new BlockToWeightPair(VanillaBlocks::REDSTONE_ORE(), 10),
                    new BlockToWeightPair(VanillaBlocks::DIAMOND_ORE(), 8)
                ])),
                new IslandLevelSpec(9, 4 ** 9 * 100, 225, new CobbleGenWeightTable([
                    new BlockToWeightPair(VanillaBlocks::COAL_ORE(), 15),
                    new BlockToWeightPair(VanillaBlocks::IRON_ORE(), 24),
                    new BlockToWeightPair(VanillaBlocks::GOLD_ORE(), 18),
                    new BlockToWeightPair(VanillaBlocks::REDSTONE_ORE(), 13),
                    new BlockToWeightPair(VanillaBlocks::DIAMOND_ORE(), 11)
                ])),
                new IslandLevelSpec(10, 4 ** 10 * 100, 250, new CobbleGenWeightTable([
                    new BlockToWeightPair(VanillaBlocks::COAL_ORE(), 10),
                    new BlockToWeightPair(VanillaBlocks::IRON_ORE(), 24),
                    new BlockToWeightPair(VanillaBlocks::GOLD_ORE(), 24),
                    new BlockToWeightPair(VanillaBlocks::REDSTONE_ORE(), 20),
                    new BlockToWeightPair(VanillaBlocks::DIAMOND_ORE(), 15)
                ])),
                new IslandLevelSpec(11, 4 ** 11 * 100, 275, new CobbleGenWeightTable([
                    new BlockToWeightPair(VanillaBlocks::COAL_ORE(), 5),
                    new BlockToWeightPair(VanillaBlocks::IRON_ORE(), 10),
                    new BlockToWeightPair(VanillaBlocks::GOLD_ORE(), 10),
                    new BlockToWeightPair(VanillaBlocks::REDSTONE_ORE(), 25),
                    new BlockToWeightPair(VanillaBlocks::DIAMOND_ORE(), 20)
                ])),
            ] as $levelSpec) {
            self::$levels[$levelSpec->getId()] = $levelSpec;
        }

        return self::$levels;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    public function isAllowedArea(Vector3 $spawn, Vector3 $target): bool
    {
        $squareRadius = $this->areaLengthWidth / 2;
        return abs($target->x - $spawn->x) <= $squareRadius && abs($target->z - $spawn->z) <= $squareRadius;
    }
}