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
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use pocketmine\utils\TextFormat;

class AutoClaimCommand extends BaseCommand
{
    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct('autoclaim', $plugin);

        $this->setDescription('Toggle auto claim heads feature');
        $this->setAliases(['ac', 'auto']);
        $this->setPermission('nethergames.vip.ultra');
        $this->setPermissionMessage(Factions::getPrefix() . TextFormat::RED . "You don't have permission to use autoclaim! Buy a rank at §bngmc.co/store §cto use this feature!");
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        if (!$this->testPermission($sender)) {
            return true;
        }

        $playerData = $this->getOwningPlugin()->getPlayerData();
        if (isset($args[0])) {
            switch ($args[0]) {
                case 'on':
                    $playerData->setValue($sender, PlayerData::AUTO_CLAIM, true);
                    $this->sendMessage($sender, 'Enabled auto claiming for heads. Any heads you collect will now be automatically claimed.');
                    break;
                case 'off':
                    $playerData->setValue($sender, PlayerData::AUTO_CLAIM, false);
                    $this->sendFailureMessage($sender, 'Disabled auto claiming for heads. Any heads you collect will no longer be automatically claimed.');
                    break;
                default:
                    $this->sendFailureMessage($sender, 'Usage: /autoclaim <on/off>');
            }
        } else {
            $this->sendFailureMessage($sender, 'Usage: /autoclaim <on/off>');
        }

        return true;
    }
}