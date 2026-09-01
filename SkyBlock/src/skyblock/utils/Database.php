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

namespace skyblock\utils;

use NetherGames\NGEssentials\NGEssentials;
use pocketmine\utils\Config;
use skyblock\SkyBlock;
use function getenv;

class Database extends \libMMO\utils\Database
{
    public const ADD_AUCTION = 'auctionhouse.add_auction';
    public const GET_ALL_AUCTIONS = 'auctionhouse.get_all';
    public const GET_AUCTION_BY_ID = 'auctionhouse.get_by_id';
    public const GET_AUCTIONS_FROM_ITEM_NAME = 'auctionhouse.get_from_item_name';
    public const GET_ALL_AUCTIONS_FROM_PLAYER = 'auctionhouse.get_all_from_player';
    public const GET_AUCTIONS_FROM_PLAYER = 'auctionhouse.get_from_player';
    public const GET_AUCTIONS_IN_CATEGORY = 'auctionhouse.get_in_category';
    public const REMOVE_AUCTION = 'auctionhouse.remove_auction';
    public const GET_ISLAND = 'skyblock.get_island';
    public const GET_ISLAND_LOCATION = 'skyblock.get_island_location';
    public const CREATE_ISLAND = 'skyblock.create';
    public const REMOVE_ISLAND = 'skyblock.remove_island';
    public const SET_ISLAND = 'skyblock.set_island';
    public const SET_ISLAND_PUBLIC = 'skyblock.set_island_public';
    public const GET_PUBLIC_ISLANDS = 'skyblock.get_public_islands';

    public const GET_HELPERS_REMAINDER = 'helpers.get_remainder';
    public const SET_HELPERS_REMAINDER = 'helpers.set_remainder';

    public function __construct(SkyBlock $plugin, Config $config)
    {
        $path = 'sb_database.';

        if (NGEssentials::isInDevelopmentMode()) {
            $host = $config->getNested($path . 'host');
            $user = $config->getNested($path . 'username');
            $password = $config->getNested($path . 'password');
            $schema = $config->getNested($path . 'schema');
        } else {
            $host = getenv('SB_HOST');
            $user = getenv('SB_USER');
            $password = getenv('SB_PASSWORD');
            $schema = getenv('SB_SCHEMA');
        }

        parent::__construct($plugin, [
            $host,
            $user,
            $password,
            $schema
        ]);
    }
}