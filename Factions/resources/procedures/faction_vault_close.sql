# Result codes:
# 0 - Faction vault could not be verified.
# 1 - The player closes the vault is not the same player as the one that opens the vault (Wrong server).
# 2 - Something went wrong while trying to store the content into the faction vault.
# 3 - Successful vault close.
DELIMITER &&
CREATE OR REPLACE PROCEDURE faction_vault_close(
    IN p_faction_id INTEGER,
    IN p_player_name VARCHAR(16),
    IN p_server_id VARCHAR(255),
    IN p_data LONGBLOB,
    OUT r_result INTEGER
)
vault_close:
BEGIN
    DECLARE v_locked_player VARCHAR(16);
    DECLARE v_server_id VARCHAR(128);

    SELECT locked_player, server_id
    INTO v_locked_player, v_server_id
    FROM faction_vaults
    WHERE faction_id = p_faction_id;

    IF (SELECT FOUND_ROWS() = 0) THEN
        SET r_result = 0;
        LEAVE vault_close;
    END IF;

    IF (v_locked_player != p_player_name OR p_server_id != v_server_id) THEN
        SET r_result = 1;
        LEAVE vault_close;
    END IF;

    UPDATE faction_vaults
    SET locked_player = NULL,
        server_id     = NULL,
        vault         = p_data,
        last_open     = p_player_name
    WHERE faction_id = p_faction_id;

    IF (SELECT ROW_COUNT() > 0) THEN
        SET r_result = 3;
    ELSE
        SET r_result = 2;
    END IF;
END &&
DELIMITER ;