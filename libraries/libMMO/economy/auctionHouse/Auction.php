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

use JsonException;
use libMMO\utils\Utils;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;

class Auction
{
    /** @var int */
    private int $id;
    /** @var string */
    private string $player;
    /** @var string */
    private string $category;
    /** @var string */
    private string $itemName;
    /** @var string */
    private string $itemJson;
    /** @var Item|null */
    private ?Item $item = null;
    /** @var int */
    private int $price;
    /** @var int */
    private int $expires;

    public function __construct(int $id, string $player, string $category, string $itemName, string $itemNbt, int $price, int $expires)
    {
        $this->id = $id;
        $this->player = $player;
        $this->category = $category;
        $this->itemName = $itemName;
        $this->itemJson = $itemNbt;
        $this->price = $price;
        $this->expires = $expires;
    }

    /**
     * Creates a new Auction object using data based on the auctions table.
     * @param array $row
     * @return Auction
     */
    public static function fromDatabase(array $row): Auction
    {
        return new self($row['auction_id'], $row['player'], $row['category'], $row['item_name'], $row['item_json'], $row['price'], $row['expires']);
    }

    /**
     * Returns the id of the auction from the database.
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Returns the name of the player who created the auction.
     * @return string
     */
    public function getPlayer(): string
    {
        return $this->player;
    }

    /**
     * Returns the name of the category the item is in.
     * @return string
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * Returns the name of the item, including colors etc.
     * @return string
     */
    public function getItemName(): string
    {
        return $this->itemName;
    }

    /**
     * Returns the serialized NBT of the item auctioned.
     * @return string
     */
    public function getItemJson(): string
    {
        return $this->itemJson;
    }

    /**
     * Returns the deserialized item json in the form of an Item.
     * @return Item
     */
    public function getItem(): Item
    {
        if ($this->item === null) {
            try {
                $this->item = Utils::decodeItem($this->itemJson);
            } catch (JsonException $exception) {
                $this->item = VanillaItems::AIR();
            }
        }

        return $this->item;
    }

    /**
     * Returns the price the auction is being sold for.
     * @return int
     */
    public function getPrice(): int
    {
        return $this->price;
    }

    /**
     * Returns whether or not the auction has expired.
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expires <= time();
    }

    /**
     * Returns the time at which the auction will expire
     * @return int
     */
    public function getExpiration(): int
    {
        return $this->expires;
    }
}