<?php
/**
 *        ______         _   _
 *       |  ____|       | | (_)
 *  __  _| |__ __ _  ___| |_ _  ___  _ __  ___
 *  \ \/ /  __/ _` |/ __| __| |/ _ \| '_ \/ __|
 *   >  <| | | (_| | (__| |_| | (_) | | | \__ \
 *  /_/\_\_|  \__,_|\___|\__|_|\___/|_| |_|___/
 *
 * Copyright (C) 2016-2021 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author larryTheCoder
 */

declare(strict_types=1);

namespace factions\economy\auctionhouse;

use factions\economy\auctionhouse\category\ArmorCategory;
use factions\economy\auctionhouse\category\BlocksCategory;
use factions\economy\auctionhouse\category\SpawnersCategory;
use factions\economy\auctionhouse\category\ToolsCategory;
use factions\economy\auctionhouse\category\WeaponsCategory;
use factions\utils\Database;
use libMMO\economy\auctionHouse\Auction;
use libMMO\MMOPlugin;
use libMMO\player\PlayerData;
use libMMO\utils\Utils;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use poggit\libasynql\SqlError;
use function array_map;
use function strtolower;

class AuctionHouse extends \libMMO\economy\auctionHouse\AuctionHouse
{

    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct($plugin);

        $this->addCategory(new ArmorCategory());
        $this->addCategory(new BlocksCategory());
        $this->addCategory(new SpawnersCategory());
        $this->addCategory(new ToolsCategory());
        $this->addCategory(new WeaponsCategory());
    }

    public function getAllAuctions(callable $callable): void
    {
        Database::executeSelect(Database::GET_ALL_AUCTIONS, [], static function (array $rows) use ($callable): void {
            $auctions = array_map(static function (array $row): Auction {
                return Auction::fromDatabase($row);
            }, $rows);
            $callable($auctions);
        }, static function () use ($callable): void {
            $callable([]);
        });
    }

    public function getAuctionsWithItemName(string $name, callable $callable): void
    {
        Database::executeSelect(Database::GET_AUCTIONS_FROM_ITEM_NAME, ['item_name' => $name], static function (array $rows) use ($callable): void {
            $auctions = array_map(static function (array $row): Auction {
                return Auction::fromDatabase($row);
            }, $rows);
            $callable($auctions);
        }, static function () use ($callable): void {
            $callable([]);
        });
    }

    public function getAuctionFromId(int $id, callable $callable): void
    {
        Database::executeSelect(Database::GET_AUCTION_BY_ID, ['auction_id' => $id], static function (array $rows) use ($callable): void {
            if (isset($rows[0])) {
                $callable(Auction::fromDatabase($rows[0]));
            } else {
                $callable(null);
            }
        }, static function () use ($callable): void {
            $callable(null);
        });
    }

    public function getAuctionsFromPlayer(string $player, bool $includeExpired, callable $callable): void
    {
        Database::executeSelect($includeExpired ? Database::GET_ALL_AUCTIONS_FROM_PLAYER : Database::GET_AUCTIONS_FROM_PLAYER, ['player' => $player], static function (array $rows) use ($callable): void {
            $auctions = array_map(static function (array $row): Auction {
                return Auction::fromDatabase($row);
            }, $rows);
            $callable($auctions);
        }, static function () use ($callable): void {
            $callable([]);
        });
    }

    public function getAuctionsInCategory(string $category, callable $callable): void
    {
        Database::executeSelect(Database::GET_AUCTIONS_IN_CATEGORY, ['category' => $category], static function (array $rows) use ($callable): void {
            $auctions = array_map(static function (array $row): Auction {
                return Auction::fromDatabase($row);
            }, $rows);
            $callable($auctions);
        }, static function () use ($callable): void {
            $callable([]);
        });
    }

    public function sellItem(Player $player, Item $item, int $price, int $auctionLength): bool
    {
        $itemCategory = 'misc';
        foreach ($this->categories as $category) {
            if ($category->validateItem($item)) {
                $itemCategory = strtolower($category->getName());
            }
        }

        Database::executeInsert(Database::ADD_AUCTION, [
            'player' => $player->getName(),
            'category' => $itemCategory,
            'item_name' => TextFormat::clean($item->getName()),
            'item_json' => Utils::zlibEncodeItem($item),
            'price' => $price,
            'expires' => time() + $auctionLength
        ]);

        return true;
    }

    protected function getBalanceItem(Player $player): Item
    {
        return VanillaBlocks::SUNFLOWER()->asItem()->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GOLD . 'Your Balance')->setLore([
            '',
            TextFormat::RESET . TextFormat::AQUA . 'Coins: ' . TextFormat::WHITE . number_format($this->getPlugin()->getPlayerData()->getInt($player, PlayerData::PLAYER_MONEY)),
        ]);
    }

    public function isValidAuction(int $auctionId, callable $callable): void
    {
        Database::executeSelect(Database::GET_AUCTION_BY_ID, ['auction_id' => $auctionId], static function (array $rows) use ($callable): void {
            $callable(count($rows) > 0);
        }, static function () use ($callable): void {
            $callable(false);
        });
    }

    public function removeAuction(int $auctionId, ?callable $onSuccess = null): void
    {
        Database::executeChange(Database::REMOVE_AUCTION, ['auction_id' => $auctionId], function (int $affectedRows) use ($onSuccess): void {
            if ($onSuccess !== null) {
                $onSuccess($affectedRows > 0);
            }
        }, function (SqlError $result) use ($onSuccess): void {
            if ($onSuccess !== null) {
                $onSuccess(false);
            }
        });
    }
}