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

namespace libMMO\utils\logger;

use GlobalLogger;
use muqsit\invmenu\inventory\InvMenuInventory;
use NetherGames\NGEssentials\events\NGChatEvent;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\ServerManager;
use pocketmine\block\inventory\BlockInventory;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\Listener;
use pocketmine\event\server\CommandEvent;
use pocketmine\inventory\BaseInventory;

/**
 * "If you're doing nothing wrong, you have nothing to hide from the giant
 * surveillance apparatus the government's been hiding."
 */
class LogListener implements Listener
{
    /**
     * @param NGChatEvent $ev
     * @priority MONITOR
     */
    public function onChatEvent(NGChatEvent $ev): void
    {
        GlobalLogger::get()->info($ev->getPlayer()->getName() . " executed command " . $ev->getMessage());
    }

    /**
     * @param CommandEvent $ev
     * @priority MONITOR
     */
    public function onCommandEvent(CommandEvent $ev): void
    {
        GlobalLogger::get()->info($ev->getSender()->getName() . " executed command " . $ev->getCommand());
    }

    /**
     * @param InventoryOpenEvent $event
     * @priority MONITOR
     */
    public function onPlayerOpenInventory(InventoryOpenEvent $event): void
    {
        /** @var BaseInventory $inventory */
        $inventory = $event->getInventory();

        if ($inventory instanceof BlockInventory && !($inventory instanceof InvMenuInventory)) {
            $player = $event->getPlayer();

            $holder = $inventory->getHolder();
            GlobalLogger::get()->info($player->getName() . ' is opening inventory at ' . $holder->getX() . ":" . $holder->getY() . ":" . $holder->getZ());
        }
    }

    /**
     * @param SignChangeEvent $event
     * @priority MONITOR
     */
    public function onSignChangeEvent(SignChangeEvent $event): void
    {
        $player = $event->getPlayer();
        $world = $player->getWorld();

        $players = [];
        foreach ($world->getPlayers() as $targets) {
            $players[] = $targets->getName();
        }

        if (NGEssentials::getInstance()->getServerManager()->getServerType() === ServerManager::SB) {
            GlobalLogger::get()->info('[' . $player->getName() . ', ' . $world->getFolderName() . ' (' . implode(',', $players) . ')] sign change data: ' . implode(',', $event->getNewText()->getLines()));
        } else {
            GlobalLogger::get()->info('[' . $player->getName() . '] sign change data: ' . implode(',', $event->getNewText()->getLines()));
        }
    }
}