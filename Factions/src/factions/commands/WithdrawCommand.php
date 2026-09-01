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
use factions\item\CustomItemManager;
use Generator;
use libforms\elements\Input;
use libforms\elements\Label;
use libforms\FormManager;
use libMMO\item\ItemStorage;
use libMMO\MMOPlugin;
use libMMO\player\PlayerData;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;

class WithdrawCommand extends BaseCommand
{
    public function __construct(Factions $owningPlugin)
    {
        parent::__construct('withdraw', $owningPlugin);

        $this->setUsage(TextFormat::RED . "/withdraw <coins>");
        $this->setDescription('Withdraw coins from your balance.');
    }

    public function executeCommand(Player $sender, string $commandLabel, array $args): bool
    {
        Await::f2c(function () use ($sender, $args) {
            if (isset($args[0])) {
                /** @var string $amount */
                $amount = $args[0];

                if (!is_numeric($amount)) {
                    $sender->sendMessage(TextFormat::RED . "That's an invalid number.");
                    return;
                }

                $amount = (int)$amount;

                if ($amount > 5_000_000) {
                    $this->sendFailureMessage($sender, 'You cannot withdraw more than 5,000,000 coins');
                } else if ($amount > 0) {
                    yield $this->createMoneyPouch($sender, $amount);
                } else {
                    $sender->sendMessage(TextFormat::RED . "That's an invalid number.");
                }

                return;
            }

            $form = FormManager::createCustomForm($sender);

            if ($form !== null) {
                $form->setTitle(MMOPlugin::getPrefix() . TextFormat::DARK_GRAY . 'Coins Withdrawal');
                $form->addElement(new Label(TextFormat::GREEN . 'Your coins: ' . TextFormat::WHITE . '$' . number_format($this->getOwningPlugin()->getPlayerData()->getInt($sender, PlayerData::PLAYER_MONEY))));
                $form->addElement(new Input('Amount', '1', '', yield Await::RESOLVE_MULTI));
                $form->sendForm();

                $result = yield Await::ONCE;

                if (!$sender->isConnected()) {
                    return;
                }

                /** @var string $amount */
                $amount = $result[1];

                if (!is_numeric($amount)) {
                    $sender->sendMessage(TextFormat::RED . "That's an invalid number.");
                    return;
                }

                $amount = (int)$amount;

                if ($amount > 5_000_000) {
                    $this->sendFailureMessage($sender, 'You cannot withdraw more than 5,000,000 coins');
                } else if ($amount > 0) {
                    yield $this->createMoneyPouch($sender, $amount);
                } else {
                    $sender->sendMessage(TextFormat::RED . "That's an invalid number.");
                }
            }
        });

        return true;
    }

    public function createMoneyPouch(Player $sender, int $amount): Generator
    {
        $item = CustomItemManager::getMoneyPouch($amount);

        ItemStorage::createValidationId($item, $sender->getName(), yield);

        $item = yield Await::ONCE;

        if (!$sender->isConnected()) {
            ItemStorage::removeValidationId($item);

            return;
        }

        if (!$sender->getInventory()->canAddItem($item)) {
            ItemStorage::removeValidationId($item);

            $this->sendFailureMessage($sender, 'Your inventory is currently full!');
            return;
        }

        $this->getOwningPlugin()->getEconomyManager()->reducePlayerMoney($sender->getName(), $amount, yield, function (bool $isRateLimited) use ($item, $sender) {
            ItemStorage::removeValidationId($item);

            if ($isRateLimited) {
                $this->sendFailureMessage($sender, 'Please wait a few seconds before executing this command again.');
            }
        });

        yield Await::ONCE;

        if (!$sender->isConnected()) {
            ItemStorage::removeValidationId($item);

            $this->getOwningPlugin()->getEconomyManager()->increasePlayerMoney($sender->getName(), $amount);
        } else {
            $sender->getInventory()->addItem($item);

            $this->sendMessage($sender, TextFormat::GREEN . "You withdrew $amount coins from your balance.");
        }
    }
}