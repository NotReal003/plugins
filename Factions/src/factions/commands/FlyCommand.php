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
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use NetherGames\NGEssentials\player\permissions\Permissions;
use pocketmine\utils\TextFormat;

class FlyCommand extends \libMMO\commands\FlyCommand
{
    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct($plugin);

        $this->setPermissions([Permissions::DEFAULT_COMMAND_PERMISSION]);
        $this->setDescription('Fly command');
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        if ($sender->isCombatTimerActive()) {
            $sender->sendMessage(TextFormat::RED . "You can't fly while you are in combat.");
        } elseif ($sender->hasPermission('nethergames.vip.ultra')) {
            if (!$this->getOwningPlugin()->getPlayerManager()->canFly($sender)) {
                $sender->sendMessage(TextFormat::RED . 'Flying is disabled in this zone.');
            } else {
                self::setFlying($sender, !$sender->getAllowFlight());
            }
        } elseif (($faction = Factions::getInstance()->getPlayerData()->getFaction($sender)) !== null) {
            if (!$faction->isAreaHome($sender->getPosition()) || !$sender->hasPermission('nethergames.flight.orb')) {
                $sender->sendMessage("§cYou don't have permission to fly! Buy a rank at §bngmc.co/store §cto fly!");
            } else if (!$this->getOwningPlugin()->getPlayerManager()->canFly($sender)) {
                $sender->sendMessage(TextFormat::RED . 'Flying is disabled in this zone.');
            } else {
                self::setFlying($sender, !$sender->getAllowFlight());
            }
        } else {
            $sender->sendMessage("§cYou don't have permission to fly! Buy a rank at §bngmc.co/store §cto fly!");
        }

        return true;
    }
}