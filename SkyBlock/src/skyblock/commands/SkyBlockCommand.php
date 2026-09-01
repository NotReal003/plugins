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
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew
 *
 */
declare(strict_types=1);

namespace skyblock\commands;

use libMMO\player\MMOPlayer;
use pocketmine\utils\TextFormat;
use skyblock\forms\IslandForm;
use skyblock\SkyBlock;

class SkyBlockCommand extends BaseCommand
{
    public const BLOCKED_LEVELS = ['pvp', 'sb-arena'];

    public function __construct(SkyBlock $plugin)
    {
        parent::__construct('skyblock', $plugin);

        $this->setAliases(['sb', 'is', 'island']);
        $this->setDescription('Open the islands UI');
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        if (in_array($sender->getWorld()->getFolderName(), self::BLOCKED_LEVELS)) {
            $sender->sendMessage(TextFormat::RED . "You can't use that command in this world.");
        } else {
            if ($sender->isCombatTimerActive()) {
                $sender->sendMessage(TextFormat::RED . "You can't teleport while you are in combat.");
                return false;
            }

            IslandForm::sendSkyBlockForm($sender, $this->getOwningPlugin());
        }

        return true;
    }
}