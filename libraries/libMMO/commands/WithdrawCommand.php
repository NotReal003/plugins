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

use libMMO\forms\EconomyForms;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use libMMO\player\PlayerData;
use pocketmine\utils\TextFormat;
use function number_format;

class WithdrawCommand extends BaseCommand
{

    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct('withdraw', $plugin);

        $this->setDescription('Withdraw money from your bank');
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        if ($this->getOwningPlugin()->getPlayerManager()->canDoTransactions($sender)) {
            $this->getOwningPlugin()->getPlayerData()->loadValue($sender->getName(), PlayerData::BANK_MONEY, function (int $bankMoney) use ($sender): void {
                if ($sender->isConnected()) {
                    $content = TextFormat::GREEN . 'Your bank balance: ' . TextFormat::WHITE . '$' . number_format($bankMoney) . TextFormat::EOL . TextFormat::GREEN . 'Your purse: ' . TextFormat::WHITE . '$' . number_format($this->getOwningPlugin()->getPlayerData()->getInt($sender, PlayerData::PLAYER_MONEY));
                    EconomyForms::sendWithdrawMenu($sender, $content, $bankMoney, $this->getOwningPlugin());
                }
            });
        } else {
            $sender->sendMessage(TextFormat::RED . "You can't do transaction right now!");
        }
        return true;
    }
}