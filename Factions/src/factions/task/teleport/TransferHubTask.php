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

namespace factions\task\teleport;

use factions\Factions;
use NetherGames\NGEssentials\NGEssentials;
use pocketmine\entity\Location;
use pocketmine\player\Player;
use pocketmine\Server;

class TransferHubTask extends TeleportTask
{
    public function __construct(Player $player)
    {
        $spawn = $player->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation();

        parent::__construct($player, $spawn);
    }

    public function teleportToTarget(): void
    {
        NGEssentials::getInstance()->getPlayerManager()->transferPlayer($this->player);
    }
}