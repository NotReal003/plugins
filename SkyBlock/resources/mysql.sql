-- #! mysql
-- # {  auctionhouse
-- #    {   add_auction
-- #        :player string
-- #        :category string
-- #        :item_name string
-- #        :item_json string
-- #        :price int
-- #        :expires int
INSERT INTO auctions (player, category, item_name, item_json, price, expires) VALUES (:player, :category, :item_name, :item_json, :price, :expires);
-- #    }

-- #    {   get_all
SELECT auction_id, player, category, item_name, item_json, price, expires FROM auctions WHERE expires > UNIX_TIMESTAMP() ORDER BY expires DESC;
-- #    }

-- #    {   get_all_expired
SELECT auction_id, player, category, item_name, item_json, price, expires FROM auctions WHERE expires < UNIX_TIMESTAMP();
-- #    }

-- #    {   get_by_id
-- #        :auction_id int
SELECT auction_id, player, category, item_name, item_json, price, expires FROM auctions WHERE auction_id = :auction_id LIMIT 1;
-- #    }

-- #    {   get_from_item_name
-- #        :item_name string
SELECT auction_id, player, category, item_name, item_json, price, expires FROM auctions WHERE LOWER(item_name) LIKE CONCAT('%', LOWER(:item_name), '%') AND expires > UNIX_TIMESTAMP() ORDER BY expires DESC;
-- #    }

-- #    {   get_all_from_player
-- #        :player string
SELECT auction_id, player, category, item_name, item_json, price, expires FROM auctions WHERE LOWER(player) = LOWER(:player) ORDER BY expires DESC;
-- #    }

-- #    {   get_from_player
-- #        :player string
SELECT auction_id, player, category, item_name, item_json, price, expires FROM auctions WHERE LOWER(player) = LOWER(:player) AND expires > UNIX_TIMESTAMP() ORDER BY expires DESC;
-- #    }

-- #    {   get_in_category
-- #        :category string
SELECT auction_id, player, category, item_name, item_json, price, expires FROM auctions WHERE category = LOWER(:category) AND expires > UNIX_TIMESTAMP() ORDER BY expires DESC;
-- #    }

-- #    {   remove_auction
-- #        :auction_id int
DELETE FROM auctions WHERE auction_id = :auction_id;
-- #    }

-- # }

-- # {  item_storage
-- #    {   add
-- #        :origin string
INSERT INTO item_storage (origin) VALUES (:origin);
-- #    }

-- #    {   exists
-- #        :id int
SELECT origin FROM item_storage WHERE id = :id LIMIT 1;
-- #    }

-- #    {   remove
-- #        :id int
DELETE FROM item_storage WHERE id = :id;
-- #    }
-- # }

-- # {  skyblock
-- #    {   create
-- #        :xuid string
-- #        :owner string
-- #        :location string
-- #        :package string
INSERT IGNORE into instance (xuid, owner, location, package) VALUES (:xuid, :owner, :location, :package);
-- #    }

-- #    {   set_island
-- #        :xuid string
-- #        :package string
UPDATE instance SET package = :package WHERE xuid = :xuid;
-- #    }

-- #    {   set_island_location
-- #        :owner string
-- #        :newLocation string
-- #        :lastLocation string
UPDATE instance SET location = IF(location = :lastLocation, :newLocation, location) WHERE owner = :owner;
-- #    }

-- #    {   owner_change_name
-- #        :xuid string
-- #        :owner string
UPDATE instance SET owner = :owner WHERE xuid = :xuid;
-- #    }

-- #    {   set_island_public
-- #        :xuid string
-- #        :public int
UPDATE instance SET public = :public WHERE xuid = :xuid;
-- #    }

-- #    {   get_island_location
-- #        :owner string
SELECT location FROM instance WHERE owner = :owner;
-- #    }

-- #    {   get_public_islands
SELECT owner FROM instance WHERE public = 1 AND location IS NOT NULL;
-- #    }

-- #    {   get_island
-- #        :owner string
SELECT xuid, package, public FROM instance WHERE owner = :owner;
-- #    }

-- #    {   remove_island
-- #        :xuid string
DELETE FROM instance WHERE xuid = :xuid;
-- #    }
-- # }

-- # {  server

-- #    {   create_server_node
-- #        :server string
INSERT INTO servers(server_unique_id) VALUES (:server);
-- #    }

-- #    {   delete_server_node
-- #        :server string
DELETE FROM servers WHERE server_unique_id = :server;
-- #    }

-- # }

-- # {  player

-- #{ player_get_data
-- #   :xuid string
-- #   :player_name string
SELECT * FROM (SELECT * FROM player_data WHERE xuid = :xuid) t1 LEFT JOIN (SELECT xuid AS relatedXuid FROM player_data WHERE player = :player_name) t2 ON TRUE
UNION
SELECT * FROM (SELECT * FROM player_data WHERE xuid = :xuid) t1 RIGHT JOIN (SELECT xuid AS relatedXuid FROM player_data WHERE player = :player_name) t2 ON TRUE;
-- #}

