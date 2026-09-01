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

namespace libMMO\economy\shop;

use libforms\elements\Input;
use libforms\elements\Label;
use libforms\elements\Toggle;
use libforms\FormManager;
use libMMO\economy\EconomyManager;
use libMMO\MMOPlugin;
use libMMO\player\PlayerData;
use libMMO\utils\Utils;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function is_numeric;
use function number_format;
use const PHP_INT_MAX;

class Transaction
{
    /** @var Category */
    private Category $category;
    /** @var Item */
    private Item $transactionItem;
    /** @var int */
    private int $buyCost;
    /** @var int */
    private int $sellCost;
    /** @var EconomyManager */
    private EconomyManager $economyManager;

    //Form specific variables.
    /** @var bool */
    private bool $isBuying = false;
    /** @var bool */
    private bool $isSelling = false;

    /**
     * Transaction constructor.
     * @param Category $category
     * @param Item|ShopItem $transactionItem
     * @param EconomyManager $economyManager
     */
    public function __construct(Category $category, ShopItem|Item $transactionItem, EconomyManager $economyManager)
    {
        $this->category = $category;

        if ($transactionItem instanceof Item) {
            $this->transactionItem = $transactionItem;
            $this->buyCost = $category->getBuyPrice($transactionItem);
            $this->sellCost = $category->getSellPrice($transactionItem);

            if ($this->transactionItem->getCustomBlockData() === null) {
                $this->transactionItem->setCustomBlockData(Utils::readOnlyTag());
            } else {
                $this->transactionItem->getCustomBlockData()->setInt(Utils::READONLY_TAGS, 0);
            }
        } else {
            $this->transactionItem = $transactionItem->getCleanItem();
            $this->buyCost = $transactionItem->getBuyPrice();
            $this->sellCost = $transactionItem->getSellPrice();
        }

        $this->economyManager = $economyManager;
    }

    /**
     * Sends the chest UI to the player.
     *
     * @param Player $player
     * @param bool $useForm
     * @param InvMenu|null $menu
     */
    public function send(Player $player, bool $useForm = false, ?InvMenu $menu = null): void
    {
        if ($useForm) {
            $form = FormManager::createCustomForm($player, function (Player $player) {
                Shop::getCategory($this->category->getTitle())->send($player, true);
            });

            if ($form !== null) {
                $form->setTitle($this->transactionItem->getVanillaName());

                $form->addElement(new Label($this->category->isXpCategory()
                    ? 'Levels: ' . TextFormat::GOLD . number_format($player->getXpManager()->getXpLevel())
                    : 'Purse: ' . TextFormat::GOLD . '$' . number_format($this->economyManager->getPlugin()->getPlayerData()->getInt($player, PlayerData::PLAYER_MONEY))
                ));
                $form->addElement(new Toggle('Buy', false, function () use ($player) {
                    if ($this->isSelling) {
                        $player->sendMessage(TextFormat::RED . 'You can only toggle one option (buy/sell).');
                        $this->isBuying = false;
                        $this->isSelling = false;
                    } else {
                        $this->isBuying = true;
                    }
                }));
                if ($this->sellCost !== 0) {
                    $form->addElement(new Toggle('Sell', false, function () use ($player) {
                        if ($this->isBuying) {
                            $player->sendMessage(TextFormat::RED . 'You can only toggle one option (buy/sell).');
                            $this->isBuying = false;
                            $this->isSelling = false;
                        } else {
                            $this->isSelling = true;
                        }
                    }));
                }
                $form->addElement(new Label($this->category->isXpCategory()
                    ? 'Purchase price: ' . $this->buyCost . ' levels/each'
                    : 'Purchase price: $' . number_format($this->buyCost) . '/each'));
                if ($this->sellCost !== 0) {
                    $form->addElement(new Label($this->category->isXpCategory()
                        ? 'Sell price: ' . $this->sellCost . ' levels/each'
                        : 'Sell price: $' . number_format($this->sellCost) . '/each'));
                }
                $form->addElement(new Input('Amount', '1', '', function (Player $player, $data) {
                    if (!is_numeric($data)) {
                        $player->sendMessage(TextFormat::RED . $data . 'is an invalid number!');
                        return;
                    }

                    $data = (int)$data;

                    if ($data <= 0 || $data >= PHP_INT_MAX) {
                        $player->sendMessage(TextFormat::RED . $data . 'is an invalid number!');
                        return;
                    }

                    $clearedItem = $this->transactionItem;
                    $clearedItem->setCount($data);

                    if ($this->isSelling) {
                        $this->sellItem($player, $clearedItem);

                        $this->isSelling = false;
                    } elseif ($this->isBuying) {
                        $this->buyItem($player, $clearedItem);

                        $this->isBuying = false;
                    } else {
                        $player->sendMessage(TextFormat::RED . 'You must select an option (buy/sell).');
                    }
                }));

                $form->sendForm();
            }
        } else {
            $created = false;

            if ($menu === null) {
                $menu = InvMenu::create(MMOPlugin::MENU_CHEST_DOUBLE);
                $created = true;
            }

            $menu->setName($this->transactionItem->getVanillaName());
            $menu->getInventory()->clearAll();
            $this->addDefaultItems($menu, $this->sellCost !== 0);
            $this->attachListener($menu);

            if ($created) {
                $menu->send($player);
            }
        }
    }

