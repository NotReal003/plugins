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

use libMMO\MMOPlugin;
use libMMO\player\PlayerManager;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class BalanceCommand extends BaseCommand
{
    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct('balance', $plugin);

        $this->setDescription("Check a player's balance");
        $this->setUsage(TextFormat::RED . '/balance <player>');
        $this->setAliases(['bal', 'mymoney', 'coins', 'mycoins', 'seemoney']);
    }

    public function executeCommand(Player $sender, string $commandLabel, array $args): bool
    {
        if (isset($args[0])) {
            $playerName = $args[0];
            $player = $this->getOwningPlugin()->getEssentials()->getPlayerManager()->getBestMatchingPlayer($playerName);

            if ($player instanceof Player && $player->isConnected()) {
                $playerName = $player->getName();

                $playerData = $this->getOwningPlugin()->getPlayerData();
                $playerData->loadMoneyBalance($player, static function (int $currentBalance) use ($sender, $playerName) {
                    $sender->sendMessage(TextFormat::AQUA . $playerName . TextFormat::GOLD . ' has a balance of ' . TextFormat::GREEN . '$' . number_format($currentBalance) . TextFormat::GOLD . '.');
                });
            } else {
                PlayerManager::getPlayerAlike($args[0], function (array $players) use ($sender) {
                    if (!empty($players)) {
                        $playerName = $players[0];
                        $playerData = $this->getOwningPlugin()->getPlayerData();
                        $playerData->loadMoneyBalance($players[0], static function (int $currentBalance) use ($sender, $playerName) {
                            $sender->sendMessage(TextFormat::AQUA . $playerName . TextFormat::GOLD . ' has a balance of ' . TextFormat::GREEN . '$' . number_format($currentBalance) . TextFormat::GOLD . '.');
                        });
                    } else {
                        $sender->sendMessage(TextFormat::RED . 'An account with that username does not exist - please try again.');
                    }
                });
            }
        } else {
            $playerData = $this->getOwningPlugin()->getPlayerData();
            $playerData->loadMoneyBalance($sender->getName(), static function (int $currentBalance) use ($sender) {
                $sender->sendMessage(TextFormat::GOLD . 'You have a balance of ' . TextFormat::GREEN . '$' . number_format($currentBalance) . TextFormat::GOLD . '!');
            });
        }

        return true;
    }
}