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
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function str_replace;

class FlyCommand extends BaseCommand
{

    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct('fly', $plugin);

        $this->setPermission('nethergames.vip.legend');
        $this->setPermissionMessage('§cYou don\'t have permission to fly! Buy the §l§bLEGEND§r §crank at §bngmc.co/store §cto fly!');
        $this->setDescription('Fly command');
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        if (!$this->testPermission($sender)) {
            return true;
        }

        if ($sender->isCombatTimerActive()) {
            $sender->sendMessage(TextFormat::RED . "You can't fly while you are in combat.");
        } elseif ($this->getOwningPlugin()->getPlayerManager()->canFly($sender)) {
            self::setFlying($sender, !$sender->getAllowFlight());
        } else {
            $sender->sendMessage(TextFormat::RED . 'Flying is disabled in this zone.');
        }

        return true;
    }

    public static function setFlying(Player $player, bool $enable = true, bool $sendMessage = true): void
    {
        $flightMode = TextFormat::GREEN . '• ' . TextFormat::RESET;

        if ($enable) {
            $player->setAllowFlight(true);
            $player->setNameTag($flightMode . str_replace($flightMode, '', $player->getNameTag()));

            if ($sendMessage) {
                $player->sendMessage(TextFormat::GREEN . 'Your ability to fly has been enabled.');
            }
        } else {
            $player->setAllowFlight(false);
            $player->setFlying(false);
            $player->setNameTag(str_replace($flightMode, '', $player->getNameTag()));

            if ($sendMessage) {
                $player->sendMessage(TextFormat::RED . 'Your ability to fly has been disabled.');
            }
        }
    }
}