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
use NetherGames\NGEssentials\player\permissions\Permissions;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\utils\TextFormat;

class InvCommand extends BaseCommand
{
    public function __construct(MMOPlugin $owningPlugin)
    {
        parent::__construct('inv', $owningPlugin);

        $this->setPermission(Permissions::RANK_TRAINEE . ';' . Permissions::RANK_DEVELOPER);
        $this->setPermissionMessage('§cThat command is reserved for §eNether§6Games §cstaff.');
        $this->setDescription('Command used for player investigation');
        $this->setUsage(TextFormat::RED . "/inv <player>");
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        if (!$this->testPermission($sender)) {
            return true;
        }

        if (!isset($args[0])) {
            throw new InvalidCommandSyntaxException();
        }

        $this->getOwningPlugin()->getInvestigationManager()->sendInvestigationForm($sender, $args[0]);
        return true;
    }
}