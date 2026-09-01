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

namespace factions\entities\explosive;

use factions\utils\explosion\CustomExplosion;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\world\Position;

class PrimedTNT extends \pocketmine\entity\object\PrimedTNT
{
    public function explode(): void
    {
        $ev = new EntityPreExplodeEvent($this, 4);
        $ev->call();
        if (!$ev->isCancelled()) {
            $explosion = new CustomExplosion(Position::fromObject($this->location->add(0, $this->size->getHeight() / 2, 0), $this->getWorld()), $this->worksUnderwater(), $ev->getRadius(), $this);
            if ($ev->isBlockBreaking()) {
                $explosion->explodeA();
            }
            $explosion->explodeB();
        }
    }
}