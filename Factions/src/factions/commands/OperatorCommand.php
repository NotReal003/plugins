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

use factions\faction\object\Faction;
use factions\Factions;
use factions\utils\Database;
use factions\utils\EventEmitter;
use Generator;
use libMMO\MMOPlugin;
use libMMO\player\PlayerData;
use LogicException;
use NetherGames\NGEssentials\player\NGPlayer;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;

/**
 * Operator Command function, use with responsibility.
 *
 * @package factions\commands
 */
class OperatorCommand extends BaseCommand
{
    public function __construct(Factions $owningPlugin)
    {
        parent::__construct('operator', $owningPlugin);

        $this->setAliases(['o']);
        $this->setPermission('nethergames.developer');
        $this->setDescription('NetherGames Developer Operation command.');
        $this->setPermissionMessage('§cThat command is reserved for §eNether§6Games §ddeveloper.');
    }

    public function executeCommand(Player $sender, string $commandLabel, array $args): bool
    {
        if (!$this->testPermission($sender)) {
            return true;
        }

        // Operator Command: (To be done)
        // reducebalance <faction> <int> - Reduce factions treasury balance.
        // addbalance <faction> <int> - Add certain balance to the player.
        // addxp <player> <int> - Add a certain xp to target player
        // setxp <player> <int> - Set a certain amount of Xp to target player
        // addmoney <player> <int> - Add a certain amount of money to target player
        // setmoney <player> <int> - Set a certain amount of money to target player
        try {
            switch ($args[0] ?? "") {
                case "reducebalance":
                    if (isset($args[1], $args[2])) {
                        $target = (string)$args[1];
                        $amount = (int)$args[2];

                        if ($amount > 0) {
                            Await::f2c(function () use ($sender, $target, $amount): Generator {
                                $this->getOwningPlugin()->getFactionManager()->loadFactionByName($target, yield);

                                /** @var Faction|null $faction */
                                $faction = yield Await::ONCE;
                                if ($faction === null) {
                                    $sender->sendMessage("That faction does not exists.");
                                    return;
                                }

                                Database::executeChange("factions.update_balance", [
                                    'withdraw' => $amount,
                                    'factionId' => $faction->getFactionId(),
                                ], yield, yield Await::REJECT);

                                $affectedRows = yield Await::ONCE;
                                if ($affectedRows === 1) {
                                    $faction->setBalance($faction->getBalance() - $amount);

                                    Factions::getInstance()->getEventEmitter()->broadcastEvent($faction, EventEmitter::EVENT_UPDATE_BALANCE, [$faction->getBalance()]);

                                    $sender->sendMessage(MMOPlugin::getPrefix() . "You removed " . number_format($amount) . " coins from the " . $faction->getFactionName() . " faction.");
                                } else {
                                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "The faction don't have enough money.");
                                }
                            });
                        } else {
                            $sender->sendMessage(TextFormat::RED . "That's an invalid number.");
                        }
                    } else {
                        $sender->sendMessage('Usage: ' . TextFormat::RED . '/o addbalance <faction> <balance>');
                    }
                    break;
                case "addbalance":
                    if (isset($args[1], $args[2])) {
                        $target = (string)$args[1];
                        $amount = (int)$args[2];

                        if ($amount > 0) {
                            Await::f2c(function () use ($sender, $target, $amount): Generator {
                                $this->getOwningPlugin()->getFactionManager()->loadFactionByName($target, yield);

                                /** @var Faction|null $faction */
                                $faction = yield Await::ONCE;
                                if ($faction === null) {
                                    $sender->sendMessage("That faction does not exists.");
                                    return;
                                }

                                Database::executeChange("factions.add_balance", [
                                    'deposited' => $amount,
                                    'factionId' => $faction->getFactionId(),
                                ], yield, yield Await::REJECT);

                                $affectedRows = yield Await::ONCE;
                                if ($affectedRows === 1) {
                                    $faction->setBalance($faction->getBalance() + $amount);

                                    Factions::getInstance()->getEventEmitter()->broadcastEvent($faction, EventEmitter::EVENT_UPDATE_BALANCE, [$faction->getBalance()]);

                                    $sender->sendMessage(MMOPlugin::getPrefix() . "You added " . number_format($amount) . " coins to the " . $faction->getFactionName() . " faction.");
                                } else {
                                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Something went wrong, try again later.");
                                }
                            });
                        } else {
                            $sender->sendMessage(TextFormat::RED . "That's an invalid number.");
                        }
                    } else {
                        $sender->sendMessage('Usage: ' . TextFormat::RED . '/o addbalance <faction> <balance>');
                    }
                    break;
                case "reducestrength":
                    if (isset($args[1], $args[2])) {
                        $target = (string)$args[1];
                        $amount = (int)$args[2];

                        if ($amount > 0) {
                            Await::f2c(function () use ($sender, $target, $amount): Generator {
                                $this->getOwningPlugin()->getFactionManager()->loadFactionByName($target, yield);

                                /** @var Faction|null $faction */
                                $faction = yield Await::ONCE;
                                if ($faction === null) {
                                    $sender->sendMessage("That faction does not exists.");
                                    return;
                                }

                                $faction->subtractFromStrength($amount);

                                $sender->sendMessage(TextFormat::RED . "Successfully decreased faction {$faction->getFactionName()} strength to " . number_format($amount) . ".");
                            });
                        } else {
                            $sender->sendMessage(TextFormat::RED . "That's an invalid number.");
                        }
                    } else {
                        $sender->sendMessage('Usage: ' . TextFormat::RED . '/o reducestrength <faction> <strength>');
                    }
                    break;
                case "addstrength":
                    if (isset($args[1], $args[2])) {
                        $target = (string)$args[1];
                        $amount = (int)$args[2];

                        if ($amount > 0) {
                            Await::f2c(function () use ($sender, $target, $amount): Generator {
                                $this->getOwningPlugin()->getFactionManager()->loadFactionByName($target, yield);

                                /** @var Faction|null $faction */
                                $faction = yield Await::ONCE;
                                if ($faction === null) {
                                    $sender->sendMessage(TextFormat::RED . "That faction does not exists.");
                                    return;
                                }

                                $faction->addStrength($amount);

                                $sender->sendMessage(TextFormat::RED . "Successfully increased faction {$faction->getFactionName()} strength to " . number_format($amount) . ".");
                            });
                        } else {
                            $sender->sendMessage(TextFormat::RED . "That's an invalid number.");
                        }
                    } else {
                        $sender->sendMessage('Usage: ' . TextFormat::RED . '/o addstrength <faction> <strength>');
                    }
                    break;
                case "addmoney":
                    if (isset($args[1], $args[2])) {
                        $target = (string)$args[1];
                        $amount = (int)$args[2];

                        if ($amount > 0) {
                            Await::f2c(function () use ($sender, $target, $amount): Generator {
                                NGPlayer::doesNameExist($target, yield);

                                if (!(yield Await::ONCE)) {
                                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "That player doesn't seems to be exists.");
                                    return;
                                }

                                $ecoManager = $this->getOwningPlugin()->getEconomyManager();
                                $ecoManager->increasePlayerMoney($target, $amount, yield);

                                yield Await::ONCE;

                                $sender->sendMessage(MMOPlugin::getPrefix() . 'You added ' . TextFormat::GOLD . '$' . number_format($amount) . TextFormat::GRAY . ' to ' . $target);
                            });
                        } else {
                            $sender->sendMessage(TextFormat::RED . "That's an invalid number.");
                        }
                    } else {
                        $sender->sendMessage('Usage: ' . TextFormat::RED . '/o addmoney <player> <balance>');
                    }
                    break;
                case "addxp":
                    if (isset($args[1], $args[2])) {
                        $target = (string)$args[1];
                        $amount = (int)$args[2];

                        if ($amount > 0) {
                            Await::f2c(function () use ($sender, $target, $amount): Generator {
                                NGPlayer::doesNameExist($target, yield);

                                if (!(yield Await::ONCE)) {
                                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "That player doesn't seems to be exists.");
                                    return;
                                }

                                $ess = $this->getOwningPlugin()->getEssentials();
                                $player = $ess->getPlayerManager()->getBestMatchingPlayer($target);
                                if ($player instanceof Player && $player->isConnected()) {
                                    $player->getXpManager()->addXpLevels($amount);

                                    $sender->sendMessage(MMOPlugin::getPrefix() . 'You added ' . $amount . ' xp to ' . $target . ', total xp: ' . $player->getXpManager()->getXpLevel());
                                } else {
                                    $playerData = $this->getOwningPlugin()->getPlayerData();
                                    $playerData->loadValue($target, PlayerData::XP, yield);

                                    $value = (yield Await::ONCE) + $amount;

                                    $playerData->setValue($target, PlayerData::XP, $value);
                                    $playerData->saveValue($target, PlayerData::XP, yield);

                                    yield Await::ONCE;

                                    $sender->sendMessage(MMOPlugin::getPrefix() . 'You added ' . $amount . ' Xp to ' . $target . ', total xp: ' . $value);
                                }
                            });
                        } else {
                            $sender->sendMessage(TextFormat::RED . "That's an invalid number.");
                        }
                    } else {
                        $sender->sendMessage('Usage: ' . TextFormat::RED . '/o addxp <player> <balance>');
                    }
                    break;
                case "scan":
                case "lockdown":
                default:
                    break;
            }
        } catch (LogicException) {
            $sender->sendMessage(TextFormat::RED . 'Something went wrong, LogicException was thrown.');
        }

        return true;
    }
}