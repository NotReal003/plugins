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

namespace factions\entities\boss;

use factions\Factions;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\world\Position;

class BossManager implements BossId
{
    public static function spawnRandomBoss(Position $position): void
    {
        self::spawnBoss($position, array_rand(self::BOSS));
    }

    /**
     * Spawns a boss with it targeting a specific player.
     *
     * @param Position $pos
     * @param int $bossId
     * @return Boss|null
     */
    public static function spawnBoss(Position $pos, int $bossId): ?Boss
    {
        $folder = 'skins' . DIRECTORY_SEPARATOR;

        switch ($bossId) {
            case self::DRAGON:
                $skinId = 'dragon';
                break;
            case self::OGRE:
                $skinId = 'ogre';
                break;
            case self::SKELETON_KING:
            case self::DEMON_LORD:
            default:
                return null;
        }

        $skin = new Skin('Standard_Custom', self::getTextureFromResources($folder . $skinId . '.png'), '', 'geometry.humanoid.custom');

        /**
         * @phpstan-var class-string<Boss>
         */
        $bossClass = self::BOSS[$bossId];

        /** @var Boss $boss */
        $boss = new $bossClass(Location::fromObject($pos, $pos->getWorld()), $skin);

        $boss->setSkin($skin);
        $boss->spawnToAll();

        return $boss;
    }

    public static function getTextureFromResources(string $filename): string
    {
        $ess = Factions::getInstance();

        /** @var resource $png */
        $png = $ess->getResource($filename);
        /** @var string $pngContent */
        $pngContent = stream_get_contents($png);

        fclose($png);

        return self::getTextureFromString($pngContent);
    }

    public static function getTextureFromString(string $string): string
    {
        $img = imagecreatefromstring($string);
        [$k, $l] = getimagesizefromstring($string);
        $bytes = '';

        for ($y = 0; $y < $l; ++$y) {
            for ($x = 0; $x < $k; ++$x) {
                $argb = imagecolorat($img, $x, $y);
                $bytes .= chr(($argb >> 16) & 0xff) . chr(($argb >> 8) & 0xff) . chr($argb & 0xff) . chr(((~((int)($argb >> 24))) << 1) & 0xff);
            }
        }

        imagedestroy($img);
        return $bytes;
    }
}