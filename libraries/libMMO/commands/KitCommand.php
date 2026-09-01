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
use pocketmine\network\mcpe\protocol\types\InputMode;
use pocketmine\utils\TextFormat;

class KitCommand extends BaseCommand
{
    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct('kit', $plugin);

        $this->setDescription('Kit command');
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        $kitManager = $this->getOwningPlugin()->getKitManager();

        if (isset($args[0])) {
            $kit = $kitManager->getKit($args[0]);

            if ($kit === null) {
                $sender->sendMessage(TextFormat::RED . 'That kit does not exist.');
                return false;
            } else {
                $kitManager->redeemKit($sender, $kit);
            }
        } else {
            $kitManager->send($sender, $sender->getInputMode() === InputMode::TOUCHSCREEN);
        }
        return true;
    }
}