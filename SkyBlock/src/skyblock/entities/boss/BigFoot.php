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

use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\math\Vector3;
use function abs;

class BigFoot extends Boss
{
    private const BIG_FOOT_KNOCK_BACK_FORCE = 2.5;

    /** @var int */
    private int $knockBackTick = 0;

    public function __construct(Location $location, Skin $skin)
    {
        $this->bossId = self::BIG_FOOT;
        $this->speed = 0.14;
        $this->spawnHealth = 300;
        $this->damage = 8;

        parent::__construct($location, $skin);
    }

    /**
     * Runs at the entity's tick change.
     *
     * @param int $tickDiff
     * @return bool
     */
    public function entityBaseTick(int $tickDiff = 1): bool
    {
        if ($this->knockBackTick === 0) {
            foreach ($this->getWorld()->getCollidingEntities($this->getBoundingBox()->expandedCopy(5, 5, 5), $this) as $target) {
                $deltaX = abs($target->getPosition()->getX() - $this->getPosition()->getX());
                $deltaZ = abs($target->getPosition()->getZ() - $this->getPosition()->getZ());

                $inverseRatioX = 0;
                $inverseRatioZ = 0;

                if ($deltaX > 0) {
                    $inverseRatioX = 1 / abs($deltaX);
                }

                if ($deltaZ > 0) {
                    $inverseRatioZ = 1 / abs($deltaZ);
                }

                $x = self::BIG_FOOT_KNOCK_BACK_FORCE;
                $z = self::BIG_FOOT_KNOCK_BACK_FORCE;
                $y = 0.5 * self::BIG_FOOT_KNOCK_BACK_FORCE;

                if ($deltaX < 0) {
                    $x = -self::BIG_FOOT_KNOCK_BACK_FORCE;
                }
                if ($deltaZ < 0) {
                    $z = -self::BIG_FOOT_KNOCK_BACK_FORCE;
                }

                $x = $inverseRatioX * $x;
                $z = $inverseRatioZ * $z;

                $target->setMotion(new Vector3($x, $y, $z));

                $this->knockBackTick = 8 * 20;
            }
        } else {
            $this->knockBackTick--;
        }

        return parent::entityBaseTick($tickDiff);
    }

}