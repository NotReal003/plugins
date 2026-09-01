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
use factions\player\PlayerData;
use factions\utils\object\TransferLogic;
use NetherGames\NGEssentials\servers\DataServer;
use pocketmine\player\Player;

class TransferServerLogicTask extends TransferServerTask
{
    /** @var TransferLogic */
    private TransferLogic $homeLocation;

    public function __construct(Player $player, TransferLogic $location, DataServer $targetServer)
    {
        parent::__construct($player, $targetServer);

        $this->homeLocation = $location;
    }

    public function teleportToTarget(): void
    {
        if (!$this->homeLocation->isValidServer()) {
            $playerData = Factions::getInstance()->getPlayerData();
            $playerData->setValue($this->player, PlayerData::TRANSFER_LOCATION, $this->homeLocation->getTransportData());
        }

        parent::teleportToTarget();
    }
}