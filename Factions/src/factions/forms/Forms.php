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

namespace factions\forms;


use factions\Factions;
use factions\task\teleport\TransferServerTask;
use libforms\elements\Button;
use libforms\FormManager;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\ServerManager;
use NetherGames\NGEssentials\servers\DataServer;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class Forms
{
    public static function sendWildernessSelector(Player $player): void
    {
        $form = FormManager::createSimpleForm($player);

        if ($form !== null) {
            $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . "Select teleport option.");

            if (!Factions::isBadlands()) {
                $form->addButton(new Button(TextFormat::RED . "Wilderness Warzone", static function (Player $player): void {
                    Server::getInstance()->dispatchCommand($player, 'wz');
                }));
            }

            $serverManager = NGEssentials::getInstance()->getServerManager();
            $serverRegion = $serverManager->getServerRegion();

            $manager = Factions::getInstance()->getRegionManager();
            foreach ($manager->getRegions()[ServerManager::GAME_TYPE_FARLANDS] as $region => $serversCluster) {
                $serverCount = $serversCluster === null ? 0 : $serversCluster->getOnlinePlayers();
                $wildName = TextFormat::DARK_GRAY . $region . " Wilderness [$serverCount]" . (!Factions::isBadlands() && $serverRegion === $region ? TextFormat::EOL . TextFormat::DARK_AQUA . "[You're in this server]" : "");

                $form->addButton(new Button($wildName, static function (Player $player) use ($manager, $serverRegion, $region): void {
                    if (!Factions::isBadlands() && $region === $serverRegion) {
                        Server::getInstance()->dispatchCommand($player, 'wild');
                    } else {
                        $server = $manager->getFarlandsByRegion($region);

                        if ($server === null) {
                            $player->sendMessage(Factions::getPrefix() . TextFormat::RED . "The server you are teleporting into is currently offline, try again later!");
                        } else {
                            Factions::getInstance()->getScheduler()->scheduleRepeatingTask(new TransferServerTask($player, $server), 20);
                        }
                    }
                }));
            }

            $form->sendForm();
        }
    }

    public static function sendBadlandsSelector(Player $player): void
    {
        $candidate = [];

        $manager = Factions::getInstance()->getRegionManager();
        foreach ($manager->getRegions()[ServerManager::GAME_TYPE_BADLANDS] as $region => $serversCluster) {
            if ($serversCluster === null) {
                continue;
            }

            $candidate[$region] = $serversCluster;
        }

        if (count($candidate) > 1) {
            if (($form = FormManager::createSimpleForm($player)) === null) {
                return;
            }

            $serverManager = NGEssentials::getInstance()->getServerManager();
            $serverRegion = $serverManager->getServerRegion();

            $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . "Select teleport option.");

            foreach ($candidate as $region => $serversCluster) {
                $badlandsName = TextFormat::DARK_GRAY . $region . " Badlands [{$serversCluster->getOnlinePlayers()}]" . (Factions::isBadlands() && $serverRegion === $region ? TextFormat::EOL . TextFormat::DARK_AQUA . "[You're in this server]" : "");
                $form->addButton(new Button($badlandsName, static function (Player $player) use ($manager, $serverRegion, $region): void {
                    if (Factions::isBadlands() && $region === $serverRegion) {
                        Server::getInstance()->dispatchCommand($player, 'pvp');
                    } else {
                        $server = $manager->getBadlandsByRegion($region);

                        if ($server === null) {
                            $player->sendMessage(Factions::getPrefix() . TextFormat::RED . "The server you are teleporting into is currently offline, try again later!");
                        } else {
                            Factions::getInstance()->getScheduler()->scheduleRepeatingTask(new TransferServerTask($player, $server), 20);
                        }
                    }
                }));
            }

            $form->sendForm();
        } else if (!empty($candidate)) {
            /** @var DataServer $server */
            $server = $candidate[array_key_first($candidate)];

            Factions::getInstance()->getScheduler()->scheduleRepeatingTask(new TransferServerTask($player, $server), 20);
        } else {
            $player->sendMessage(Factions::getPrefix() . TextFormat::RED . "Badlands is temporarily offline.");
        }
    }
}