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

namespace libMMO\commands;

use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use pocketmine\utils\TextFormat;

class HealCommand extends BaseCommand
{

    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct('heal', $plugin);

        $this->setPermission('nethergames.vip.legend');
        $this->setPermissionMessage('§cYou don\'t have permission to heal yourself! Buy the §l§bLEGEND§r §crank at §bngmc.co/store §cto heal!');
        $this->setDescription('Heal command');
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        if (!$this->testPermission($sender)) {
            return true;
        }

        if ($sender->isCombatTimerActive()) {
            $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't heal yourself while you are in combat.");
        } elseif ($this->getOwningPlugin()->getPlayerManager()->canFly($sender)) {
            $sender->setHealth($sender->getMaxHealth());
            $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . 'Your health has been restored!');
        } else {
            $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Healing is disabled in this zone.');
        }

        return true;
    }
}