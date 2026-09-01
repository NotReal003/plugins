# Result codes:
# 0 - The player closes the vault is not the same player as the one that opens the vault (Wrong server).
# 1 - Successful vault open.
DELIMITER &&
CREATE OR REPLACE PROCEDURE faction_vault_open(
    IN p_faction_id INTEGER,
    IN p_player_name VARCHAR(16),
    IN p_server_id VARCHAR(255),
    OUT r_result INTEGER,
    OUT r_data LONGBLOB
)
vault_open:
BEGIN
    DECLARE v_data LONGBLOB;
    DECLARE v_player_open VARCHAR(16);

    INSERT INTO faction_vaults(faction_id, locked_player, server_id)
    VALUES (p_faction_id, p_player_name, p_server_id)
    ON DUPLICATE KEY UPDATE server_id     = IF(locked_player IS NULL, p_server_id, server_id),
                            locked_player = IF(locked_player IS NULL, p_player_name, locked_player);

    IF (SELECT ROW_COUNT() = 0) THEN
        SELECT locked_player INTO v_player_open FROM faction_vaults WHERE faction_id = p_faction_id;

        SET r_result = 0;
        SET r_data = v_player_open;

        LEAVE vault_open;
    END IF;

    SELECT vault INTO v_data FROM faction_vaults WHERE faction_id = p_faction_id;

    SET r_result = 1;
    SET r_data = v_data;
END &&
DELIMITER ;