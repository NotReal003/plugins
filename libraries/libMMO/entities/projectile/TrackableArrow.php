<?php
/**
 *   _ _ _     __  __ __  __  ____
 *  | (_) |   |  \/  |  \/  |/ __ \
 *  | |_| |__ | \  / | \  / | |  | |
 *  | | | '_ \| |\/| | |\/| | |  | |
 *  | | | |_) | |  | | |  | | |__| |
 *  |_|_|_.__/|_|  |_|_|  |_|\____/
 *
 * Copyright (C) 2016-2024 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder, Studgi
 */

declare(strict_types=1);

namespace libMMO\entities\projectile;

use libMMO\utils\Utils;
use NetherGames\NGEssentials\entity\Arrow;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;

class TrackableArrow extends Arrow
{
    /** @var Vector3 */
    private Vector3 $firstVector;

    public function __construct(Location $location, ?Entity $shootingEntity, bool $critical, ?CompoundTag $nbt = null)
    {
        parent::__construct($location, $shootingEntity, $critical, $nbt);

        if ($nbt !== null && Utils::hasTag($nbt, 'StartPos')) {
            $this->firstVector = EntityDataHelper::parseVec3($nbt, 'StartPos', false);
        } else {
            $this->firstVector = $this->getPosition()->asVector3();
        }
    }

    public function getFirstVector(): Vector3
    {
        return $this->firstVector;
    }
}