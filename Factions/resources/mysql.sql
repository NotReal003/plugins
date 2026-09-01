-- #!mysql

-- # { table
-- #        { optimize_tables
OPTIMIZE TABLE factions_kills, inventory_backup;
-- #        }
-- #    }

-- #    { server
-- #        { create_server_node
-- #            :server string
INSERT INTO servers(server_unique_id) VALUES (:server);
-- #        }

-- #        { delete_server_node
-- #            :server string
DELETE FROM servers WHERE server_unique_id = :server;
-- #        }
-- #    }


-- #    { player
-- #        { player_get_data
-- #            :xuid string
-- #            :player_name string
SELECT * FROM (SELECT * FROM player_data WHERE xuid = :xuid) t1 LEFT JOIN (SELECT xuid AS relatedXuid FROM player_data WHERE player = :player_name) t2 ON TRUE
UNION
SELECT * FROM (SELECT * FROM player_data WHERE xuid = :xuid) t1 RIGHT JOIN (SELECT xuid AS relatedXuid FROM player_data WHERE player = :player_name) t2 ON TRUE;
-- #        }

-- #        { join_get
-- #            :xuid string
SELECT * FROM player_data WHERE xuid = :xuid;
-- #        }

-- #        { join_get_name
-- #            :player string
SELECT xuid FROM player_data WHERE player = :player;
-- #        }

-- #        { get_player_alike
-- #            :player_name string
SELECT player FROM player_data WHERE player LIKE CONCAT('%', :player_name, '%');
-- #        }

-- #        { change_name
-- #            :xuid string
-- #            :player string
UPDATE player_data SET player = :player WHERE xuid = :xuid;
-- #        }

-- #        { create
-- #            :xuid string
-- #            :server string
-- #            :player string
INSERT INTO player_data (xuid, player, registerDate, server_online) VALUES (:xuid, :player, UNIX_TIMESTAMP(), :server);
-- #        }

-- #        { economy_transaction
-- #            :player string
-- #            :balance int
-- #            :mode int
CALL economy_transaction(:player, :balance, :mode, @balance, @result);
-- #&
SELECT @balance as balance, @result as result;
-- #        }

-- #        { lock_verify_server_location
-- #            :server string
-- #            :xuid string
UPDATE player_data SET server_online = :server WHERE xuid = :xuid AND server_online IS NULL;
-- #        }

-- #        { lock_server_location
-- #            :server string
-- #            :xuid string
UPDATE player_data SET server_online = :server WHERE xuid = :xuid;
-- #}

-- #        { unlock_server_location
-- #            :server string
-- #            :xuid string
UPDATE player_data SET server_online = IF(server_online = :server, NULL, server_online) WHERE xuid = :xuid;
-- #}

-- #        { get_lock_status
-- #            :xuid string
SELECT server_online FROM player_data WHERE xuid = :xuid;
-- #        }
-- #    }


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
DELETE FROM inventory_backup WHERE death_time < (UNIX_TIMESTAMP() - 2764800);
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

-- #    { factions
-- #        { get_faction_id
-- #            :player string
SELECT faction_id FROM faction_members WHERE player_name = :player;
-- #        }

-- #        { select_by_name
-- #            :faction_name string
SELECT faction_id FROM factions WHERE faction_name = :faction_name;
-- #        }

-- #        { set_faction_name
-- #            :faction_name string
-- #            :faction_id string
UPDATE factions SET faction_name = IF ((SELECT COUNT(*) FROM factions WHERE faction_name = :faction_name) = 0, :faction_name, faction_name) WHERE faction_id = :faction_id;
-- #         }

-- #        { get_faction_meta
-- #            :faction_id int
SELECT * FROM factions WHERE faction_id = :faction_id;
-- #        }

-- #        { get_members
-- #            :faction_id int
SELECT * FROM faction_members WHERE faction_id = :faction_id;
-- #        }

-- #        { get_allies
-- #            :faction_id int
SELECT faction_allies.faction_id,
       factions.faction_name AS allied_name
FROM faction_allies
         INNER JOIN factions ON faction_allies.faction_id = factions.faction_id
