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
use libMMO\player\MMOPlayer;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class SetHomeCommand extends BaseCommand
{

    public function __construct(Factions $plugin)
    {
        parent::__construct('sethome', $plugin);

        $this->setDescription('Set home command.');
        $this->setUsage(TextFormat::RED . '/sethome <home>');
    }

    public function executeCommand(Player $sender, string $commandLabel, array $args): bool
    {
        $playerData = $this->getOwningPlugin()->getPlayerData();

        /** @var MMOPlayer $sender */
        if (isset($args[0])) {
            $home = $playerData->getHomeByName($sender, $args[0]);

            if ($home !== null) {
                $this->sendFailureMessage($sender, "A home with that name is already exists.");
            } else if ($sender->getWorld()->getFolderName() === "wild") {
                $playerData->setHomePosition($sender, $args[0]);

                $this->sendMessage($sender, "You have set a home named, '$args[0]'.");
            } else {
                $this->sendFailureMessage($sender, "You cannot set a home here.");
            }
        } else {
            throw new InvalidCommandSyntaxException();
        }

        return true;
    }
}