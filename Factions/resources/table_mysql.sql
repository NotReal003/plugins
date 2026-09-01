-- #{ table

-- #{ optimize_tables
OPTIMIZE TABLE factions_kills, inventory_backup;
-- #}

-- #{ online_servers
CREATE TABLE IF NOT EXISTS servers
(
    server_unique_id VARCHAR(128) PRIMARY KEY
) ENGINE = InnoDB;
-- #}

-- #{ players
CREATE TABLE IF NOT EXISTS player_data
(
    player        VARCHAR(16) NOT NULL,
    xuid          VARCHAR(16) NOT NULL,
    inventory     BLOB         DEFAULT (''),
    crate_keys    BLOB         DEFAULT (''),
    kit_cooldown  BLOB         DEFAULT (''),
    vaults        LONGBLOB     DEFAULT (''),
    tags          BIGINT       DEFAULT 0,
    registerDate  INT          DEFAULT 0,
    currentTag    INT          DEFAULT 0,
    coins         INT          DEFAULT 1000,
    groupId       INT          DEFAULT 0,
    kills         INT          DEFAULT 0,
    streak        INT          DEFAULT 0,
    bestStreak    INT          DEFAULT 0,
    bounty        INT          DEFAULT 0,
    xp            INT          DEFAULT 0,
    form_status   INT          DEFAULT 0,
    extra_data    BLOB         DEFAULT (''),
    home_coords   BLOB         DEFAULT (''),
    server_online VARCHAR(128) DEFAULT NULL,

    PRIMARY KEY (xuid),
    UNIQUE idx_player (player),
    INDEX idx_server (server_online),
    FOREIGN KEY (server_online) REFERENCES servers (server_unique_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE = InnoDB;
-- #}

-- #{ item_storage
CREATE TABLE IF NOT EXISTS item_storage
(
    id     INTEGER PRIMARY KEY AUTO_INCREMENT,
    origin varchar(16) NOT NULL
) ENGINE = InnoDB;
-- #}

-- #{ inventory_ids
CREATE TABLE IF NOT EXISTS inventory_ids
(
    id BLOB UNIQUE NOT NULL
);
-- #}

-- #{ auctions
CREATE TABLE IF NOT EXISTS auctions
(
    auction_id INTEGER PRIMARY KEY AUTO_INCREMENT,
    player     VARCHAR(16) NOT NULL,
    category   VARCHAR(255) DEFAULT NULL,
    item_name  VARCHAR(255) DEFAULT NULL,
    item_json  BLOB         DEFAULT NULL,
    price      INTEGER      DEFAULT NULL,
    expires    INTEGER      DEFAULT NULL,

    INDEX idx_player (player),
    FOREIGN KEY (player) REFERENCES player_data (player) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB;
-- #}

-- #{ factions
CREATE TABLE IF NOT EXISTS factions
(
    faction_id   INTEGER AUTO_INCREMENT,
    faction_name VARCHAR(16),
    leader       VARCHAR(16) NOT NULL,
    home_coords  BLOB,
    motd         VARCHAR(200) DEFAULT '',
    strength     INT          DEFAULT 100,
    balance      INT          DEFAULT 0,

    PRIMARY KEY faction_idx (faction_id, leader),
    UNIQUE leader_idx (leader),
    FOREIGN KEY (leader) REFERENCES player_data (player) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB;
-- #}

-- #{ faction_allies
CREATE TABLE IF NOT EXISTS faction_allies
(
    faction_id     INTEGER,
    faction_allied INTEGER,

    UNIQUE idx_faction (faction_id, faction_allied),
    FOREIGN KEY (faction_id) REFERENCES factions (faction_id) ON DELETE CASCADE,
    FOREIGN KEY (faction_allied) REFERENCES factions (faction_id) ON DELETE CASCADE
) ENGINE = InnoDB;
-- #}

-- #{ faction_kills
CREATE TABLE IF NOT EXISTS factions_kills
(
    faction_id  INTEGER,
    player_name VARCHAR(16) NOT NULL,
    deaths_on   INTEGER DEFAULT 0,
    death_time  INTEGER,

    PRIMARY KEY (faction_id, player_name),
    FOREIGN KEY (player_name) REFERENCES player_data (player) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (faction_id) REFERENCES factions (faction_id) ON DELETE CASCADE
) ENGINE = InnoDB;
-- #}

-- #{ faction_vault
CREATE TABLE IF NOT EXISTS faction_vaults
(
    faction_id    INTEGER PRIMARY KEY,
    vault         LONGBLOB,
    server_id     VARCHAR(128) DEFAULT NULL,
    locked_player VARCHAR(16)  DEFAULT NULL,
    last_open     VARCHAR(16)  DEFAULT NULL,

    INDEX idx_players (locked_player, last_open),
    FOREIGN KEY (server_id) REFERENCES servers (server_unique_id) ON UPDATE CASCADE ON DELETE SET NULL,
    FOREIGN KEY (faction_id) REFERENCES factions (faction_id) ON DELETE CASCADE,
    FOREIGN KEY (locked_player) REFERENCES player_data (player) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (last_open) REFERENCES player_data (player) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB;
-- #}

-- #{ faction_claims
CREATE TABLE IF NOT EXISTS faction_claims
(
    chunk_hash BIGINT     NOT NULL,
    server_id  VARCHAR(3) NOT NULL,
    faction_id INTEGER    NOT NULL,

    UNIQUE chunk_hash (chunk_hash, server_id),
    INDEX idx_faction (faction_id),
    FOREIGN KEY (faction_id) REFERENCES factions (faction_id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- #}

-- #{ faction_member
CREATE TABLE IF NOT EXISTS faction_members
(
    faction_id   INTEGER,
    faction_role INTEGER     NOT NULL,
    player_name  VARCHAR(16) NOT NULL,

    INDEX idx_faction (faction_id),
    UNIQUE idx_player (player_name),
    FOREIGN KEY (player_name) REFERENCES player_data (player) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (faction_id) REFERENCES factions (faction_id) ON DELETE CASCADE
) ENGINE = InnoDB;
-- #}

-- #{faction_kills_tracking
CREATE TABLE factions_kills_tracking
(
    player_name VARCHAR(16) NOT NULL,
    faction_id INTEGER NOT NULL,
    deaths_total INTEGER DEFAULT 0,
    last_updated_epoch INTEGER DEFAULT 0,

    INDEX idx_faction (faction_id),
    UNIQUE idx_player (player_name),
    FOREIGN KEY (player_name) REFERENCES player_data (player) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (faction_id) REFERENCES factions (faction_id) ON DELETE CASCADE
);
-- #}

-- #{ offline_storage
CREATE TABLE IF NOT EXISTS offline_storage
(
    player      VARCHAR(16)   NOT NULL,
    message     VARCHAR(1024) NOT NULL,

    INDEX idx_player (player),
    FOREIGN KEY (player) REFERENCES player_data (player) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB;
-- #}

-- #{ inventory_storage
CREATE TABLE IF NOT EXISTS inventory_backup
(
    inventory_id INTEGER PRIMARY KEY AUTO_INCREMENT,
    player_name  VARCHAR(16) NOT NULL,
    inventory    BLOB    DEFAULT (''),
    death_time   INTEGER DEFAULT 0,
    death_cause  BLOB    DEFAULT (''),
    has_executed BOOLEAN DEFAULT FALSE,
    item_count   INTEGER DEFAULT 0,

    INDEX idx_player (player_name),
    FOREIGN KEY (player_name) REFERENCES player_data (player) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB;
-- #}

-- #{ alter_table
ALTER TABLE factions
    AUTO_INCREMENT = 1;
-- #{

-- #}