WHERE faction_allies.faction_allied = :faction_id
UNION
SELECT faction_allies.faction_allied, factions.faction_name AS allied_name
FROM faction_allies
         INNER JOIN factions ON faction_allies.faction_allied = factions.faction_id
WHERE faction_allies.faction_id = :faction_id;
-- #        }

-- #        { create
-- #            :faction_name string
-- #            :leader string
INSERT INTO factions(faction_name, leader) VALUES (:faction_name, :leader);
-- #        }

-- #        { remove_allies
-- #            :faction_id int
-- #            :faction_ally int
DELETE FROM faction_allies WHERE (faction_id = :faction_id AND faction_allied = :faction_ally) OR (faction_allied = :faction_id AND faction_id = :faction_ally);
-- #        }

-- #        { add_member
-- #            :player_name string
-- #            :faction_role int
-- #            :faction_id int
INSERT INTO faction_members(faction_id, faction_role, player_name) VALUES (:faction_id, :faction_role, :player_name) ON DUPLICATE KEY UPDATE faction_role = :faction_role;
-- #        }

-- #        { update_leader
-- #            :faction_id int
-- #            :old_leader string
-- #            :new_leader string
UPDATE factions, faction_members SET factions.leader = :new_leader, faction_members.faction_role = ( CASE WHEN faction_members.player_name = :old_leader THEN 1 WHEN faction_members.player_name = :new_leader THEN 2 END ) WHERE faction_members.faction_id = :faction_id AND factions.faction_id = :faction_id AND faction_members.player_name IN (:old_leader, :new_leader);
-- #        }

-- #        { set_leader
-- #            :player_name string
-- #            :faction_id int
UPDATE factions SET leader = :player_name WHERE faction_id = :faction_id;
-- #        }

-- #        { remove_member
-- #            :player_name string
-- #            :faction_id int
DELETE FROM faction_members WHERE player_name = :player_name AND faction_id = :faction_id;
-- #        }

-- #        { get_faction_count
-- #            :faction_id int
SELECT COUNT(*) as members FROM faction_members WHERE faction_id = :faction_id;
-- #        }

-- #        { update_permissions
-- #            :faction_id int
-- #            :permissions string
UPDATE factions SET permissions = :permissions WHERE faction_id = :faction_id;
-- #        }

-- #        { update_kick_days
-- #            :faction_id int
-- #            :kick_days int
UPDATE factions SET auto_kick_days = :kick_days WHERE faction_id = :faction_id;
-- #        }

-- #        { update_kick_deaths
-- #            :faction_id int
-- #            :kick_deaths int
UPDATE factions SET auto_kick_deaths = :kick_deaths WHERE faction_id = :faction_id;
-- #        }

-- #        { increase_death_tracking
-- #            :faction_id int
-- #            :player_name string
-- #            :current_epoch int
CALL increment_death_total(:faction_id, :player_name, :current_epoch, @r_status);
-- #&
SELECT @r_status as status;
-- #        }

-- #        { decrease_death_tracking
-- #            :faction_id int
-- #            :player_name string
UPDATE factions_kills_tracking SET deaths_total = IF(deaths_total > 0, deaths_total - 1, 0) WHERE player_name = :player_name AND faction_id = :faction_id;
-- #        }

-- #        { increase_strength
-- #            :faction_id int
-- #            :strength int
UPDATE factions SET strength = strength + :strength WHERE faction_id = :faction_id;
-- #        }

-- #        { decrease_strength
-- #            :faction_id int
-- #            :strength int
UPDATE factions SET strength = strength - :strength WHERE faction_id = :faction_id;
-- #        }

-- #        { get_strength
-- #            :faction_id int
SELECT strength FROM factions WHERE faction_id = :faction_id;
-- #        }

-- #        { update_motd
-- #            :faction_id int
-- #            :motd string
UPDATE factions SET motd = :motd WHERE faction_id = :faction_id;
-- #        }

-- #        { add_allies
-- #            :faction_id int
-- #            :faction_ally int
INSERT IGNORE INTO faction_allies(faction_id, faction_allied) SELECT :faction_id, :faction_ally WHERE IF ((SELECT COUNT(*) FROM faction_allies WHERE faction_id = :faction_ally AND faction_allied = :faction_id) = 0, TRUE, FALSE);
-- #        }

