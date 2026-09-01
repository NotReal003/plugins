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

use factions\entities\boss\BossManager;
use pocketmine\block\BlockBreakInfo as BreakInfo;
use pocketmine\block\BlockIdentifier as BID;
use pocketmine\block\BlockTypeIds as Ids;
use pocketmine\block\BlockTypeInfo as Info;
use pocketmine\block\DragonEgg as VanillaDragonEgg;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\ToolTier;
use pocketmine\world\particle\HugeExplodeSeedParticle;
use pocketmine\world\sound\BlazeShootSound;

class DragonEgg extends VanillaDragonEgg
{
    public function __construct()
    {
        parent::__construct(new BID(Ids::DRAGON_EGG), "Dragon Egg", new Info(BreakInfo::pickaxe(3.0, ToolTier::WOOD)));
    }

    public function teleport(): void
    {
        $world = $this->position->getWorld();

        if ($world->isChunkLocked($this->getPosition()->getFloorX() >> 4, $this->getPosition()->getFloorZ() >> 4)) {
            return;
        }

        $world->setBlock($this->getPosition(), VanillaBlocks::AIR());
        $world->addParticle($this->getPosition(), new HugeExplodeSeedParticle());
        $world->addSound($this->getPosition(), new BlazeShootSound());

        BossManager::spawnRandomBoss($this->getPosition());
    }
}