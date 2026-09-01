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

namespace skyblock\islands\feature\elevator;

use pocketmine\block\BlockTypeIds;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJumpEvent;
use pocketmine\event\player\PlayerToggleSneakEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;

class Elevator implements Listener
{
    private const MAX_HEIGHT_DELTA = 10;
    private const OFFSET_HORIZONTAL = 0.5;

    public const TRIGGER_CAUSE_JUMP = 0;
    public const TRIGGER_CAUSE_SNEAK = 1;

    /**
     * @param PlayerJumpEvent $event
     * @priority NORMAL
     */
    public function onPlayerJump(PlayerJumpEvent $event): void
    {
        $player = $event->getPlayer();

        self::useElevator($player, self::TRIGGER_CAUSE_JUMP);
    }

    /**
     * @param PlayerToggleSneakEvent $event
     * @priority NORMAL
     */
    public function onPlayerSneak(PlayerToggleSneakEvent $event): void
    {
        $player = $event->getPlayer();

        if ($event->isSneaking()) {
            self::useElevator($player, self::TRIGGER_CAUSE_SNEAK);
        }
    }

    /**
     * Uses the elevator but makes sure all conditions have been met.
     * Safe to call without any previous trigger constraints.
     *
     * @param Player $player
     * @param int $triggerCause
     */
    public static function useElevator(Player $player, int $triggerCause): void
    {
        $floor = $player->getPosition()->floor();
        $world = $player->getWorld();

        if (self::checkIfFloorIsElevatorFloor($world, $floor)) {
            switch ($triggerCause) {
                case self::TRIGGER_CAUSE_JUMP:
                    $aboveFloor = self::getAboveElevatorFloor($world, $floor);
                    if ($aboveFloor !== null) {
                        $newPosition = new Vector3($aboveFloor->getX() + self::OFFSET_HORIZONTAL, $aboveFloor->getY() + 1, $aboveFloor->getZ() + self::OFFSET_HORIZONTAL);
                        $player->teleport($newPosition, $player->getLocation()->getYaw(), $player->getLocation()->getPitch());
                    }
                    break;
                case self::TRIGGER_CAUSE_SNEAK:
                    $belowFloor = self::getBelowElevatorFloor($world, $floor);
                    if ($belowFloor !== null) {
                        $newPosition = new Vector3($belowFloor->getX() + self::OFFSET_HORIZONTAL, $belowFloor->getY() + 1, $belowFloor->getZ() + self::OFFSET_HORIZONTAL);
                        $player->teleport($newPosition, $player->getLocation()->getYaw(), $player->getLocation()->getPitch());
                    }
                    break;
            }
        }
    }

    /**
     * Checks if the floor is an elevator floor.
     *
     * @param World $world
     * @param Vector3 $floor
     * @return bool
     */
    private static function checkIfFloorIsElevatorFloor(World $world, Vector3 $floor): bool
    {
        return $world->getBlockAt((int)$floor->getX(), (int)$floor->getY(), (int)$floor->getZ())->getTypeId() === BlockTypeIds::WEIGHTED_PRESSURE_PLATE_HEAVY;
    }

    /**
     * Get the above elevator floor.
     * Returns null if doesn't exist.
     *
     * @param World $world
     * @param Vector3 $currentFloor
     * @return Vector3|null
     */
    private static function getAboveElevatorFloor(World $world, Vector3 $currentFloor): ?Vector3
    {
        for ($y = $currentFloor->getY() + 2; $y <= $currentFloor->getY() + self::MAX_HEIGHT_DELTA; $y++) {
            $vector = new Vector3($currentFloor->getX(), $y, $currentFloor->getZ());

            if ($world->getBlockAt((int)$vector->getX(), (int)$vector->getY(), (int)$vector->getZ())->getTypeId() === BlockTypeIds::IRON) {
                return $vector;
            }
        }

        return null;
    }

    /**
     * Get the below elevator floor.
     * Returns null if doesn't exist.
     *
     * @param World $world
     * @param Vector3 $currentFloor
     * @return Vector3|null
     */
    private static function getBelowElevatorFloor(World $world, Vector3 $currentFloor): ?Vector3
    {
        for ($y = $currentFloor->getY() - 2; $y >= $currentFloor->getY() - self::MAX_HEIGHT_DELTA; $y--) {
            $vector = new Vector3($currentFloor->getX(), $y, $currentFloor->getZ());

            if ($world->getBlockAt((int)$vector->getX(), (int)$vector->getY(), (int)$vector->getZ())->getTypeId() === BlockTypeIds::IRON) {
                return $vector;
            }
        }

        return null;
    }

}