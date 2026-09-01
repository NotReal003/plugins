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

namespace skyblock\entities\boss;

use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\utils\SkinUtils;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use function fclose;
use function stream_get_contents;
use const DIRECTORY_SEPARATOR;

class BossManager implements BossId
{
    /**
     * Spawns a boss with it targeting a specific player.
     *
     * @param Location $location
     * @param int $bossId
     * @param Boss|null $ownerBoss
     * @return Boss|null
     */
    public static function spawnBoss(Location $location, int $bossId, ?Boss $ownerBoss = null): ?Boss
    {
        if ($ownerBoss === null) {
            $prefix = '';
            $folder = 'skins' . DIRECTORY_SEPARATOR . 'costumes' . DIRECTORY_SEPARATOR;
        } else {
            $prefix = 'minion.';
            $folder = 'skins' . DIRECTORY_SEPARATOR . 'costumes' . DIRECTORY_SEPARATOR . 'minions' . DIRECTORY_SEPARATOR;
        }

        switch ($bossId) {
            case self::MEDUSA:
                $skinId = 'medusa';
                break;
            case self::BIG_FOOT:
                $skinId = 'bigfoot';
                break;
            case self::DESERTER:
                $skinId = 'deserter';
                break;
            case self::THANOS:
                $skinId = 'thanos';
                break;
            default:
                return null;
        }

        $geometry = NGEssentials::getInstance()->getResource($folder . 'geometry' . DIRECTORY_SEPARATOR . $skinId . '.json');
        $skin = new Skin($prefix . $skinId, SkinUtils::getTextureFromResources($folder . $skinId . '.png'), '', 'geometry.costume.' . $prefix . $skinId, (string)stream_get_contents($geometry));
        fclose($geometry);

        if ($ownerBoss === null) {
            switch ($bossId) {
                case self::MEDUSA:
                    $boss = new Medusa($location, $skin);
                    break;
                case self::BIG_FOOT:
                    $boss = new BigFoot($location, $skin);
                    break;
                case self::DESERTER:
                    $boss = new Deserter($location, $skin);
                    break;
                case self::THANOS:
                    $boss = new Thanos($location, $skin);
                    break;
                default:
                    return null;
            }
        } else {
            $boss = new BossMinion($location, $skin, $ownerBoss);
            $boss->setBossLevel($ownerBoss->getBossLevel());
        }

        $boss->setSkin($skin);
        $boss->spawnToAll();

        return $boss;
    }
}