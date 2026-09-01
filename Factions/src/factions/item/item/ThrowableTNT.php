<?php
/**
 *        ______         _   _
 *       |  ____|       | | (_)
 *  __  _| |__ __ _  ___| |_ _  ___  _ __  ___
 *  \ \/ /  __/ _` |/ __| __| |/ _ \| '_ \/ __|
 *   >  <| | | (_| | (__| |_| | (_) | | | \__ \
 *  /_/\_\_|  \__,_|\___|\__|_|\___/|_| |_|___/
 *
 * Copyright (C) 2016-2023 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder, Studgi
 */

namespace factions\item\item;

use factions\entities\explosive\PrimedTNT;
use NetherGames\NGEssentials\item\SimpleCustomItem;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\Location;
use pocketmine\item\ItemUseResult;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\IgniteSound;

class ThrowableTNT extends SimpleCustomItem
{
    private bool $worksUnderwater = false;

    protected function describeState(RuntimeDataDescriber $w): void
    {
        $w->bool($this->worksUnderwater);
    }

    public function worksUnderwater(): bool
    {
        return $this->worksUnderwater;
    }

    /** @return $this */
    public function setWorksUnderwater(bool $worksUnderwater): self
    {
        $this->worksUnderwater = $worksUnderwater;
        return $this;
    }

    public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems): ItemUseResult
    {
        $tnt = new PrimedTNT(Location::fromObject($player->getOffsetPosition($player->getPosition()), $player->getWorld()));
        $tnt->setFuse(80);
        $tnt->setWorksUnderwater($this->worksUnderwater());
        $tnt->setMotion($directionVector->multiply(2));

        $tnt->spawnToAll();
        $tnt->broadcastSound(new IgniteSound());

        $this->pop();

        return ItemUseResult::SUCCESS;
    }
}