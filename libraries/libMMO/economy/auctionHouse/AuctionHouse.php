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

namespace libMMO\economy\auctionHouse;

use Generator;
use libforms\elements\Button;
use libforms\elements\ImageButton;
use libforms\elements\Input;
use libforms\elements\Label;
use libforms\FormManager;
use libMMO\item\ItemStorage;
use libMMO\MMOPlugin;
use libMMO\player\Inventory;
use libMMO\player\PlayerData;
use libMMO\utils\BaseClass;
use libMMO\utils\rollback\RollbackEngine;
use libMMO\utils\Utils;
use libVanilla\LibVanillaItems;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;
use function array_chunk;
use function array_map;
use function array_merge;
use function count;
use function floor;
use function max;
use function min;
use function number_format;
use function strtolower;
use function time;
use function trim;

abstract class AuctionHouse extends BaseClass
{
    public const AUCTION_LENGTH = 60 * 60 * 24;
    public const AUCTIONS_NUMBER_PER_PAGE = 45;

    public const FILTER_NONE = 0;
    public const FILTER_BY_CATEGORY = 1;
    public const FILTER_BY_ITEM_NAME = 2;
    public const FILTER_BY_PLAYER = 3;
    public const FILTER_BY_PLAYER_WITH_EXPIRED = 4;

    public const FILTER_DISPLAYS = [
        self::FILTER_NONE => 'No Filter',
        self::FILTER_BY_CATEGORY => 'By Category',
        self::FILTER_BY_ITEM_NAME => 'By Item Name',
        self::FILTER_BY_PLAYER => 'By Player'
    ];

    /** @var AuctionHouseCategory[] */
    protected array $categories = [];

    abstract public function getAuctionFromId(int $auctionId, callable $callable): void;

    abstract public function isValidAuction(int $auctionId, callable $callable): void;

    abstract public function getAuctionsInCategory(string $category, callable $callable): void;

    abstract public function getAuctionsWithItemName(string $name, callable $callable): void;

    abstract public function getAuctionsFromPlayer(string $player, bool $includeExpired, callable $callable): void;

    abstract public function getAllAuctions(callable $callable): void;

    abstract public function sellItem(Player $player, Item $item, int $price, int $auctionLength): bool;

    abstract public function removeAuction(int $auctionId, ?callable $onSuccess = null): void;

    public function addCategory(AuctionHouseCategory $category): void
    {
        $this->categories[strtolower($category->getName())] = $category;
    }

    public function getMaximumNumberOfAuctions(Player $player): int
    {
        if ($player->hasPermission('nethergames.vip.titan')) {
            return 20;
        }
        if ($player->hasPermission('nethergames.vip.legend')) {
            return 10;
        }
        if ($player->hasPermission('nethergames.vip.emerald')) {
            return 7;
        }
        if ($player->hasPermission('nethergames.vip.ultra')) {
            return 5;
        }
        return 3;
    }

