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

use InvalidArgumentException;
use libforms\elements\Button;
use libforms\FormManager;
use libMMO\economy\EconomyManager;
use libMMO\MMOPlugin;
use libMMO\utils\Utils;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use function strtolower;

class Shop
{
    /** @var Category[] */
    private static array $categories = [];
    /** @var string */
    private string $title;
    /** @var EconomyManager */
    private EconomyManager $economyManager;

    public function __construct(EconomyManager $economyManager, string $title = 'Shop')
    {
        $this->title = $title;
        $this->economyManager = $economyManager;
    }

    /**
     * Searches every category for the price of an item.
     *
     * @param Item $item
     * @return int
     */
    public static function getBuyPrice(Item $item): int
    {
        foreach (self::$categories as $category) {
            if (($price = $category->getBuyPrice($item)) !== 0) {
                return $price;
            }
        }

        return 0;
    }

    /**
     * Searches every category for the value of an item.
     *
     * @param Item $item
     * @return int
     */
    public static function getSellPrice(Item $item): int
    {
        foreach (self::$categories as $category) {
            if (($price = $category->getSellPrice($item)) !== 0) {
                return $price;
            }
        }

        return 0;
    }

    /**
     * Searches for a specific category, returns null if not found.
     *
     * @param string $name
     * @return Category|null
     */
    public static function getCategory(string $name): ?Category
    {
        return self::$categories[strtolower($name)] ?? null;
    }

    /**
     * Adds a category to the shop.
     *
     * @param string $name
     * @param Item $item
     * @param ShopItem[] $items
     * @param bool $levelCategory
     * @param callable|null $purchaseCallback A callable function that returns all the items the player is purchasing with callback: <code>function({@link Item} $items) : Item[]{}</code>
     */
    public function addCategory(string $name, Item $item, array $items, bool $levelCategory = false, ?callable $purchaseCallback = null): void
    {
        if (isset(self::$categories[strtolower($name)])) {
            return;
        }

        self::$categories[strtolower($name)] = new Category($name, $item, $items, $this, $this->economyManager, $levelCategory, $purchaseCallback);
    }

    /**
     * Sends the chest UI to the player.
     *
     * @param Player $player
     * @param bool $useForm
     * @param InvMenu|null $shop
     */
    public function send(Player $player, bool $useForm = false, ?InvMenu $shop = null): void
    {
        if ($useForm) {
            $form = FormManager::createSimpleForm($player);

            if ($form !== null) {
                $form->setTitle($this->title);
                foreach ($this->getCategories() as $category) {
                    try {
                        $form->addButton(new Button($category->getTitle(), static function () use ($category, $player) {
                            $category->send($player, true);
                        }));
                    } catch (InvalidArgumentException $exception) {
                        Server::getInstance()->getLogger()->logException($exception);
                    }
                }

                $form->sendForm();
            }
        } else {
            $created = false;
            if ($shop === null) {
                $created = true;
                $shop = InvMenu::create(MMOPlugin::MENU_CHEST_DOUBLE);
            }
            $shop->setName($this->title);

            $contents = [];

            foreach ($this->getCategories() as $category) {
                try {
                    $item = $category->getPlaceholderItem();
                    $item->setCustomName(TextFormat::RESET . TextFormat::WHITE . $category->getTitle());
                    $item->getNamedTag()->setString('category', $category->getTitle());
                    $item->setCustomBlockData(Utils::readOnlyTag());

                    $contents[] = $item;
                } catch (InvalidArgumentException $exception) {
                    Server::getInstance()->getLogger()->logException($exception);
                }
            }

            $shop->getInventory()->setContents($contents);

            $shop->setListener(InvMenu::readonly(static function (DeterministicInvMenuTransaction $transaction) use ($shop): void {
                $player = $transaction->getPlayer();
                $itemClicked = $transaction->getItemClicked();

                if (Utils::hasTag($itemClicked->getNamedTag(), 'category')) {
                    self::getCategory($itemClicked->getNamedTag()->getString('category'))->send($player, false, $shop);
                }
            }));

            if ($created) {
                $shop->send($player);
            }
        }
    }

    /**
     * Returns every shop category.
     *
     * @return Category[]
     */
    public function getCategories(): array
    {
        return self::$categories;
    }
}
