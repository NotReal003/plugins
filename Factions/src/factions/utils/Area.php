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

use factions\faction\claims\ClaimManager;
use factions\Factions;
use factions\koth\KothListener;
use pocketmine\entity\Location;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;

class Area
{
    private const WARZONE_AREA_SIZE = 550;

    /** @var array|null */
    private static ?array $midpointAreas = null;

    public static function inPvpArea(Player $player): bool
    {
        $pos = $player->getPosition();

        $world = $player->getWorld();

        $wm = $player->getServer()->getWorldManager();
        $wild = $wm->getWorldByName('wild');

        /** @var AxisAlignedBB|null $safeAreaPvp */
        $safeAreaPvp = self::$midpointAreas['pvp'] ?? null;
        /** @var AxisAlignedBB|null $safeAreaWild */
        $safeAreaWild = self::$midpointAreas['safezone'] ?? null;

        $isBadlands = Factions::isBadlands();
        if ($isBadlands) {
            return $safeAreaPvp !== null && !$safeAreaPvp->isVectorInside($pos);
        } else if ($wild !== null && $world->getId() === $wild->getId() && $safeAreaWild !== null && !$safeAreaWild->isVectorInside($pos)) {
            return true;
        } else if ($world->getFolderName() === KothListener::KOTH_FOLDER_NAME) {
            return true;
        }

        return false;
    }

    public static function inKothArea(Player $player): bool
    {
        $pos = $player->getPosition();

        /** @var Vector2 $koth */
        $koth = self::$midpointAreas['koth'];

        $distance = $koth->distance(new Vector2($pos->getFloorX(), $pos->getFloorZ()));
        return $distance <= 10 && ($pos->getFloorY() < 70 && $pos->getFloorY() > 60); // 60-70
    }

    public static function isAreaInside(Position $pos, string $key = 'warzone'): bool
    {
        /** @var AxisAlignedBB|null $warzone */
        $warzone = self::$midpointAreas[$key] ?? null;

        return $warzone !== null && ($key === 'warzone' ? $warzone->isVectorInXZ($pos) : $warzone->isVectorInside($pos));
    }

    public static function isChunkInWarzone(int $chunkX, int $chunkZ): bool
    {
        /** @var AxisAlignedBB|null $warzone */
        $warzone = self::$midpointAreas['warzone_chunk'] ?? null;

        return $warzone !== null && $warzone->isVectorInXZ(new Vector3($chunkX, 0, $chunkZ));
    }

    public static function setWarzoneSafezone(): void
    {
        $pos = Factions::getSpawnLocation();

        $x = $pos->getFloorX();
        $y = $pos->getFloorY();
        $z = $pos->getFloorZ();

        if (Factions::isBadlands()) {
            $minX = min($x + 15, $x - 15);
            $minY = min($y + 6, $y - 6);
            $minZ = min($z + 15, $z - 15);

            $maxX = max($x + 15, $x - 15);
            $maxY = max($y + 6, $y - 6);
            $maxZ = max($z + 15, $z - 15);

            self::$midpointAreas['pvp'] = new AxisAlignedBB($minX, $minY, $minZ, $maxX, $maxY, $maxZ);
        } else {
            // Safe Region 1: Vector3(x=-192,y=97,z=135)
            // Safe Region 2: Vector3(x=186,y=-54,z=-174)

            // Safezone area, this is no longer hardcoded and based on the location of the spawnpoint that is set
            $minX = min($x + 186, $x - 192);
            $minY = min($y + 500, $y - 28);
            $minZ = min($z + 135, $z - 174);

            $maxX = max($x + 186, $x - 192);
            $maxY = max($y + 500, $y - 28);
            $maxZ = max($z + 135, $z - 174);

            self::$midpointAreas['safezone'] = new AxisAlignedBB($minX, $minY, $minZ, $maxX, $maxY, $maxZ);

            $minX = min($x + self::WARZONE_AREA_SIZE, $x - self::WARZONE_AREA_SIZE);
            $minY = max(min($y + self::WARZONE_AREA_SIZE, $y - self::WARZONE_AREA_SIZE), 0);
            $minZ = min($z + self::WARZONE_AREA_SIZE, $z - self::WARZONE_AREA_SIZE);

            $maxX = max($x + self::WARZONE_AREA_SIZE, $x - self::WARZONE_AREA_SIZE);
            $maxY = min(max($y + self::WARZONE_AREA_SIZE, $y - self::WARZONE_AREA_SIZE), 255);
            $maxZ = max($z + self::WARZONE_AREA_SIZE, $z - self::WARZONE_AREA_SIZE);

            ClaimManager::positionToChunk(new Vector3($minX, 0, $minZ), $minChunkX, $minChunkZ);
            ClaimManager::positionToChunk(new Vector3($maxX, 0, $maxZ), $maxChunkX, $maxChunkZ);

            self::$midpointAreas['warzone'] = new AxisAlignedBB($minX, $minY, $minZ, $maxX, $maxY, $maxZ);
            self::$midpointAreas['warzone_chunk'] = new AxisAlignedBB($minChunkX, $minY, $minChunkZ, $maxChunkX, $maxY, $maxChunkZ);
        }
    }

    public static function addVectorToLocation(Vector3 $position, ?float $yaw = null, ?float $pitch = null): Location
    {
        $location = Factions::getSpawnLocation();

        return Location::fromObject(
            $location->addVector($position),
            $location->getWorld(),
            $yaw === null ? $location->getYaw() : $yaw,
            $pitch === null ? $location->getPitch() : $pitch
        );
    }

    public static function init(): void
    {
        self::$midpointAreas = [];

        self::$midpointAreas['koth'] = new Vector2(1, 0);
    }
}

Area::init();