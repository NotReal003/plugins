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

namespace factions\commands;

use factions\Factions;
use factions\player\PlayerData;
use libMMO\player\MMOPlayer;
use pocketmine\utils\TextFormat;

class KeysCommand extends BaseCommand
{
    public function __construct(Factions $owningPlugin)
    {
        parent::__construct("keys", $owningPlugin);

        $this->setAliases(['list']);
        $this->setDescription("List all the crate keys you have owned.");
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        $playerData = Factions::getInstance()->getPlayerData();

        $keys = $playerData->getArray($sender, PlayerData::CRATE_KEYS);
        if (empty($keys)) {
            $this->sendFailureMessage($sender, "You don't have any keys.");

            return true;
        }

        $this->sendMessage($sender, "Your keys:");
        foreach ($keys as $crateId => $crateAmount) {
            $sender->sendMessage(TextFormat::GRAY . ' - ' . $this->getOwningPlugin()->getCrateManager()->getCrateName($crateId) . ': ' . TextFormat::WHITE . $crateAmount);
        }

        return true;
    }
}