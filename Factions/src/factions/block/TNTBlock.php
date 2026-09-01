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

namespace factions\block;

use factions\entities\explosive\PrimedTNT;
use pocketmine\block\BlockBreakInfo as BreakInfo;
use pocketmine\block\BlockIdentifier as BID;
use pocketmine\block\BlockTypeIds as Ids;
use pocketmine\block\BlockTypeInfo as Info;
use pocketmine\block\TNT;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\sound\IgniteSound;

class TNTBlock extends TNT
{
    public function __construct()
    {
        parent::__construct(new BID(Ids::TNT), "TNT", new Info(BreakInfo::instant()));
    }

    public function ignite(int $fuse = 80): void
    {
        $this->getPosition()->getWorld()->setBlock($this->getPosition(), VanillaBlocks::AIR());

        $mot = (new Random())->nextSignedFloat() * M_PI * 2;

        $tnt = new PrimedTNT(Location::fromObject($this->getPosition()->add(0.5, 0, 0.5), $this->getPosition()->getWorld()));
        $tnt->setFuse($fuse);
        $tnt->setWorksUnderwater($this->worksUnderwater);
        $tnt->setMotion(new Vector3(-sin($mot) * 0.02, 0.2, -cos($mot) * 0.02));

        $tnt->spawnToAll();
        $tnt->broadcastSound(new IgniteSound());
    }
}