    public function sellItem(Player $player, Item $clearedItem): void
    {
        $cost = $this->sellCost * $clearedItem->getCount();

        if (!$player->getInventory()->contains($clearedItem)) {
            $player->sendMessage(TextFormat::RED . "You don't have enough of the item you want to sell in your inventory!");
            return;
        }

        $player->getInventory()->removeItem($clearedItem);

        if ($this->category->isXpCategory()) {
            $player->getXpManager()->addXpLevels($cost);

            $player->sendMessage(TextFormat::GREEN . 'You sold ' . TextFormat::GOLD . $clearedItem->getCount() . 'x ' . TextFormat::clean($clearedItem->getName()) . TextFormat::GREEN . ' for ' . TextFormat::GOLD . $cost . " xp levels");
        } else {
            $this->economyManager->increasePlayerMoney($player->getName(), $cost, function () use ($player, $clearedItem, $cost) {
                $player->sendMessage(TextFormat::GREEN . 'You sold ' . TextFormat::GOLD . $clearedItem->getCount() . 'x ' . TextFormat::clean($clearedItem->getName()) . TextFormat::GREEN . ' for ' . TextFormat::GOLD . '$' . number_format($cost));
            }, function () use ($player, $clearedItem) {
                $player->getInventory()->addItem($clearedItem);

                $player->sendMessage(TextFormat::RED . 'Something went wrong while trying to sell your item');
            });
        }
    }

    public function buyItem(Player $player, Item $clearedItem): void
    {
        $cost = $this->buyCost * $clearedItem->getCount();

        if ($cost === 0) {
            $this->economyManager->getPlugin()->getLogger()->critical($clearedItem->__toString() . ' has buycost 0');
            return;
        }

        $targetItems = $this->category->getItemCallback($clearedItem);
        if (count($targetItems) === 1 && !$player->getInventory()->canAddItem($targetItems[0]) || count($targetItems) > 1 && !Utils::canAddItems($player->getInventory(), count($targetItems))) {
            $player->sendMessage(TextFormat::RED . 'Your inventory is currently full!');
            return;
        }

        if ($this->category->isXpCategory()) {
            if ($player->getXpManager()->getXpLevel() >= $cost) {
                $player->getXpManager()->subtractXpLevels($cost);
                $player->getInventory()->addItem(...$targetItems);
                $player->sendMessage(TextFormat::GREEN . 'You bought ' . TextFormat::GOLD . $clearedItem->getCount() . 'x ' . TextFormat::clean($clearedItem->getName()) . TextFormat::GREEN . ' for ' . TextFormat::GOLD . $cost . " xp levels");
            } else {
                $player->sendMessage(TextFormat::RED . "You don't have enough xp levels!");
            }
        } else {
            $this->economyManager->reducePlayerMoney($player->getName(), $cost, function () use ($player, $targetItems, $clearedItem, $cost): void {
                if ($player->isConnected()) {
                    $player->getInventory()->addItem(...$targetItems);
                    $player->sendMessage(TextFormat::GREEN . 'You bought ' . TextFormat::GOLD . $clearedItem->getCount() . 'x ' . TextFormat::clean($clearedItem->getName()) . TextFormat::GREEN . ' for ' . TextFormat::GOLD . '$' . number_format($cost));
                } else {
                    $this->economyManager->increasePlayerMoney($player->getName(), $cost);
                }
            });
        }
    }

    /**
     * Adds the items within the menu.
     *
     * @param InvMenu $menu
     * @param bool $sellable
     */
    final public function addDefaultItems(InvMenu $menu, bool $sellable): void
    {
        $menu->getInventory()->setContents($this->getMode(true));
        $menu->getInventory()->setItem(13, $this->transactionItem);

        $buy = VanillaBlocks::CONCRETE()->setColor(DyeColor::LIME)->asItem();
        $buy->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GREEN . 'Buy Item')->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to confirm your purchase.']);
        $buy->getNamedTag()->setByte('buy', 1);
        $buy->setCustomBlockData(Utils::readOnlyTag());
        $menu->getInventory()->setItem(38, $buy);