    protected function getBalanceItem(Player $player): Item
    {
        return VanillaBlocks::DOUBLE_TALLGRASS()->asItem()->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GOLD . 'Your Balance')->setLore([
            '',
            TextFormat::RESET . TextFormat::AQUA . 'Purse: ' . TextFormat::WHITE . '$' . number_format($this->getPlugin()->getPlayerData()->getInt($player, PlayerData::PLAYER_MONEY)),
            '',
            TextFormat::RESET . TextFormat::GRAY . 'You can only use your purse balance',
            TextFormat::RESET . TextFormat::GRAY . 'to buy from the Auction House.',
            TextFormat::RESET . TextFormat::GRAY . 'Use the ATM to withdraw from bank to your purse.'
        ]);
    }

    public function sendAuction(Player $player, int $auctionId): void
    {
        Await::f2c(function () use ($player, $auctionId): Generator {
            $this->getAuctionFromId($auctionId, yield);

            /** @var Auction|null $auction */
            $auction = yield Await::ONCE;

            if ($auction === null || $auction->isExpired()) {
                $player->sendMessage(TextFormat::RED . 'This auction no longer exists.');
                return;
            }

            if ($auction->getPlayer() === $player->getName()) {
                $player->sendMessage(TextFormat::RED . "You can't buy your own auction!");
                return;
            }

            $item = clone $auction->getItem();
            $itemValidation = clone $auction->getItem();
            if (ItemStorage::hasValidationId($itemValidation)) {
                ItemStorage::isValid($itemValidation, yield);

                $result = yield Await::ONCE;
                if ($result === ItemStorage::ITEM_INVALID) {
                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Server could not verify the auction's validity, it was removed for security reasons.");
                    return;
                }

                $item->setCount(1);
            }

            if (!$player->isConnected()) {
                return;
            }

            $menu = InvMenu::create(MMOPlugin::MENU_HOPPER);
            $menu->setName('Confirm Purchase');
            $menu->getInventory()->setContents([
                VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::GREEN)->asItem()->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GREEN . 'Confirm Purchase')->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to buy this item.']),
                VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::GREEN)->asItem()->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GREEN . 'Confirm Purchase')->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to buy this item.']),
                $item->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::LIGHT_PURPLE . $item->getName())->setLore($item->getLore() + [
                        '',
                        TextFormat::RESET . TextFormat::AQUA . 'Seller: ' . TextFormat::WHITE . $auction->getPlayer(),
                        TextFormat::RESET . TextFormat::YELLOW . 'Price: ' . TextFormat::WHITE . '$' . number_format($auction->getPrice()),
                        TextFormat::RESET . ($auction->isExpired() ? TextFormat::BOLD . TextFormat::RED . 'EXPIRED' : TextFormat::GOLD . 'Expires: ' . TextFormat::WHITE . $this->formatTime($auction->getExpiration() - time()))
                    ]),
                VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::RED)->asItem()->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . 'Cancel Purchase')->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to return to listings page.']),
                VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::RED)->asItem()->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . 'Cancel Purchase')->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to return to listings page.'])
            ]);
            $menu->setListener(InvMenu::readonly(function (DeterministicInvMenuTransaction $transaction) use ($auction, $auctionId): void {
                $player = $transaction->getPlayer();
                $action = $transaction->getAction();

                Await::f2c(function () use ($player, $action, $auction, $auctionId) {
                    if ($action->getSlot() < 2) {
                        $player->removeCurrentWindow();

                        $this->isValidAuction($auctionId, yield);

                        /** @var bool $valid */
                        $valid = yield Await::ONCE;

                        if (!$player->isConnected()) {
                            return;
                        }

                        if (!$valid) {
                            $player->sendMessage(TextFormat::RED . 'This auction no longer exists.');
                            return;
                        }

                        if (!$player->getInventory()->canAddItem($auction->getItem())) {
                            $player->sendMessage(TextFormat::RED . 'Your inventory is currently full!');
                            return;
                        }

                        $this->getPlugin()->getEconomyManager()->reducePlayerMoney($player->getName(), $auction->getPrice(), yield);
                        yield Await::ONCE;

                        if ($player->isConnected()) {
                            $this->removeAuction($auction->getId(), function (bool $success) use ($player, $auction): void {
                                if ($success) {
                                    $item = $auction->getItem();

                                    Inventory::addItemToPlayer($this->getPlugin(), $player->getName(), $item);

                                    $this->getPlugin()->getEconomyManager()->increasePlayerMoney($auction->getPlayer(), $auction->getPrice(), function () use ($player, $auction, $item): void {
                                        $this->getPlugin()->getEventEmitter()->broadcastMessage($auction->getPlayer(), TextFormat::AQUA . $player->getName() . TextFormat::GOLD . ' bought ' . TextFormat::GREEN . $item->getCount() . 'x ' . TextFormat::clean($item->getName()) . TextFormat::GOLD . ' from your auction house for ' . TextFormat::AQUA . '$' . number_format($auction->getPrice()) . TextFormat::GOLD . '.');
                                    });

                                    if ($auction->getPrice() > 25_000_000) {
                                        $this->getPlugin()->getLoggerStream()->add('**SUSPICIOUS TRANSACTION** - ' . $player->getName() . ' bought ' . $item->getCount() . 'x ' . TextFormat::clean($item->getName()) . ' from ' . $auction->getPlayer() . "'s auction house for " . $auction->getPrice() . ' coins');
                                    }

                                    if ($player->isConnected()) {
                                        $player->sendMessage(TextFormat::GREEN . 'You bought ' . TextFormat::GOLD . $item->getCount() . 'x ' . TextFormat::clean($item->getName()) . TextFormat::GREEN . ' for ' . TextFormat::GOLD . '$' . number_format($auction->getPrice()));
                                    }
                                } else {
                                    $this->getPlugin()->getEconomyManager()->increasePlayerMoney($player->getName(), $auction->getPrice());

                                    if ($player->isConnected()) {
                                        $player->sendMessage(TextFormat::RED . 'This auction no longer exists.');
                                    }
                                }
                            });
                        } else {
                            $this->getPlugin()->getEconomyManager()->increasePlayerMoney($player->getName(), $auction->getPrice());
                        }
                    } elseif ($action->getSlot() > 2) {
                        $player->removeCurrentWindow();

                        $this->sendAuctionMenu($player);
                    }
                });
            }));

            $menu->send($player);
        });
    }

    public function sendAuctionMenu(Player $player, int $filter = self::FILTER_NONE, string $filterData = '', ?InvMenu $menu = null): void
    {
        Await::f2c(function () use ($player, $filter, $filterData, $menu): Generator {
            switch ($filter) {
                case self::FILTER_BY_CATEGORY:
                    $this->getAuctionsInCategory($filterData, yield);
                    break;
                case self::FILTER_BY_ITEM_NAME:
                    $this->getAuctionsWithItemName($filterData, yield);
                    break;
                case self::FILTER_BY_PLAYER:
                    $this->getAuctionsFromPlayer($filterData, false, yield);
                    break;
                case self::FILTER_BY_PLAYER_WITH_EXPIRED:
                    $this->getAuctionsFromPlayer($filterData, true, yield);
                    break;
                default:
                    $this->getAllAuctions(yield);
                    break;
            }

            /** @var Auction[] $auctions */
            $auctions = yield Await::ONCE;

            $invalidAuctions = [];
            $validAuctions = [];

            foreach ($auctions as $auction) {
                RollbackEngine::isIllegalItem($auction->getItem()) ? $invalidAuctions[] = $auction : $validAuctions[] = $auction;
            }

            if ($player->isConnected()) {
                $this->sendAuctionHouse($player, 1, $filter, $filterData, $validAuctions, $menu);
            }

            foreach ($invalidAuctions as $invalidAuction) {
                $this->removeAuction($invalidAuction->getId(), function (bool $success) use ($invalidAuction): void {
                    if ($success) {
                        $this->getPlugin()->getEventEmitter()->broadcastMessage($invalidAuction->getPlayer(), TextFormat::RED . 'Your auction has been removed from the auction house due to an illegal item.');
                    }
                });
            }
        });
    }

    public function sendAuctionHouse(Player $player, int $page = 1, int $filter = self::FILTER_NONE, string $filterData = '', array $auctions = [], ?InvMenu $menu = null): void
    {
        $pages = array_chunk($auctions, self::AUCTIONS_NUMBER_PER_PAGE);
        $page = max(1, min($page, count($pages)));

        /** @var Auction[] $pagedAuctions */
        $pagedAuctions = $pages[$page - 1] ?? [];

        $items = array_map(function (Auction $auction): Item {
            return ($item = clone $auction->getItem())
                ->setCustomName(TextFormat::RESET . TextFormat::BOLD . $item->getName())
                ->setCustomBlockData(Utils::readOnlyTag())
                ->setLore(array_merge($item->getLore(), [
                    '',
                    TextFormat::RESET . TextFormat::AQUA . 'Seller: ' . TextFormat::WHITE . $auction->getPlayer(),
                    TextFormat::RESET . TextFormat::YELLOW . 'Price: ' . TextFormat::WHITE . '$' . number_format($auction->getPrice()),
                    TextFormat::RESET . ($auction->isExpired() ? TextFormat::BOLD . TextFormat::RED . 'EXPIRED' : TextFormat::GOLD . 'Expires: ' . TextFormat::WHITE . $this->formatTime($auction->getExpiration() - time())),
                    '',
                    TextFormat::RESET . TextFormat::GRAY . 'Click to confirm purchase.'
                ]));
        }, $pagedAuctions);

        $created = false;
        if ($menu === null) {
            $created = true;
            $menu = InvMenu::create(MMOPlugin::MENU_CHEST_DOUBLE);
        }

        $menu->setName('Auction House');
        $menu->getInventory()->setContents($items + [
                45 => $this->getBalanceItem($player),
                46 => $filter === self::FILTER_BY_PLAYER_WITH_EXPIRED ? VanillaBlocks::REDSTONE_WIRE()
                    ->asItem()
                    ->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . 'Go Back')
                    ->setCustomBlockData(Utils::readOnlyTag())
                    ->setLore([
                            '',
                            TextFormat::RESET . TextFormat::GRAY . 'Click to navigate to previous page.'
                        ]
                    ) : LibVanillaItems::CHEST_MINECART()
                    ->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::YELLOW . 'Your Auctions')
                    ->setCustomBlockData(Utils::readOnlyTag())
                    ->setLore([
                        '',
                        TextFormat::RESET . TextFormat::WHITE . 'View your sold auctions',
                        TextFormat::RESET . TextFormat::WHITE . 'and collect expired auctions here.',
                        '',
                        TextFormat::RESET . TextFormat::GRAY . 'Click to view your auction listings.'
                    ]),
                48 => $page > 1 ? VanillaItems::PAPER()
                    ->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::WHITE . 'Previous Page')
                    ->setCustomBlockData(Utils::readOnlyTag())
                    ->setLore([
                        '',
                        TextFormat::RESET . TextFormat::GRAY . 'You are currently on page ' . TextFormat::WHITE . $page . '/' . TextFormat::WHITE . count($pages),
                        '',
                        TextFormat::RESET . TextFormat::GRAY . 'Click to navigate to the previous page.'
                    ]) : VanillaBlocks::AIR()->asItem(),
                49 => $filter !== self::FILTER_BY_PLAYER_WITH_EXPIRED ? VanillaBlocks::HOPPER()->asItem()
                    ->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::DARK_GRAY . 'Change Filter')
                    ->setCustomBlockData(Utils::readOnlyTag())
                    ->setLore([
                        '',
                        TextFormat::RESET . TextFormat::YELLOW . 'Current filter: ' . TextFormat::WHITE . (self::FILTER_DISPLAYS[$filter] ?? 'Unknown') . ' (' . $filterData . ')',
                        '',
                        TextFormat::RESET . TextFormat::GRAY . 'Click to switch filter.'
                    ]) : VanillaBlocks::AIR()->asItem(),
                50 => $page < count($pages) ? VanillaItems::PAPER()
                    ->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::WHITE . 'Next Page')
                    ->setCustomBlockData(Utils::readOnlyTag())
                    ->setLore([
                        '',
                        TextFormat::RESET . TextFormat::GRAY . 'You are currently on page ' . TextFormat::WHITE . $page . '/' . TextFormat::WHITE . count($pages),
                        '',
                        TextFormat::RESET . TextFormat::GRAY . 'Click to navigate to the next page.'
                    ]) : VanillaBlocks::AIR()->asItem(),
                53 => VanillaItems::GOLD_NUGGET()
                    ->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GOLD . 'Sell Item In Hand')
                    ->setCustomBlockData(Utils::readOnlyTag())->setLore([
                        '',
                        TextFormat::RESET . TextFormat::GRAY . 'Click to sell the item you are currently holding.'
                    ])
            ]);
        $menu->setListener(InvMenu::readonly(function (DeterministicInvMenuTransaction $transaction) use ($page, $auctions, $pagedAuctions, $filter, $filterData, $menu): void {
            $player = $transaction->getPlayer();
            $itemClicked = $transaction->getItemClicked();
            $action = $transaction->getAction();

            if ($action->getSlot() < 45) {
                $auction = $pagedAuctions[$action->getSlot()] ?? null;

                if ($auction !== null) {
                    $player->removeCurrentWindow();

                    if ($filter === self::FILTER_BY_PLAYER_WITH_EXPIRED) {
                        $this->isValidAuction($auction->getId(), function (bool $valid) use ($player, $auction): void {
                            if (!$valid) {
                                $player->sendMessage(TextFormat::RED . 'This auction no longer exists.');
                                return;
                            }

                            if (!$player->isConnected()) {
                                return;
                            }

                            $item = $auction->getItem();
                            if ($player->getInventory()->canAddItem($item)) {
                                $this->removeAuction($auction->getId(), function (bool $success) use ($player, $item) {
                                    if ($success) {
                                        Inventory::addItemToPlayer($this->getPlugin(), $player->getName(), $item);

                                        if ($player->isConnected()) {
                                            $player->sendMessage(TextFormat::GREEN . 'Your item has been removed from auction.');
                                        }
                                    }
                                });
                            } else {
                                $player->sendMessage(TextFormat::RED . 'Your inventory is currently full!');
                            }
                        });
                    } else {
                        $this->getPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player, $auction): void {
                            $this->sendAuction($player, $auction->getId());
                        }), 20);
                    }
                }
            } else {
                switch ($action->getSlot()) {
                    case 46:
                        if ($itemClicked->getTypeId() === ItemTypeIds::REDSTONE_DUST) {
                            $this->sendAuctionMenu($player, self::FILTER_NONE, '', $menu);
                        } else {
                            $this->sendAuctionMenu($player, self::FILTER_BY_PLAYER_WITH_EXPIRED, $player->getName(), $menu);
                        }
                        break;
                    case 48:
                        if ($itemClicked->getTypeId() === ItemTypeIds::PAPER) {
                            $this->sendAuctionHouse($player, $page - 1, $filter, $filterData, $auctions, $menu);
                        }
                        break;
                    case 49:
                        if (ItemTypeIds::toBlockTypeId($itemClicked->getTypeId()) === BlockTypeIds::HOPPER) {
                            $player->removeCurrentWindow();

                            $this->getPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player): void {
                                if ($player->isConnected()) {
                                    $this->sendFilterForm($player);
                                }
                            }), 20);
                        }
                        break;
                    case 50:
                        if ($itemClicked->getTypeId() === ItemTypeIds::PAPER) {
                            $this->sendAuctionHouse($player, $page + 1, $filter, $filterData, $auctions, $menu);
                        }
                        break;
                    case 53:
                        $player->removeCurrentWindow();

                        $this->getPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player): void {
                            if ($player->isConnected()) {
                                $form = FormManager::createCustomForm($player);

                                if ($form === null) {
                                    $player->sendMessage(TextFormat::RED . 'An unexpected error occurred while opening the form. Please try again later.');
                                } else {
                                    $form->setTitle('Sell Item');

                                    $form->addElement(new Label('Enter a price to auction the item in your hand:'));
                                    $form->addElement(new Input('Sell Price', '100', '', function (Player $player, string $value) {
                                        $this->getPlugin()->getServer()->dispatchCommand($player, 'ah sell ' . $value);
                                    }));

                                    $form->sendForm();
                                }
                            }
                        }), 20);
                        break;
                }
            }
        }));

        if ($created) {
            $menu->send($player);
        }
    }

    public function sendFilterForm(Player $player): void
    {
        $form = FormManager::createSimpleForm($player);
        if ($form === null) {
            $player->sendMessage(TextFormat::RED . 'An unexpected error occurred while opening the form. Please try again later.');
            return;
        }
        $form->setTitle('Auction House Filter');
        $form->setContent('Select a filter to apply:');
        $form->addButton(new Button('By Category', function (Player $player) {
            $this->sendCategorySelectionForm($player);
        }));
        $form->addButton(new Button('By Item Name', function (Player $player) {
            $this->sendItemNameForm($player);
        }));
        $form->addButton(new Button('By Player', function (Player $player) {
            $this->sendPlayerNameForm($player);
        }));
        $form->addButton(new ImageButton(TextFormat::RED . 'Back', ImageButton::IMAGE_TYPE_PATH, 'textures/blocks/barrier', function (Player $player) {
            $this->sendAuctionMenu($player);
        }));

        $form->sendForm();
    }

    public function sendCategorySelectionForm(Player $player): void
    {
        $form = FormManager::createSimpleForm($player);
        if ($form === null) {
            $player->sendMessage(TextFormat::RED . 'An unexpected error occurred while opening the form. Please try again later.');
            return;
        }
        $form->setTitle('Filter by Category');
        $form->setContent('Select a category to search through:');
        foreach ($this->categories as $category) {
            $form->addButton(new Button($category->getName(), function (Player $player) use ($category): void {
                $this->getPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player, $category): void {
                    if ($player->isConnected()) {
                        $this->sendAuctionMenu($player, self::FILTER_BY_CATEGORY, $category->getName());
                    }
                }), 20);
            }));
        }
        $form->addButton(new Button('Miscellaneous', function (Player $player) {
            $this->getPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player): void {
                if ($player->isConnected()) {
                    $this->sendAuctionMenu($player, self::FILTER_BY_CATEGORY, 'misc');
                }
            }), 20);
        }));
        $form->addButton(new ImageButton(TextFormat::RED . 'Back', ImageButton::IMAGE_TYPE_PATH, 'textures/blocks/barrier', function (Player $player) {
            $this->sendFilterForm($player);
        }));

        $form->sendForm();
    }

    public function sendItemNameForm(Player $player): void
    {
        $form = FormManager::createCustomForm($player);
        if ($form === null) {
            $player->sendMessage(TextFormat::RED . 'An unexpected error occurred while opening the form. Please try again later.');
            return;
        }
        $form->setTitle('Filter by Item Name');
        $form->addElement(new Label('Search for items which include the name below:'));
        $form->addElement(new Input('Item Name', 'Diamond Sword', '', function (Player $player, string $value) {
            $this->getPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player, $value): void {
                if ($player->isConnected()) {
                    $this->sendAuctionMenu($player, self::FILTER_BY_ITEM_NAME, $value);
                }
            }), 20);
        }));

        $form->sendForm();
    }

    public function sendPlayerNameForm(Player $player): void
    {
        $form = FormManager::createCustomForm($player);
        if ($form === null) {
            $player->sendMessage(TextFormat::RED . 'An unexpected error occurred while opening the form. Please try again later.');
            return;
        }
        $form->setTitle('Filter by Player');
        $form->addElement(new Label('Search for items from a specific player:'));
        $form->addElement(new Input('Player', 'Steve', '', function (Player $player, string $value) {
            $this->getPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player, $value): void {
                $this->sendAuctionMenu($player, self::FILTER_BY_PLAYER, $value);
            }), 20);
        }));

        $form->sendForm();
    }

    protected function formatTime(int $time): string
    {
        $time = max(0, $time);
        $days = floor($time / 86400);
        $hours = floor($time / 3600) % 24;
        $minutes = floor($time / 60) % 60;
        $seconds = $time % 60;
        $formatted = trim(($days > 0 ? $days . 'd ' : '') . ($hours > 0 ? $hours . 'h ' : '') . ($minutes > 0 ? $minutes . 'm ' : '') . ($seconds > 0 ? $seconds . 's' : ''));
        return $formatted === '' ? '0s' : $formatted;
    }
}