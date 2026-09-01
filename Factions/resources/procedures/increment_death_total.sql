DELIMITER &&
CREATE OR REPLACE PROCEDURE increment_death_total
(
    IN p_faction_id INTEGER,
    IN p_player_name VARCHAR(16),
    IN p_current_epoch INTEGER,
    OUT r_result INTEGER
)
BEGIN
    DECLARE v_auto_kicks_deaths INTEGER;
    DECLARE v_leader VARCHAR(255);
    DECLARE v_deaths_total INT;
    DECLARE v_last_updated_epoch INT;

    DECLARE result_id INTEGER DEFAULT 0;

    SELECT auto_kick_deaths, leader INTO v_auto_kicks_deaths, v_leader FROM factions WHERE faction_id = p_faction_id;
    SELECT last_updated_epoch INTO v_last_updated_epoch FROM factions_kills_tracking WHERE player_name = p_player_name AND faction_id = p_faction_id;

    IF (v_leader != p_player_name) THEN
        IF (SELECT FOUND_ROWS() > 0) THEN
            -- Update the factions kill tracking. Check if the last updated epoch passed the 1 day check.

            IF ((p_current_epoch - v_last_updated_epoch) > 86400) THEN
                UPDATE factions_kills_tracking SET deaths_total = 1, last_updated_epoch = p_current_epoch WHERE player_name = p_player_name AND faction_id = p_faction_id;
            ELSE
                UPDATE factions_kills_tracking SET deaths_total = deaths_total + 1 WHERE player_name = p_player_name AND faction_id = p_faction_id;
            END IF;
        ELSE
            -- Insert the factions kill tracking.
            INSERT INTO factions_kills_tracking
            SET deaths_total       = 1,
                player_name        = p_player_name,
                faction_id         = p_faction_id,
                last_updated_epoch = p_current_epoch
            ON DUPLICATE KEY
                UPDATE deaths_total       = 1,
                       player_name        = p_player_name,
                       faction_id         = p_faction_id,
                       last_updated_epoch = p_current_epoch;
        END IF;

        -- Retrieve the updated row data.
        SELECT deaths_total INTO v_deaths_total FROM factions_kills_tracking WHERE player_name = p_player_name AND faction_id = p_faction_id;

        IF (v_deaths_total > v_auto_kicks_deaths AND v_auto_kicks_deaths > 0) THEN
            -- Kick the player out of faction
            DELETE FROM faction_members WHERE player_name = p_player_name AND faction_id = p_faction_id;
            DELETE FROM factions_kills_tracking WHERE player_name = p_player_name AND faction_id = p_faction_id;

            SET result_id = 1;
        END IF;
    END IF;

    SET r_result = result_id;
END &&
DELIMITER ;