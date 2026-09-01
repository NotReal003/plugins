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

use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use function array_search;
use function in_array;

class BossMinion extends Boss
{

    /**
     * BossMinion constructor.
     *
     * @param Location $location
     * @param Skin $skin
     * @param Boss|null $owner
     */
    public function __construct(Location $location, Skin $skin, ?Boss $owner)
    {
        $this->bossId = self::MINION;
        $this->speed = 0.23;
        $this->spawnHealth = 15;
        $this->damage = 5;

        parent::__construct($location, $skin);

        $this->setOwningEntity($owner);
        $this->setScale(0.75);
    }

    /**
     * Flags for Despawn while also removing minion from owner minion list.
     *
     * @void
     */
    public function flagForDespawn(): void
    {
        $owner = $this->getOwningEntity();

        if ($owner !== null && in_array($this, $owner->minionList, true)) {
            $key = array_search($this, $owner->minionList, true);
            if (is_int($key)) {
                unset($owner->minionList[$key]);
            }
        }

        parent::flagForDespawn();
    }

    /**
     * @return Boss|null
     */
    public function getOwningEntity(): ?Entity
    {
        /** @var Boss|null $owner */
        $owner = parent::getOwningEntity();

        return $owner;
    }
}