-- #        { get_allies_count
-- #            :faction_id int
-- #            :faction_ally int
SELECT COUNT(*) AS allies_count, :faction_id AS faction_id FROM faction_allies WHERE (faction_id = :faction_id OR faction_allied = :faction_id) UNION SELECT COUNT(*) AS allies_count, :faction_ally AS faction_id FROM faction_allies WHERE (faction_id = :faction_ally OR faction_allied = :faction_ally);
-- #        }

-- #        { economy_transaction
-- #            :player string
-- #            :amount int
-- #            :faction_id int
-- #            :transaction_mode int
CALL economy_transaction_factions(:player, :amount, :faction_id, :transaction_mode, @faction_bal, @player_bal, @result);
-- #&
SELECT @faction_bal AS faction_balance, @player_bal AS player_balance, @result AS result;
-- #        }

-- #        { update_home_cords
-- #            :home_cords string
-- #            :faction_id int
UPDATE factions SET home_coords = :home_cords WHERE faction_id = :faction_id;
-- #        }

-- #        { delete_home_cords
-- #            :faction_id int
UPDATE factions SET home_coords = NULL WHERE faction_id = :faction_id;
-- #        }

-- #        { strongest_faction
SELECT faction_name, leader, strength FROM factions ORDER BY strength DESC LIMIT 5;
-- #        }

-- #        { faction_rank
-- #            :faction_id int
SELECT (SELECT FIND_IN_SET( strength, ( SELECT GROUP_CONCAT( DISTINCT strength ORDER BY strength DESC ) FROM factions )) FROM factions WHERE faction_id = :faction_id) AS ranking, (SELECT MAX(ranking) FROM (SELECT FIND_IN_SET( strength, ( SELECT GROUP_CONCAT( DISTINCT strength ORDER BY strength DESC ) FROM factions)) AS ranking FROM factions) r) AS total_rows;
-- #        }

-- #        { faction_total
SELECT COUNT(*) AS total_rows FROM factions;
-- #        }

-- #        { faction_vault_open
-- #            :faction_id string
-- #            :player_name string
-- #            :server_id string
CALL faction_vault_open(:faction_id, :player_name, :server_id, @result, @contents);
-- #&
SELECT @result as result, @contents AS contents;
-- #        }

-- #        { faction_vault_close
-- #            :faction_id string
-- #            :player_name string
-- #            :server_id string
-- #            :contents string
CALL faction_vault_close(:faction_id, :player_name, :server_id, :contents, @result);
-- #&
SELECT @result as result;
-- #        }

-- #        { inc_factions_kills
-- #            :faction_id int
-- #            :player_name string
INSERT INTO factions_kills(faction_id, player_name, deaths_on, death_time) VALUES (:faction_id, :player_name, 1, UNIX_TIMESTAMP() + 86400) ON DUPLICATE KEY UPDATE deaths_on  = IF(deaths_on >= 5 AND death_time > UNIX_TIMESTAMP(), deaths_on, IF(death_time < UNIX_TIMESTAMP(), 1, deaths_on + 1)), death_time = IF(death_time < UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + 86400, death_time);
-- #        }

-- #        { delete_old_kills
DELETE FROM factions_kills WHERE death_time < UNIX_TIMESTAMP();
-- #        }

-- #        { faction_select
-- #            :factionName string
SELECT faction_id, faction_name, strength FROM factions WHERE faction_name LIKE CONCAT('%', :factionName, '%') LIMIT 10;
-- #        }
-- #    }


-- #    { item_storage
-- #        { add
-- #            :origin string
INSERT INTO item_storage (origin) VALUES (:origin);
-- #        }

-- #        { exists
-- #            :id int
SELECT origin FROM item_storage WHERE id = :id LIMIT 1;
-- #        }

-- #        { remove
-- #            :id int
DELETE FROM item_storage WHERE id = :id;
-- #        }
-- #    }


-- #    {   claims

