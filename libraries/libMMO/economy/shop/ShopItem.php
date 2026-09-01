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

use pocketmine\item\Item;

class ShopItem
{
    /** @var int */
    private static int $shopUniqueId = 0;

    /** @var Item */
    private Item $item;
    /** @var int */
    private int $buyPrice;
    /** @var int */
    private int $sellPrice;
    /** @var string|null */
    private ?string $previewTitle;

    public function __construct(Item $item, int $buyPrice, int $sellPrice = 0, ?string $previewTitle = null)
    {
        $this->item = $item;
        $this->buyPrice = $buyPrice;
        $this->sellPrice = $sellPrice;
        $this->previewTitle = $previewTitle;

        $this->item->setNamedTag($item->getNamedTag()->setInt('ShopUniqueId', self::$shopUniqueId++));
    }

    public function getFormTitle() : string
    {
        return $this->previewTitle ?? $this->getItem()->getVanillaName();
    }

    public function getItem(): Item
    {
        return clone $this->item;
    }

    public function getCleanItem(): Item
    {
        $item = $this->getItem();
        ($tag = $item->getNamedTag())->removeTag('ShopUniqueId');
        $item->setNamedTag($tag);

        return $item;
    }

    public function equals(Item $item): bool
    {
        return $this->item->equals($item, true, false);
    }

    public function getBuyPrice(): int
    {
        return $this->buyPrice;
    }

    public function getSellPrice(): int
    {
        return $this->sellPrice;
    }


}