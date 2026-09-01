<?php
/**
 *         _____ _          _     _            _
 *        / ____| |        | |   | |          | |
 *  __  _| (___ | | ___   _| |__ | | ___   ___| | __
 *  \ \/ /\___ \| |/ / | | | '_ \| |/ _ \ / __| |/ /
 *   >  < ____) |   <| |_| | |_) | | (_) | (__|   <
 *  /_/\_\_____/|_|\_\\__, |_.__/|_|\___/ \___|_|\_\
 *                     __/ |
 *                    |___/
 *
 * Copyright (C) 2016-2022 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew
 *
 */
declare(strict_types=1);

namespace skyblock\economy\auctionhouse;

use JsonException;
use libMMO\economy\auctionHouse\Auction;
use libMMO\MMOPlugin;
use libMMO\utils\Utils;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use poggit\libasynql\SqlError;
use skyblock\economy\auctionhouse\category\ArmorCategory;
use skyblock\economy\auctionhouse\category\BlocksCategory;
use skyblock\economy\auctionhouse\category\SpawnersCategory;
use skyblock\economy\auctionhouse\category\ToolsCategory;
use skyblock\economy\auctionhouse\category\WeaponsCategory;
use skyblock\utils\Database;
use function array_map;
use function json_encode;
use function strtolower;
use function zstd_compress;

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
        }, static function (SqlError $error) use ($callable): void {
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
        }, static function (SqlError $error) use ($callable): void {
            $callable([]);
        });
    }

    public function getAuctionFromId(int $auctionId, callable $callable): void
    {
        Database::executeSelect(Database::GET_AUCTION_BY_ID, ['auction_id' => $auctionId], static function (array $rows) use ($callable): void {
            if (isset($rows[0])) {
                $callable(Auction::fromDatabase($rows[0]));
            } else {
                $callable(null);
            }
        }, static function (SqlError $error) use ($callable): void {
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
        }, static function (SqlError $error) use ($callable): void {
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
        }, static function (SqlError $error) use ($callable): void {
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

        try {
            Database::executeInsert(Database::ADD_AUCTION, [
                'player' => $player->getName(),
                'category' => $itemCategory,
                'item_name' => TextFormat::clean($item->getName()),
                'item_json' => Utils::zlibEncodeItem($item),
                'price' => $price,
                'expires' => time() + $auctionLength
            ]);

            return true;
        } catch (JsonException $e) {

        }

        return false;
    }

    public function isValidAuction(int $auctionId, callable $callable): void
    {
        Database::executeSelect(Database::GET_AUCTION_BY_ID, ['auction_id' => $auctionId], static function (array $rows) use ($callable): void {
            $callable(count($rows) > 0);
        }, static function (SqlError $error) use ($callable): void {
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