        if ($sellable) {
            $sell = VanillaBlocks::CONCRETE()->setColor(DyeColor::CYAN)->asItem();
            $sell->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GOLD . 'Sell Item')->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to confirm your sale.']);
            $sell->getNamedTag()->setByte('sell', 1);
            $sell->setCustomBlockData(Utils::readOnlyTag());
            $menu->getInventory()->setItem(40, $sell);
        }

        $back = VanillaBlocks::CONCRETE()->setColor(DyeColor::RED)->asItem();
        $back->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . 'Cancel')->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to cancel the transaction.']);
        $back->getNamedTag()->setByte('back', 1);
        $back->setCustomBlockData(Utils::readOnlyTag());
        $menu->getInventory()->setItem(42, $back);
    }

    /**
     * Applies the InvMenu listener, which listens for each item clicked from
     * the addDefaultItems() function.
     *
     * @param InvMenu $menu
     */
    final public function attachListener(InvMenu $menu): void
    {
        $menu->setListener(InvMenu::readonly(function (DeterministicInvMenuTransaction $transaction) use ($menu): void {
            $player = $transaction->getPlayer();
            $itemClicked = $transaction->getItemClicked();

            $namedTag = $itemClicked->getNamedTag();
            if (Utils::hasTag($namedTag, 'add')) {
                $amount = $this->transactionItem->getCount() + $namedTag->getInt('add');
                $this->transactionItem->setCount(min($amount, 64));
                $menu->getInventory()->setItem(13, $this->transactionItem);
            } elseif (Utils::hasTag($namedTag, 'remove')) {
                $amount = $this->transactionItem->getCount() - $namedTag->getInt('remove');
                $this->transactionItem->setCount($amount > 0 ? $amount : 1);
                $menu->getInventory()->setItem(13, $this->transactionItem);
            } elseif (Utils::hasTag($namedTag, 'mode')) {
                $items = $this->getMode($namedTag->getInt('mode') === 0);

                foreach ($items as $slot => $item) {
                    $menu->getInventory()->setItem($slot, $item);
                }
            } elseif ($itemClicked->equals($this->transactionItem)) {
                $amount = $this->transactionItem->getCount() - 1;
                $this->transactionItem->setCount($amount > 0 ? $amount : 1);
                $menu->getInventory()->setItem(13, $this->transactionItem);
            } elseif (Utils::hasTag($namedTag, 'back')) {
                Shop::getCategory($this->category->getTitle())->send($player, false, $menu);
            } elseif (!$itemClicked->isNull()) {
                $clearedItem = $this->category->getOriginalItem($this->transactionItem)->setCount($this->transactionItem->getCount());

                if (Utils::hasTag($namedTag, 'buy')) {
                    $this->buyItem($player, $clearedItem);
                } elseif (Utils::hasTag($namedTag, 'sell')) {
                    $this->sellItem($player, $clearedItem);
                }
            }
        }));
    }

    private function getMode(bool $isAddition): array
    {
        $modeAddition = VanillaBlocks::WOOL()->setColor(DyeColor::MAGENTA)->asItem()->setCustomBlockData(Utils::readOnlyTag());
        $modeSubtraction = VanillaBlocks::WOOL()->setColor(DyeColor::ORANGE)->asItem()->setCustomBlockData(Utils::readOnlyTag());
        $addOne = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::LIME)->asItem()->setCustomBlockData(Utils::readOnlyTag());
        $addSixteen = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::YELLOW)->asItem()->setCustomBlockData(Utils::readOnlyTag());
        $addSixtyFour = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::RED)->asItem()->setCustomBlockData(Utils::readOnlyTag());

        if ($isAddition) {
            $modeAddition->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GREEN . 'Addition Mode');
            $modeAddition->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Currently in addition mode.']);
            $modeAddition->getNamedTag()->setInt('mode', 0);

            $modeSubtraction->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . 'Subtraction Mode');
            $modeSubtraction->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to change current mode.']);
            $modeSubtraction->getNamedTag()->setInt('mode', 1);

            $addOne->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GREEN . '+1')->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to increase quantity by 1.']);
            $addOne->getNamedTag()->setInt('add', 1);

            $addSixteen->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::YELLOW . '+16')->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to increase quantity by 16.']);
            $addSixteen->getNamedTag()->setInt('add', 16);

            $addSixtyFour->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . '+64')->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to increase quantity by 64.']);
            $addSixtyFour->getNamedTag()->setInt('add', 64);
        } else {
            $modeAddition->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . 'Addition Mode');
            $modeAddition->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to change current mode.']);
            $modeAddition->getNamedTag()->setInt('mode', 0);

            $modeSubtraction->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GREEN . 'Subtraction Mode');
            $modeSubtraction->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Currently in subtraction mode.']);
            $modeSubtraction->getNamedTag()->setInt('mode', 1);

            $addOne->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GREEN . '-1')->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to decrease quantity by 1.']);
            $addOne->getNamedTag()->setInt('remove', 1);

            $addSixteen->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::YELLOW . '-16')->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to decrease quantity by 16.']);
            $addSixteen->getNamedTag()->setInt('remove', 16);

            $addSixtyFour->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . '-64')->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to decrease quantity by 64.']);
            $addSixtyFour->getNamedTag()->setInt('remove', 64);
        }
        $addSixteen->setCount(16);
        $addSixtyFour->setCount(64);

        return [10 => $modeAddition, 19 => $modeSubtraction, 21 => $addOne, 22 => $addSixteen, 23 => $addSixtyFour];
    }
}