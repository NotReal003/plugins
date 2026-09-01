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

use libforms\elements\Input;
use libforms\FormManager;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use pocketmine\item\Durable;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function strlen;

class RenameCommand extends BaseCommand
{
    public const RENAME_PRICE = 5000;
    public const RENAME_MAX_LENGTH = 16;

    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct('rename', $plugin);

        $this->setPermission('nethergames.vip.ultra');
        $this->setPermissionMessage(TextFormat::RED . "You don't have permission to rename an item. Buy a rank at §bngmc.co/store §cto rename one!");
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        $item = $sender->getInventory()->getItemInHand();
        if (!$item instanceof Durable) {
            $sender->sendMessage(TextFormat::RED . 'You can only rename a tool or a piece of armor.');
            return false;
        }

        $form = FormManager::createCustomForm($sender);

        if ($form !== null) {
            $holding = $sender->getInventory()->getHeldItemIndex();

            $form->addElement(new Input('What would you like to rename the item you currently are holding?', 'My best item', '', function (Player $player, string $input) use ($item, $holding) {
                if (strlen($input) < self::RENAME_MAX_LENGTH) {
                    $currentHold = $player->getInventory()->getHeldItemIndex();

                    if ($player->getInventory()->getItemInHand()->equalsExact($item) && $currentHold === $holding) {
                        $this->getOwningPlugin()->getEconomyManager()->reducePlayerMoney($player->getName(), self::RENAME_PRICE, function () use ($item, $input, $player, $holding) {
                            if ($player->isConnected() && $player->getInventory()->getItemInHand()->equalsExact($item) && $player->getInventory()->getHeldItemIndex() === $holding) {
                                $item->setCustomName($input);
                                $player->getInventory()->setItemInHand($item);

                                $player->sendMessage(TextFormat::GREEN . 'Your item has been renamed to ' . TextFormat::GOLD . $input . TextFormat::GREEN . '!');
                            } else {
                                $this->getOwningPlugin()->getEconomyManager()->increasePlayerMoney($player->getName(), self::RENAME_PRICE);
                            }
                        });
                    }
                } else {
                    $player->sendMessage(TextFormat::RED . "You can't rename your item to a word or phrase exceeding " . self::RENAME_MAX_LENGTH . ' characters.');
                }
            }));

            $form->sendForm();
        }

        return true;
    }
}