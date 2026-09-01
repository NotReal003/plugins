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

use libforms\elements\Button;
use libforms\elements\ImageButton;
use libforms\FormManager;
use libMMO\economy\EconomyManager;
use libMMO\MMOPlugin;
use libMMO\utils\Utils;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\IntTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class Category
{
    /** @var string */
    private string $title;
    /** @var Item */
    private Item $placeholderItem;
    /** @var ShopItem[][] */
    private array $items;
    /** @var Shop */
    private Shop $shop;
    /** @var EconomyManager */
    private EconomyManager $economyManager;
    /** @var bool */
    private bool $xpCategory;
    /** @var callable|null */
    private $itemCallback;

    /**
     * When adding an array of items, please make sure it is like this:
     * ["4:0" => [1, 0]], that is id:meta, with buy and sell price, using "dirt:0" will NOT work.
     *
     * @param string $title
     * @param Item $placeholderItem
     * @param ShopItem[] $items
     * @param Shop $shop
     * @param EconomyManager $economyManager
     * @param bool $xpCategory
     * @param callable|null $purchaseCallback
     */
    public function __construct(string $title, Item $placeholderItem, array $items, Shop $shop, EconomyManager $economyManager, bool $xpCategory = false, ?callable $purchaseCallback = null)
    {
        $this->title = $title;
        $this->placeholderItem = $placeholderItem;
        $this->shop = $shop;
        $this->economyManager = $economyManager;
        $this->xpCategory = $xpCategory;
        $this->itemCallback = $purchaseCallback;

        foreach ($items as $entry) {
            $item = $entry->getItem();

            $this->items[$item->getStateId()][] = $entry;
        }
    }

    /**
     * The item that is displayed for the category.
     *
     * @return Item
     */
    public function getPlaceholderItem(): Item
    {
        return $this->placeholderItem;
    }

    /**
     * Sends the chest UI to the player.
     *
     * @param Player $player
     * @param bool $useForm
     * @param InvMenu|null $category
     * @param int $pageNumber
     */
    public function send(Player $player, bool $useForm = false, ?InvMenu $category = null, int &$pageNumber = 1): void
    {
        if ($useForm) {
            $form = FormManager::createSimpleForm($player);

            if ($form !== null) {
                $form->setTitle($this->title);
                foreach ($this->items as $entry) {
                    foreach ($entry as $shopItem) {
                        $form->addButton(new Button($shopItem->getFormTitle(), function () use ($player, $shopItem) {
                            $transaction = new Transaction($this, $shopItem, $this->economyManager);
                            $transaction->send($player, true);
                        }));
                    }
                }
                $form->addButton(new ImageButton(TextFormat::RED . 'Back', ImageButton::IMAGE_TYPE_PATH, 'textures/blocks/barrier', function (Player $player) {
                    $this->shop->send($player, true);
                }));

                $form->sendForm();
            }
        } else {
            $created = false;
            if ($category === null) {
                $created = true;
                $category = InvMenu::create(MMOPlugin::MENU_CHEST_DOUBLE);
            }
            $category->setName($this->title);


            $target = [];
            foreach ($this->items as $entry) {
                foreach ($entry as $item) {
                    $target[] = $item;
                }
            }

            $moreEntries = count($target) > 45;

            $contents = [];
            if ($moreEntries) {
                $items = array_chunk($target, 45);
                $pageNumber = min(count($items), $pageNumber);
                if ($pageNumber < 1) {
                    $pageNumber = 1;
                }

                $target = $items[$pageNumber - 1];
            }

            foreach ($target as $shopItem) {
                $item = $shopItem->getItem();
                $item->setCustomName(TextFormat::RESET . TextFormat::BOLD . $item->getName())->setLore(array_merge($item->getLore(), [
                    '',
                    TextFormat::RESET . TextFormat::GREEN . 'Buy: ' . TextFormat::WHITE . (!$this->isXpCategory() ? ('$' . $shopItem->getBuyPrice()) : ($shopItem->getBuyPrice() . ' xp levels')),
                    TextFormat::RESET . TextFormat::RED . 'Sell: ' . (($price = $shopItem->getSellPrice()) !== 0 ? (!$this->isXpCategory() ? (TextFormat::WHITE . '$' . $price) : TextFormat::WHITE . $price . ' xp levels') : TextFormat::RED . 'Not Sellable'),
                    '',
                    TextFormat::RESET . TextFormat::GRAY . 'Click to buy or sell this item.'
                ]));
                if ($item->getCustomBlockData() === null) {
                    $item->setCustomBlockData(Utils::readOnlyTag());
                } else {
                    $item->getCustomBlockData()->setInt(Utils::READONLY_TAGS, 0);
                }
                $contents[] = $item;
            }

            $category->getInventory()->setContents($contents);

            $backIndex = 53;
            if ($moreEntries) {
                $next = VanillaBlocks::WOOL()->setColor(DyeColor::GREEN)->asItem();
                $next->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GREEN . 'Next')->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to see the next entry.']);
                $next->getNamedTag()->setInt('next', 1);
                $next->setCustomBlockData(Utils::readOnlyTag());
                $category->getInventory()->setItem(53, $next);

                $prev = VanillaBlocks::WOOL()->setColor(DyeColor::GRAY)->asItem();
                $prev->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GRAY . 'Previous')->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to see the previous entry.']);
                $prev->getNamedTag()->setInt('previous', 1);
                $prev->setCustomBlockData(Utils::readOnlyTag());
                $category->getInventory()->setItem(52, $prev);

                $backIndex = 45;
            }

            $back = VanillaBlocks::CONCRETE()->setColor(DyeColor::RED)->asItem();
            $back->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . 'Back')->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to see all categories.']);
            $back->getNamedTag()->setInt('back', 1);
            $back->setCustomBlockData(Utils::readOnlyTag());
            $category->getInventory()->setItem($backIndex, $back);

            $category->setListener(InvMenu::readonly(function (DeterministicInvMenuTransaction $transaction) use ($category, $pageNumber): void {
                $player = $transaction->getPlayer();
                $itemClicked = $transaction->getItemClicked();
                if (Utils::hasTag($itemClicked->getNamedTag(), 'back')) {
                    $this->shop->send($player, false, $category);
                } elseif (Utils::hasTag($itemClicked->getNamedTag(), 'next')) {
                    $pageNumber = $pageNumber + 1;

                    $this->send($player, false, $category, $pageNumber);
                } elseif (Utils::hasTag($itemClicked->getNamedTag(), 'previous')) {
                    $pageNumber = $pageNumber - 1;

                    $this->send($player, false, $category, $pageNumber);
                } elseif (Utils::hasTag($itemClicked->getNamedTag(), 'ShopUniqueId')) {
                    $transactionMenu = new Transaction($this, $itemClicked, $this->economyManager);
                    $transactionMenu->send($player, false, $category);
                }
            }));

            if ($created) {
                $category->send($player);
            }
        }
    }

    /**
     * Returns whether the items in the category are bought with xp levels or money
     *
     * @return bool
     */
    public function isXpCategory(): bool
    {
        return $this->xpCategory;
    }

    /**
     * The name of the chest UI.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Gets the price of an item within the category.
     *
     * @param Item $item
     * @return int
     */
    public function getBuyPrice(Item $item): int
    {
        $itemUniqueId = $item->getNamedTag()->getInt('ShopUniqueId');

        $entries = $this->items[$item->getStateId()] ?? [];
        foreach ($entries as $shopItem) {
            if ($shopItem->getItem()->getNamedTag()->getInt('ShopUniqueId') === $itemUniqueId) {
                return $shopItem->getBuyPrice();
            }
        }

        return 0;
    }

    public function getOriginalItem(Item $item): Item
    {
        $itemUniqueId = $item->getNamedTag()->getInt('ShopUniqueId');

        $entries = $this->items[$item->getStateId()] ?? [];
        foreach ($entries as $shopItem) {
            if ($shopItem->getItem()->getNamedTag()->getInt('ShopUniqueId') === $itemUniqueId) {
                return $shopItem->getCleanItem();
            }
        }

        return VanillaItems::AIR();
    }

    /**
     * Gets the value of an item within the category.
     *
     * @param Item $item
     * @return int
     */
    public function getSellPrice(Item $item): int
    {
        $entries = $this->items[$item->getStateId()] ?? [];

        if (Utils::hasTag($item->getNamedTag(), 'ShopUniqueId', IntTag::class)) {
            $itemUniqueId = $item->getNamedTag()->getInt('ShopUniqueId');

            foreach ($entries as $shopItem) {
                if ($shopItem->getItem()->getNamedTag()->getInt('ShopUniqueId') === $itemUniqueId) {
                    return $shopItem->getSellPrice();
                }
            }

            return 0;
        }

        foreach ($entries as $shopItem) {
            if ($shopItem->equals($item)) {
                return $shopItem->getSellPrice();
            }
        }

        return 0;
    }

    public function getItemCallback(Item $item): array
    {
        return $this->itemCallback !== null ? ($this->itemCallback)($item) : [$item];
    }
}