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
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function count;
use function number_format;

class PayCommand extends BaseCommand
{
    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct('pay', $plugin);

        $this->setDescription('Pay money from purse');
        $this->setUsage(TextFormat::RED . '/pay <player> <amount>');
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        if ($this->getOwningPlugin()->getPlayerManager()->canDoTransactions($sender)) {
            if (count($args) >= 2) {
                if (($p = $this->getOwningPlugin()->getEssentials()->getPlayerManager()->getBestMatchingPlayer($args[0])) instanceof Player) {
                    $amount = (int)$args[1];

                    if ($amount > 0) {
                        $ecoManager = $this->getOwningPlugin()->getEconomyManager();
                        $ecoManager->reducePlayerMoney($sender->getName(), $amount, function () use ($p, $sender, $amount, $ecoManager) {
                            if ($p->isConnected() && $sender->isConnected()) {
                                $ecoManager->increasePlayerMoney($p->getName(), $amount, function () use ($p, $sender, $amount) {
                                    $sender->sendMessage(TextFormat::GREEN . 'You sent ' . TextFormat::GOLD . '$' . number_format($amount) . TextFormat::GREEN . ' to ' . $p->getName());
                                    $p->sendMessage(TextFormat::GOLD . $sender->getName() . TextFormat::GREEN . ' has sent you ' . TextFormat::GOLD . '$' . number_format($amount) . TextFormat::GREEN . '!');
                                });
                            } else {
                                $ecoManager->increasePlayerMoney($sender->getName(), $amount);
                            }
                        });
                    } else {
                        $sender->sendMessage(TextFormat::RED . "That's an invalid number.");
                    }
                } else {
                    $sender->sendMessage(TextFormat::RED . "That player isn't online on this server.");
                }
            } else {
                throw new InvalidCommandSyntaxException();
            }
        } else {
            $sender->sendMessage(TextFormat::RED . "You can't do transaction right now!");
        }

        return true;
    }
}