-- #        { load_claims
-- #            :server_id string
SELECT faction_claims.faction_id, faction_claims.chunk_hash, factions.faction_name AS faction_name, factions.strength AS strength FROM faction_claims INNER JOIN factions ON faction_claims.faction_id = factions.faction_id WHERE faction_claims.server_id = :server_id;
-- #        }

-- #        { add_claims
-- #            :faction_id int
-- #            :server_id string
-- #            :chunk_hash int
INSERT IGNORE INTO faction_claims(chunk_hash, server_id, faction_id) VALUES (:chunk_hash, :server_id, :faction_id);
-- #        }

-- #        { overclaim
-- #            :faction_id int
-- #            :server_id string
-- #            :chunk_hash int
UPDATE faction_claims SET faction_id = :faction_id WHERE chunk_hash = :chunk_hash AND server_id = :server_id;
-- #        }

-- #        { remove_claim
-- #            :server_id string
-- #            :chunk_hash int
DELETE FROM faction_claims WHERE chunk_hash = :chunk_hash AND server_id = :server_id;
-- #        }
-- #    }


-- #    {  auctionhouse

-- #        { add_auction
-- #            :player string
-- #            :category string
-- #            :item_name string
-- #            :item_json string
-- #            :price int
-- #            :expires int
INSERT INTO auctions (player, category, item_name, item_json, price, expires) VALUES (:player, :category, :item_name, :item_json, :price, :expires);
-- #        }

-- #        { get_all
SELECT auction_id, player, category, item_name, item_json, price, expires FROM auctions WHERE expires > UNIX_TIMESTAMP() ORDER BY expires DESC;
-- #        }

-- #        { get_all_expired
SELECT auction_id, player, category, item_name, item_json, price, expires FROM auctions WHERE expires < UNIX_TIMESTAMP();
-- #        }

-- #        { get_by_id
-- #   :auction_id int
SELECT auction_id, player, category, item_name, item_json, price, expires FROM auctions WHERE auction_id = :auction_id LIMIT 1;
-- #        }

-- #        { get_from_item_name
-- #   :item_name string
SELECT auction_id, player, category, item_name, item_json, price, expires FROM auctions WHERE LOWER(item_name) LIKE CONCAT('%', LOWER(:item_name), '%') AND expires > UNIX_TIMESTAMP() ORDER BY expires DESC;
-- #        }

-- #        { get_all_from_player
-- #   :player string
SELECT auction_id, player, category, item_name, item_json, price, expires FROM auctions WHERE LOWER(player) = LOWER(:player) ORDER BY expires DESC;
-- #        }

-- #        { get_from_player
-- #   :player string
SELECT auction_id, player, category, item_name, item_json, price, expires FROM auctions WHERE LOWER(player) = LOWER(:player) AND expires > UNIX_TIMESTAMP() ORDER BY expires DESC;
-- #        }

-- #        { get_in_category
-- #            :category string
SELECT auction_id, player, category, item_name, item_json, price, expires FROM auctions WHERE category = LOWER(:category) AND expires > UNIX_TIMESTAMP() ORDER BY expires DESC;
-- #        }

-- #        { is_locked
-- #            :auction_id int
SELECT locked_xuid FROM auctions WHERE auction_id = :auction_id;
-- #        }

-- #        { lock
-- #            :auction_id int
-- #            :xuid string
-- #            :server_id string
UPDATE auctions SET locked_xuid = IF(locked_xuid IS NULL, :xuid, locked_xuid), server_id = IF(server_id IS NULL, :server_id, server_id) WHERE auction_id = :auction_id;
-- #        }

-- #        { unlock
-- #            :auction_id int
-- #            :xuid string
UPDATE auctions SET locked_xuid = NULL WHERE auction_id = :auction_id AND locked_xuid = :xuid;
-- #        }

-- #        { remove_auction
-- #            :auction_id int
DELETE FROM auctions WHERE auction_id = :auction_id;
-- #        }

-- #        { orphaned_auctions
-- #            :server_id string
UPDATE auctions SET locked_xuid = NULL, server_id = NULL WHERE server_id = :server_id
-- #        }

-- #    }