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
 * Copyright (C) 2016-2024 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author CortexPE
 */

namespace skyblock\task;

use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;
use pocketmine\world\particle\RedstoneParticle;
use pocketmine\world\World;
use skyblock\islands\Island;

class IslandBorderTask extends Task
{

    private const BORDER_DISPLAY_DISTANCE = 7;

    public function __construct(
        private readonly World  $world,
        private readonly Island $island
    )
    {
    }

    public function onRun(): void
    {
        if (!$this->world->isLoaded()) {
            $this->getHandler()->cancel();
            return;
        }

        $xpLevelSpec = $this->island->getXpLevelSpec();
        $squareRadius = $xpLevelSpec->getAreaLengthWidth() / 2;
        $spawnPoint = $this->world->getSpawnLocation();

        $borderMinX = (int)floor($spawnPoint->x - ($squareRadius + 0));
        $borderMaxX = (int)floor($spawnPoint->x + ($squareRadius + 1));
        $borderMinZ = (int)floor($spawnPoint->z - ($squareRadius + 1));
        $borderMaxZ = (int)floor($spawnPoint->z + ($squareRadius + 1));
        foreach ($this->world->getPlayers() as $player) {
            $playerLocation = $player->getLocation();
            if (
                ($squareRadius - abs($playerLocation->x - $spawnPoint->x)) > self::BORDER_DISPLAY_DISTANCE &&
                ($squareRadius - abs($playerLocation->z - $spawnPoint->z)) > self::BORDER_DISPLAY_DISTANCE
            ) {
                // player is too far from the border
                continue;
            }

            if (!$xpLevelSpec->isAllowedArea(
                $spawnPoint,
                $playerLocation->subtractVector(
                    // let the user venture a bit into the border
                    $playerLocation->subtractVector($spawnPoint)->normalize()
                )
            )) {
                $mid = clone $spawnPoint;
                $mid->y = $playerLocation->y;
                $player->setMotion(
                    $playerLocation->subtractVector($mid)
                        ->normalize()
                        ->multiply(-0.375)
                );
                $areaLW = $xpLevelSpec->getAreaLengthWidth();
                $player->sendPopup(TextFormat::RED . "You are restricted to a $areaLW x $areaLW area on your island. Upgrade your island to get more space!");
            }

            $startX = max($playerLocation->getFloorX() - self::BORDER_DISPLAY_DISTANCE, $borderMinX);
            $endX = min($playerLocation->getFloorX() + self::BORDER_DISPLAY_DISTANCE, $borderMaxX);
            $startZ = max($playerLocation->getFloorZ() - self::BORDER_DISPLAY_DISTANCE, $borderMinZ);
            $endZ = min($playerLocation->getFloorZ() + self::BORDER_DISPLAY_DISTANCE, $borderMaxZ);
            $startY = $playerLocation->getFloorY() - self::BORDER_DISPLAY_DISTANCE;
            $endY = $playerLocation->getFloorY() + self::BORDER_DISPLAY_DISTANCE;
            for ($currentX = $startX; $currentX <= $endX; $currentX++) {
                for ($currentZ = $startZ; $currentZ <= $endZ; $currentZ++) {
                    if (
                        ($currentX != $borderMinX && $currentX != $borderMaxX) &&
                        ($currentZ != $borderMinZ && $currentZ != $borderMaxZ)
                    ) {
                        continue;
                    }
                    for ($currentY = $startY; $currentY <= $endY; $currentY++) {
                        $this->world->addParticle(
                            new Vector3($currentX + 0.5, $currentY + 0.5, $currentZ + 0.5),
                            new RedstoneParticle()
                        );
                    }
                }
            }
        }
    }
}