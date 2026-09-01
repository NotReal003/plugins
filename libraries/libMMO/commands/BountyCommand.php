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
use libMMO\player\PlayerData;
use libMMO\player\PlayerManager;
use libMMO\utils\EventEmitter;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function is_numeric;
use function number_format;

class BountyCommand extends BaseCommand
{

    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct('bounty', $plugin);

        $this->setDescription('Bounty command');
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        if (isset($args[0])) {
            switch ($args[0]) {
                case 'set':
                    if (isset($args[1], $args[2]) && is_numeric($args[2])) {
                        $playerName = $args[1];
                        $p = $this->getOwningPlugin()->getEssentials()->getPlayerManager()->getBestMatchingPlayer($playerName);
                        $amount = (int)$args[2];

                        if ($amount < 1000) {
                            $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't place a bounty below 1,000!");
                            return false;
                        }

                        if ($p->getName() === $sender->getName()) {
                            $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't place a bounty on yourself.");
                        } elseif ($p instanceof Player && $p->isConnected()) {
                            $this->setBounty($p->getName(), $sender, $amount);
                        } else {
                            PlayerManager::getPlayerAlike($playerName, function (array $players) use ($sender, $amount) {
                                if (!empty($players[0])) {
                                    $this->setBounty($players[0], $sender, $amount);
                                } else {
                                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'An account with that username does not exist - please try again.');
                                }
                            });
                        }
                        return true;
                    }
                    break;
                case 'get':
                    if (isset($args[1])) {
                        $playerName = $args[1];
                        $p = $this->getOwningPlugin()->getEssentials()->getPlayerManager()->getBestMatchingPlayer($playerName);

                        if ($p instanceof Player && $p->isConnected()) {
                            $playerName = $p->getName();

                            $playerData = $this->getOwningPlugin()->getPlayerData();
                            $playerData->loadValue($playerName, PlayerData::BOUNTY, static function (int $currentBounty) use ($p, $sender, $playerName) {
                                if ($currentBounty === 0) {
                                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::AQUA . $playerName . TextFormat::RED . " doesn't have a bounty at the moment.");
                                } else {
                                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::AQUA . $playerName . TextFormat::GOLD . ' has a bounty of ' . TextFormat::GREEN . '$' . number_format($currentBounty) . TextFormat::GOLD . '.');
                                }

                                if ($p->isConnected()) {
                                    MMOPlugin::getInstance()->getPlayerManager()->updateBountyScoreboard($p->getName(), $currentBounty);
                                }
                            });
                        } else {
                            PlayerManager::getPlayerAlike($playerName, function (array $players) use ($sender) {
                                if (!empty($players)) {
                                    $playerName = $players[0];
                                    $playerData = $this->getOwningPlugin()->getPlayerData();
                                    $playerData->loadValue($playerName, PlayerData::BOUNTY, static function (int $currentBounty) use ($sender, $playerName) {
                                        if ($currentBounty === 0) {
                                            $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::AQUA . $playerName . TextFormat::RED . " doesn't have a bounty at the moment.");
                                        } else {
                                            $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::AQUA . $playerName . TextFormat::GOLD . ' has a bounty of ' . TextFormat::GREEN . '$' . number_format($currentBounty) . TextFormat::GOLD . '.');
                                        }
                                    });
                                } else {
                                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'An account with that username does not exist - please try again.');
                                }
                            });
                        }
                    } else {
                        $playerData = $this->getOwningPlugin()->getPlayerData();
                        $playerData->loadValue($sender->getName(), PlayerData::BOUNTY, static function (int $currentBounty) use ($sender) {
                            if ($currentBounty === 0) {
                                $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You don't have a bounty at the moment.");
                            } else {
                                $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::GOLD . 'You have a bounty of ' . TextFormat::GREEN . '$' . number_format($currentBounty) . TextFormat::GOLD . '!');
                            }
                        });
                    }
                    return true;
            }
        }

        $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Usage: /bounty <set [player] [amount] | get {player}>');
        return true;
    }

    public static function setBounty(string $playerName, Player $sender, int $amount): void
    {
        MMOPlugin::getInstance()->getEconomyManager()->reducePlayerMoney($sender->getName(), $amount, function () use ($sender, $playerName, $amount) {
            $playerData = MMOPlugin::getInstance()->getPlayerData();
            $playerData->loadValue($playerName, PlayerData::BOUNTY, function (int $currentBounty) use ($sender, $playerData, $playerName, $amount) {
                $result = $currentBounty + $amount;

                $playerData->setValue($playerName, PlayerData::BOUNTY, $result);
                $playerData->saveValue($playerName, PlayerData::BOUNTY, function (int $insertId, int $affectedRows) use ($sender, $playerName, $amount, $result) {
                    if ($sender->isConnected()) {
                        $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . 'You added ' . TextFormat::GOLD . '$' . number_format($amount) . TextFormat::GREEN . ' to ' . TextFormat::GOLD . $playerName . "'s" . TextFormat::GREEN . ' bounty!');
                    }

                    MMOPlugin::getInstance()->getServer()->broadcastMessage(TextFormat::AQUA . $sender->getName() . TextFormat::GOLD . ' has added ' . TextFormat::GREEN . '$' . number_format($amount) . TextFormat::GOLD . ' to ' . TextFormat::AQUA . $playerName . "'s " . TextFormat::GOLD . 'bounty' . TextFormat::GOLD . '!');
                    MMOPlugin::getInstance()->getPlayerManager()->updateBountyScoreboard($playerName, $result);

                    MMOPlugin::getInstance()->getEventEmitter()->broadcastDefault($playerName, EventEmitter::NOTIFICATION_BOUNTY);
                });
            });
        });
    }

}