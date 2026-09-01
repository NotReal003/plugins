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

namespace libMMO\forms;

use libforms\elements\Button;
use libforms\elements\ImageButton;
use libforms\elements\Input;
use libforms\elements\Label;
use libforms\FormManager;
use libMMO\economy\EconomyManager;
use libMMO\item\CustomItemManager;
use libMMO\item\ItemStorage;
use libMMO\MMOPlugin;
use libMMO\player\PlayerData;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function is_numeric;
use function number_format;

class EconomyForms
{
    public static function sendATMMenu(Player $player, int $money, MMOPlugin $plugin): void
    {
        $callable = static function (int $bankMoney) use ($player, $plugin) {
            if ($player->isConnected()) {
                $pocketMoney = $plugin->getPlayerData()->getInt($player, PlayerData::PLAYER_MONEY);

                $form = FormManager::createSimpleForm($player);

                if ($form !== null) {
                    $form->setTitle('ATM');

                    $form->setContent($content = TextFormat::GREEN . 'Your bank balance: ' . TextFormat::WHITE . '$' . number_format($bankMoney) . TextFormat::EOL . TextFormat::GREEN . 'Your purse: ' . TextFormat::WHITE . '$' . number_format($plugin->getPlayerData()->getInt($player, PlayerData::PLAYER_MONEY)));

                    if ($bankMoney !== 0) {
                        $form->addButton(new Button(TextFormat::YELLOW . 'Withdraw', static function (Player $player) use ($content, $bankMoney, $plugin) {
                            self::sendWithdrawMenu($player, $content, $bankMoney, $plugin);
                        }));
                    }

                    if ($pocketMoney !== 0) {
                        $form->addButton(new Button(TextFormat::YELLOW . 'Deposit', static function (Player $player) use ($content, $bankMoney, $plugin) {
                            self::sendDepositMenu($player, $content, $bankMoney, $plugin);
                        }));

                        $form->addButton(new Button(TextFormat::YELLOW . 'Create Money Pouch', static function (Player $player) use ($content, $bankMoney, $plugin) {
                            self::sendPouchesMenu($player, $content, $bankMoney, $plugin);
                        }));
                    }

                    $form->addButton(new ImageButton(TextFormat::RED . TextFormat::BOLD . 'Exit', ImageButton::IMAGE_TYPE_PATH, 'textures/blocks/barrier'));

                    $form->sendForm();
                }
            }
        };

        if ($money === -1) {
            $plugin->getPlayerData()->loadValue($player->getName(), PlayerData::BANK_MONEY, $callable);
        } else {
            $callable($money);
        }
    }

    public static function sendWithdrawMenu(Player $player, string $content, int $bankMoney, MMOPlugin $plugin): void
    {
        $form = FormManager::createCustomForm($player);

        if ($form !== null) {
            $form->setTitle('Bank Withdrawal');

            $form->setCallable(static function (Player $player) use ($bankMoney, $plugin) {
                self::sendATMMenu($player, $bankMoney, $plugin);
            });

            $form->addElement(new Label($content));

            $form->addElement(new Input('Amount', '1', '', static function (Player $player, string $input) use ($plugin) {
                if (!is_numeric($input)) {
                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "That's an invalid number.");
                    return;
                }

                $input = (int)$input;

                if ($input > 0) {
                    $plugin->getEconomyManager()->withdrawFromBank($player, $input, function ($bankMoney) use ($player, $plugin) {
                        self::sendATMMenu($player, $bankMoney, $plugin);
                    });
                } else {
                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "That's an invalid number.");
                }
            }));

            $form->sendForm();
        }
    }

    public static function sendDepositMenu(Player $player, string $content, int $bankMoney, MMOPlugin $plugin): void
    {
        $form = FormManager::createCustomForm($player);

        if ($form !== null) {
            $form->setTitle('Bank Deposit');

            $form->setCallable(static function (Player $player) use ($bankMoney, $plugin) {
                self::sendATMMenu($player, $bankMoney, $plugin);
            });

            $form->addElement(new Label($content));

            $form->addElement(new Input('Amount', '1', '', static function (Player $player, string $input) use ($plugin) {
                if (!is_numeric($input)) {
                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "That's an invalid number.");
                    return;
                }

                $input = (int)$input;

                if ($input > 0) {
                    $plugin->getEconomyManager()->depositToBank($player, $input, function ($bankMoney) use ($player, $plugin) {
                        self::sendATMMenu($player, $bankMoney, $plugin);
                    });
                } else {
                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "That's an invalid number.");
                }
            }));

            $form->sendForm();
        }
    }

    public static function sendPouchesMenu(Player $player, string $content, int $bankMoney, MMOPlugin $plugin): void
    {
        $form = FormManager::createCustomForm($player);

        if ($form !== null) {
            $form->setTitle('Create Money Pouch');

            $form->setCallable(static function (Player $player) use ($bankMoney, $plugin) {
                self::sendATMMenu($player, $bankMoney, $plugin);
            });

            $form->addElement(new Label($content));

            $form->addElement(new Input('Amount', '1', '', static function (Player $player, string $amount) use ($plugin, $bankMoney) {
                if (!is_numeric($amount)) {
                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "That's an invalid number.");
                    return;
                }

                $amount = (int)$amount;

                if ($amount > 0) {
                    if ($amount <= EconomyManager::MAX_MONEY_AMOUNT) {
                        $plugin->getEconomyManager()->reducePlayerMoney($player->getName(), $amount, static function () use ($plugin, $player, $amount, $bankMoney) {
                            if (!$player->isConnected()) {
                                $plugin->getEconomyManager()->increasePlayerMoney($player->getName(), $amount);
                                return;
                            }

                            $item = CustomItemManager::getMoneyPouch($amount);

                            if ($player->getInventory()->canAddItem($item)) {
                                ItemStorage::createValidationId($item, $player->getName(), static function (Item $item) use ($plugin, $player, $amount, $bankMoney) {
                                    if ($player->isConnected()) {
                                        $player->getInventory()->addItem($item);
                                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . 'You created a money pouch worth ' . TextFormat::GOLD . '$' . number_format($amount) . TextFormat::GREEN . ' from your bank balance.');

                                        if ($amount > 25000000) {
                                            $plugin->getLoggerStream()->add('**SUSPICIOUS ACTIVITY** - ' . $player->getName() . ' redeemed ' . $amount . ' coins from a money pouch');
                                        }
                                        self::sendATMMenu($player, $bankMoney, $plugin);
                                    } else {
                                        ItemStorage::removeValidationId($item);
                                        $plugin->getEconomyManager()->increasePlayerMoney($player->getName(), $amount);
                                    }
                                });
                            } else {
                                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Your inventory is currently full!');
                                $plugin->getEconomyManager()->increasePlayerMoney($player->getName(), $amount);
                            }
                        });
                    } else {
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't create a money pouch worth over $" . number_format(EconomyManager::MAX_MONEY_AMOUNT) . '.');
                    }
                }
            }));

            $form->sendForm();
        }
    }
}