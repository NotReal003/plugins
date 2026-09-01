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

namespace libMMO\commands;

use libforms\elements\Button;
use libforms\FormManager;
use libMMO\challenges\ChallengeSet;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use pocketmine\item\Durable;
use pocketmine\utils\TextFormat;
use function number_format;

class RepairCommand extends BaseCommand
{
    public const MONEY_DURABILTY_MULTIPLIER = 4;
    public const COST_PER_ENCHANTMENT = 250;

    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct('repair', $plugin);
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        $item = $sender->getInventory()->getItemInHand();

        if ($item->isNull()) {
            $sender->sendMessage(TextFormat::RED . "You're not holding an item!");
        } elseif ($item instanceof Durable) {
            if ($item->getDamage() > 0) {
                $form = FormManager::createModalForm($sender);

                if ($form !== null) {
                    $form->setTitle('Repair Item');

                    $holding = $sender->getInventory()->getHeldItemIndex();

                    $baseCost = 0;
                    $baseCost += $item->getDamage() * self::MONEY_DURABILTY_MULTIPLIER;
                    foreach ($item->getEnchantments() as $enchantment) {
                        $baseCost += ($enchantment->getLevel() + 1) * self::COST_PER_ENCHANTMENT;
                    }

                    $form->setContent('Would you like to repair the item you currently are holding? The cost will be ' . TextFormat::GREEN . '$' . number_format($baseCost) . TextFormat::RESET . '.');

                    $plugin = $this->getOwningPlugin();
                    $form->setButton1(new Button(TextFormat::GREEN . 'Confirm', static function () use ($sender, $baseCost, $item, $plugin, $holding) {
                        $currentHold = $sender->getInventory()->getHeldItemIndex();

                        if ($sender->isCombatTimerActive()) {
                            $sender->sendMessage(TextFormat::RED . "You cannot repair an item while in combat mode.");
                        } else if ($sender->getInventory()->getItemInHand()->equalsExact($item) && $currentHold === $holding) {
                            $plugin->getEconomyManager()->reducePlayerMoney($sender->getName(), $baseCost, static function () use ($sender, $baseCost, $item, $plugin, $holding) {
                                if ($sender->isConnected() && $sender->getInventory()->getItemInHand()->equalsExact($item) && $sender->getInventory()->getHeldItemIndex() === $holding) {
                                    $sender->sendMessage(TextFormat::GREEN . 'The item you are currently holding has been repaired!');
                                    $item->setDamage(0);
                                    $sender->getInventory()->setItemInHand($item);

                                    foreach ($plugin->getPlayerChallengeManager()->getActiveChallenges($sender) as $challenge) {
                                        $challenge->increaseProgress($sender, ChallengeSet::REPAIR_ITEM);
                                    }
                                } else {
                                    $plugin->getEconomyManager()->increasePlayerMoney($sender->getName(), $baseCost, static function () use ($sender) {
                                        if ($sender->isConnected()) {
                                            $sender->sendMessage(TextFormat::RED . "You can't equip another item while something is being repaired.");
                                        }
                                    });
                                }
                            });
                        }
                    }));
                    $form->setButton2(new Button(TextFormat::RED . 'Cancel'));
                    $form->sendForm();
                }
            } else {
                $sender->sendMessage(TextFormat::RED . 'That item is currently at maximum durability.');
            }
        } else {
            $sender->sendMessage(TextFormat::RED . 'You can only repair a tool or a piece of armour.');
        }

        return true;
    }
}