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

use libMMO\forms\TpaForm;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use NetherGames\NGEssentials\NGEssentials;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class TpaCommand extends BaseCommand
{
    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct('tpa', $plugin);

        $this->setDescription('Teleport to a player');
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        if (isset($args[0])) {
            $playerManager = NGEssentials::getInstance()->getPlayerManager();

            $p = $playerManager->getBestMatchingPlayer($args[0]);
            if ($p instanceof MMOPlayer && $p->isConnected()) {
                if ($p->isCombatTimerActive()) {
                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "That player is in combat mode, please wait until the player is no longer in combat mode.");
                } else if (TpaForm::sendTpaRequestAcceptForm($p, $sender)) {
                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . 'You sent a teleport request to ' . TextFormat::GOLD . $p->getName());
                }
            } else {
                $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "That player isn't online on this server.");
            }

            return true;
        }
        TpaForm::sendTpaRequestForm($sender);

        return true;
    }
}