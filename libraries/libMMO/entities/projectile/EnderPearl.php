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

use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use pocketmine\entity\projectile\EnderPearl as PMEnderPearl;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\utils\TextFormat;

class EnderPearl extends PMEnderPearl
{
    protected function onHit(ProjectileHitEvent $event): void
    {
        $owner = $this->getOwningEntity();
        if ($owner !== null) {
            if ($owner instanceof MMOPlayer && $owner->isCombatTimerActive()) {
                $owner->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You cannot teleport while in combat mode.");
                return;
            }

            parent::onHit($event);
        }
    }
}