-- #    {   change_name
-- #        :xuid string
-- #        :player string
UPDATE player_data SET player = :player WHERE xuid = :xuid;
-- #    }

-- #    {   create
-- #        :xuid string
-- #        :player string
-- #        :server string
INSERT INTO player_data (xuid, player, server_online) VALUES (:xuid, :player, :server);
-- #    }

-- #    {   get_player_alike
-- #        :player_name string
SELECT player FROM player_data WHERE player LIKE CONCAT('%', :player_name, '%');
-- #    }

-- #    { lock_verify_server_location
-- #        :server string
-- #        :xuid string
UPDATE player_data SET server_online = :server WHERE xuid = :xuid AND server_online IS NULL;
-- #    }

-- #    {   lock_server_location
-- #        :server string
-- #        :xuid string
UPDATE player_data SET server_online = :server WHERE xuid = :xuid;
-- #    }

-- #    {   unlock_server_location
-- #        :server string
-- #        :xuid string
UPDATE player_data SET server_online = IF(server_online = :server, NULL, server_online) WHERE xuid = :xuid;
-- #    }

-- #    {   get_lock_status
-- #        :xuid string
SELECT server_online FROM player_data WHERE xuid = :xuid;
-- #    }

-- # }

-- #    { backups

-- #        { get_player_names
-- #            :playerName string
SELECT DISTINCT player_name as playerName, (SELECT COUNT(*) FROM inventory_backup WHERE player_name = playerName) as totalEntries FROM inventory_backup WHERE player_name LIKE :playerName LIMIT 10;
-- #        }

-- #        { get_player_entries
-- #            :playerName string
SELECT death_time, inventory_id, death_cause FROM inventory_backup WHERE player_name = :playerName ORDER BY death_time DESC;
-- #        }

-- #        { get_inventory_backup
-- #            :inventory_id int
SELECT inventory_id, has_executed, player_name, inventory, death_time, death_cause, item_count, (SELECT xuid FROM player_data WHERE player = player_name) as xuid FROM inventory_backup WHERE inventory_id = :inventory_id ORDER BY death_time DESC;
-- #        }

-- #        { backup_inventory
-- #            :playerName string
-- #            :inventory string
-- #            :deathCause string
-- #            :itemCount int
INSERT INTO inventory_backup(player_name, inventory, death_time, death_cause, item_count) VALUES (:playerName, :inventory, UNIX_TIMESTAMP(), :deathCause, :itemCount);
-- #        }

-- #        { delete_expired_backup
DELETE FROM inventory_backup WHERE death_time < (UNIX_TIMESTAMP() - 31536000);
-- #        }

-- #        { delete_old_inventory
-- #            :playerName string
DELETE FROM inventory_backup WHERE inventory_id IN (SELECT inventory_id FROM inventory_backup WHERE player_name = :playerName AND death_time IN (SELECT MIN(death_time) FROM inventory_backup WHERE player_name = :playerName));
-- #        }

-- #        { select_total_inventory
-- #            :playerName string
SELECT COUNT(*) as total_inventories FROM inventory_backup WHERE player_name = :playerName;
-- #        }

-- #        { select_inventory_data
-- #            :xuid string
SELECT inventory FROM player_data WHERE xuid = :xuid;
-- #        }

-- #        { update_inventory_data
-- #            :inventory string
-- #            :xuid string
UPDATE player_data SET inventory = :inventory WHERE xuid = :xuid;
-- #        }

-- #        { update_backup_status
-- #            :id string
UPDATE inventory_backup SET has_executed = true WHERE inventory_id = :id;
-- #        }

-- #        { insert_ids
-- #            :id string
INSERT IGNORE INTO inventory_ids(id) VALUE (:id);
-- #        }

-- #        { get_ids
SELECT * FROM inventory_ids;
-- #        }

-- #    }

-- #    { helpers

-- #        { get_remainder
-- #            :xuid string
SELECT p.xuid, p.lumberjack_helper - COALESCE(pr.lumberjack_helper, 0) AS lumberjack_remainder, p.miner_helper - COALESCE(pr.miner_helper, 0) AS miner_remainder, p.harvester_helper - COALESCE(pr.harvester_helper, 0) AS harvester_remainder FROM purchases p LEFT JOIN purchases_ref pr ON p.xuid = pr.xuid WHERE p.xuid = :xuid;
-- #        }

-- #        { set_remainder
-- #            :xuid string
-- #            :lumberjack_helper int
-- #            :miner_helper int
-- #            :harvester_helper int
INSERT INTO purchases_ref(xuid, lumberjack_helper, miner_helper, harvester_helper) VALUES(:xuid, :lumberjack_helper, :miner_helper, :harvester_helper) ON DUPLICATE KEY UPDATE `lumberjack_helper` = `lumberjack_helper` + :lumberjack_helper, `miner_helper` = `miner_helper` + :miner_helper, `harvester_helper` = `harvester_helper` + :harvester_helper;
-- #        }

-- #    }