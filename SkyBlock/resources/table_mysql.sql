-- # Mysql table generator for SkyBlock.

CREATE TABLE IF NOT EXISTS servers
(
    server_unique_id VARCHAR(32) PRIMARY KEY
) ENGINE = InnoDB;


CREATE TABLE IF NOT EXISTS inventory_ids
(
    id BLOB UNIQUE NOT NULL
);

CREATE TABLE player_data
(
    xuid               VARCHAR(16)              NOT NULL PRIMARY KEY,
    player             VARCHAR(15)              NOT NULL,
    money              INT(50)     DEFAULT 100  NULL,
    bank               INT(50)     DEFAULT 0    NOT NULL,
    inventory          BLOB        DEFAULT ''   NULL,
    backup_inventory   LONGBLOB    DEFAULT '[]' NOT NULL,
    xp                 INT(15)     DEFAULT 0    NOT NULL,
    challenge_progress BLOB        DEFAULT ''   NOT NULL,
    bounty             INT         DEFAULT 0    NOT NULL,
    crate_keys         BLOB        DEFAULT ''   NOT NULL,
    kit_cooldown       BLOB        DEFAULT ''   NOT NULL,
    rewards            BLOB        DEFAULT ''   NOT NULL,
    daily_challenge    BLOB        DEFAULT ''   NOT NULL,
    kill_streak        INT         DEFAULT 0    NOT NULL,
    server_online      VARCHAR(32) DEFAULT NULL,
    vaults             LONGBLOB    DEFAULT (''),
    extra_data         BLOB        DEFAULT ''   NOT NULL,
    trade_cache        BLOB        DEFAULT ''   NOT NULL,

    UNIQUE idx_player (player),
    INDEX idx_server (server_online),
    FOREIGN KEY (server_online) REFERENCES servers (server_unique_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE = InnoDB;

-- # Larger origin size allows more data for staff to investigate
-- # the originality of the item itself.
CREATE TABLE IF NOT EXISTS item_storage
(
    id     INTEGER PRIMARY KEY AUTO_INCREMENT,
    origin BLOB DEFAULT '' NOT NULL
) ENGINE = InnoDB;

-- ALTER TABLE `instance` CHANGE `location` `location` VARCHAR(32) NULL DEFAULT NULL;
-- ALTER TABLE `instance` ADD INDEX(`location`);
-- UPDATE instance SET location = NULL;
-- ALTER TABLE `instance` ADD  CONSTRAINT `instance_ibfk_2` FOREIGN KEY (`location`) REFERENCES `servers`(`server_unique_id`) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE instance
(
    owner    VARCHAR(15)           NOT NULL PRIMARY KEY,
    xuid     VARCHAR(16)           NOT NULL,
    location VARCHAR(32) DEFAULT NULL,
    public   INT(1)      DEFAULT 0 NOT NULL,
    package  LONGBLOB              NULL,

    UNIQUE idx_xuid (xuid),
    INDEX idx_server (location),
    FOREIGN KEY (owner) REFERENCES player_data (player) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (location) REFERENCES servers (server_unique_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE = InnoDB;

CREATE TABLE auctions
(
    auction_id INT AUTO_INCREMENT PRIMARY KEY,
    player     VARCHAR(16)  NOT NULL,
    category   VARCHAR(255) NULL,
    item_name  VARCHAR(255) NULL,
    item_json  BLOB         NULL,
    price      INT          NULL,
    expires    INT          NULL,

    INDEX idx_player (player),
    FOREIGN KEY (player) REFERENCES player_data (player) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE inventory_backup
(
    inventory_id INTEGER PRIMARY KEY AUTO_INCREMENT,
    player_name  VARCHAR(16) NOT NULL,
    has_executed BOOLEAN DEFAULT FALSE,
    inventory    BLOB    DEFAULT (''),
    death_time   INTEGER DEFAULT 0,
    death_cause  BLOB    DEFAULT (''),
    item_count   INTEGER DEFAULT 0,

    INDEX idx_player (player_name),
    FOREIGN KEY (player_name) REFERENCES player_data (player) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE purchases
(
    xuid              varchar(16) PRIMARY KEY NOT NULL,
    lumberjack_helper int(11)                 NOT NULL DEFAULT 0,
    miner_helper      int(11)                 NOT NULL DEFAULT 0,
    harvester_helper  int(11)                 NOT NULL DEFAULT 0
) ENGINE = InnoDB;

CREATE TABLE purchases_ref
(
    xuid              varchar(16) PRIMARY KEY NOT NULL,
    lumberjack_helper int(11)                 NOT NULL DEFAULT 0,
    miner_helper      int(11)                 NOT NULL DEFAULT 0,
    harvester_helper  int(11)                 NOT NULL DEFAULT 0
) ENGINE = InnoDB;

-- #{ offline_storage
CREATE TABLE IF NOT EXISTS offline_storage
(
    player      VARCHAR(16)   NOT NULL,
    message     VARCHAR(1024) NOT NULL,

    INDEX idx_player (player),
    FOREIGN KEY (player) REFERENCES player_data (player) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB;
-- #}