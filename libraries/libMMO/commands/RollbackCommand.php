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

namespace libMMO\commands;

use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;

class RollbackCommand extends BaseCommand
{
    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct("rollback", $plugin);

        $this->setUsage("/rollback <storage|claim>");
        $this->setDescription("Rollback management command.");
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        switch (strtolower($args[0] ?? "help")) {
            case "help":
                $sender->sendMessage("§aRollback commands: ");
                $sender->sendMessage("- §2/$commandLabel claim §l§5»§r§f Claim all items from rollback list.");
                $sender->sendMessage("- §2/$commandLabel storage §l§5»§r§f Open rollback item storage.");
                break;
            case "claim":
                $this->getOwningPlugin()->getRollbackEngine()->claimRollbackItems($sender);
                break;
            case "storage":
                $this->getOwningPlugin()->getRollbackEngine()->openRollbackStorage($sender);
                break;
        }

        return true;
    }
}