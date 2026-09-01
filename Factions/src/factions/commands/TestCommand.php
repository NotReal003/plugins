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

use factions\item\CustomItemManager;
use libMMO\item\ItemStorage;
use NetherGames\NGEssentials\player\permissions\Permissions;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Await;

class TestCommand extends Command
{
    public function __construct()
    {
        parent::__construct('test');

        $this->setPermission(Permissions::RANK_DEVELOPER);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if (!$this->testPermission($sender) || !($sender instanceof Player)) {
            return true;
        }

        Await::f2c(function () use ($sender) {
            ItemStorage::createValidationId(CustomItemManager::getPlayerHead($sender), 'kill-' . $sender->getName(), yield);
            $head = yield Await::ONCE;

            $sender->getInventory()->addItem($head);
        });

        return true;
    }
}