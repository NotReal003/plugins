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

namespace factions\utils;

use factions\Factions;
use NetherGames\NGEssentials\NGEssentials;
use pocketmine\utils\Config;
use poggit\libasynql\base\DataConnectorImpl;
use poggit\libasynql\base\SqlThreadPool;
use function getenv;

class Database extends \libMMO\utils\Database
{
    public const PLAYER_ECONOMY_TRANSACTION = 'player.economy_transaction';
    public const FACTION_ECONOMY_TRANSACTION = 'factions.economy_transaction';

    public const ADD_AUCTION = 'auctionhouse.add_auction';
    public const GET_ALL_AUCTIONS = 'auctionhouse.get_all';
    public const GET_AUCTION_BY_ID = 'auctionhouse.get_by_id';
    public const GET_AUCTIONS_FROM_ITEM_NAME = 'auctionhouse.get_from_item_name';
    public const GET_ALL_AUCTIONS_FROM_PLAYER = 'auctionhouse.get_all_from_player';
    public const GET_AUCTIONS_FROM_PLAYER = 'auctionhouse.get_from_player';
    public const GET_AUCTIONS_IN_CATEGORY = 'auctionhouse.get_in_category';
    public const REMOVE_AUCTION = 'auctionhouse.remove_auction';

    // These data is separated in order to get the consistence factions data.
    // It is related to the main faction table, in case it gets deleted, all
    // child tables will also be removed from entry.
    public const FACTION_CREATE = 'factions.create';

    public const GET_PLAYER_FACTION_ID = 'factions.get_faction_id';
    public const GET_FACTION_METADATA = 'factions.get_faction_meta';
    public const GET_FACTIONS_MEMBER = 'factions.get_members';
    public const GET_FACTIONS_ALLIES = 'factions.get_allies';
    public const GET_FACTIONS_ALLIES_COUNT = 'factions.get_allies_count';
    public const GET_FACTIONS_BY_NAME = 'factions.select_by_name';
    public const GET_FACTIONS_SPECIFIC = 'factions.faction_select';
    public const GET_FACTION_COUNT = 'factions.get_faction_count';

    public const TRACK_FACTION_DEATHS = 'factions.increase_death_tracking';
    public const TRACK_FACTION_KILLS = 'factions.decrease_death_tracking';

    public const REMOVE_FACTION_ALLY = 'factions.remove_allies';
    public const REMOVE_FACTION_PLAYER = 'factions.remove_member';

    public const UPDATE_FACTION_NAME = 'factions.set_faction_name';
    public const UPDATE_FACTION_DEATHS = 'factions.inc_factions_kills';
    public const UPDATE_FACTION_LEADER = 'factions.update_leader';
    public const UPDATE_SET_FACTION_LEADER = 'factions.set_leader';
    public const UPDATE_FACTION_VAULTS_OPEN = 'factions.faction_vault_open';
    public const UPDATE_FACTION_VAULTS_CLOSE = 'factions.faction_vault_close';
    public const UPDATE_FACTION_MOTD = 'factions.update_motd';

    public const ADD_FACTION_PLAYER = 'factions.add_member', SET_FACTION_ROLE = self::ADD_FACTION_PLAYER;
    public const ADD_FACTION_ALLY = 'factions.add_allies';

    public const STRENGTH_INCREASE = 'factions.increase_strength';
    public const STRENGTH_DECREASE = 'factions.decrease_strength';
    public const STRENGTH_GET = 'factions.get_strength';

    public const SET_FACTION_HOME = 'factions.update_home_cords';
    public const UNSET_FACTION_HOME = 'factions.delete_home_cords';

    public const TOP_FACTIONS = 'factions.strongest_faction';
    public const FACTION_RANKING = 'factions.faction_rank';
    public const FACTIONS_DELETE_ENDPOINT = 'factions.delete_old_kills';

    public const CLAIMS_GET_DATA = 'claims.load_claims';
    public const CLAIMS_ADD_DATA = 'claims.add_claims';
    public const CLAIMS_OVERCLAIM_DATA = 'claims.overclaim';
    public const CLAIMS_DELETE_DATA = 'claims.remove_claim';

    public function __construct(Factions $plugin, Config $config)
    {
        $path = 'fc_database.';

        if (NGEssentials::isInDevelopmentMode()) {
            $host = $config->getNested($path . 'host');
            $user = $config->getNested($path . 'username');
            $password = $config->getNested($path . 'password');
            $schema = $config->getNested($path . 'schema');
        } else {
            $host = getenv('FACTIONS_HOST');
            $user = getenv('FACTIONS_USER');
            $password = getenv('FACTIONS_PASSWORD');
            $schema = getenv('FACTIONS_SCHEMA');
        }

        parent::__construct($plugin, [$host, $user, $password, $schema]);

        /** @var DataConnectorImpl $database */
        $database = self::getMySQLDatabase();

        /** @var SqlThreadPool $threadPool */
        $threadPool = (function () {
            /** @noinspection PhpUndefinedFieldInspection */
            return $this->thread;
        })->call($database);

        // Set worker limit.
        (function () {
            /** @noinspection PhpUndefinedFieldInspection */
            $this->workerLimit = 6;
        })->call($threadPool);

        parent::getMySQLDatabase()->waitAll();
    }

}