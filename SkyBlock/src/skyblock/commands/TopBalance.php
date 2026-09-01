<?php
/**
 *         _____ _          _     _            _
 *        / ____| |        | |   | |          | |
 *  __  _| (___ | | ___   _| |__ | | ___   ___| | __
 *  \ \/ /\___ \| |/ / | | | '_ \| |/ _ \ / __| |/ /
 *   >  < ____) |   <| |_| | |_) | | (_) | (__|   <
 *  /_/\_\_____/|_|\_\\__, |_.__/|_|\___/ \___|_|\_\
 *                     __/ |
 *                    |___/
 *
 * Copyright (C) 2016-2022 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder
 */

declare(strict_types=1);

namespace skyblock\commands;

use Generator;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use pocketmine\utils\TextFormat;
use skyblock\utils\Database;
use SOFe\AwaitGenerator\Await;

class TopBalance extends BaseCommand
{
    public function __construct(MMOPlugin $owningPlugin)
    {
        parent::__construct("baltop", $owningPlugin);

        $this->setDescription("Top richest players in skyblock.");
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        switch ($args[0] ?? "") {
            case "money":
            case "bank":
                $isBank = $args[0] === "bank";

                Await::f2c(function () use ($sender, $isBank): Generator {
                    if ($isBank) {
                        Database::executeSelectRaw("SELECT player, bank FROM player_data ORDER BY bank DESC LIMIT 10", [], yield, yield Await::REJECT);
                        $rows = yield Await::ONCE;

                        $sender->sendMessage(MMOPlugin::getPrefix() . "Top 10 Richest Bank Balance:");
                    } else {
                        Database::executeSelectRaw("SELECT player, money FROM player_data ORDER BY money DESC LIMIT 10", [], yield, yield Await::REJECT);
                        $rows = yield Await::ONCE;

                        $sender->sendMessage(MMOPlugin::getPrefix() . "Top 10 Richest Money Balance:");
                    }

                    $message = "";
                    foreach ($rows as $place => $data) {
                        $message .= TextFormat::LIGHT_PURPLE . TextFormat::BOLD . ($place + 1) . '. ' . TextFormat::RESET;
                        $message .= $data['player'] . TextFormat::GRAY . ' » ' . TextFormat::YELLOW;
                        $message .= '$' . number_format($isBank ? $data['bank'] : $data['money']);

                        $sender->sendMessage($message);

                        $message = "";
                    }
                }, catches: Database::getFailClosure());
                break;
            default:
                $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Usage: /baltop <bank/money>");
                break;
        }

        return true;
    }
}