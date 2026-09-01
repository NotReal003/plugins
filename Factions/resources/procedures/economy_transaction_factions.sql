# Error control:
# 0 - The player/faction do not have enough balance
# 1 - The faction/player has reached its maximum balance
# 2 - The operation were successful
DELIMITER &&
CREATE OR REPLACE PROCEDURE economy_transaction_factions(
    IN p_player VARCHAR(16),
    IN p_amount BIGINT(20),
    IN p_faction_id INTEGER,
    IN p_transaction_mode INTEGER,
    OUT r_faction_balance BIGINT(20),
    OUT r_player_balance BIGINT(20),
    OUT r_result INTEGER
)
transaction_economy:
BEGIN
    DECLARE v_faction_balance BIGINT(20);
    DECLARE v_player_balance BIGINT(20);

    # Deposit balance.
    IF (p_transaction_mode = 0) THEN
        UPDATE player_data
        SET coins = IF((coins - p_amount) >= 0, coins - p_amount, coins)
        WHERE player = p_player;

        # The player do not have enough balance to continue transaction
        IF (SELECT ROW_COUNT() = 0) THEN
            SELECT coins INTO v_player_balance FROM player_data WHERE player = p_player;
            SELECT balance INTO v_faction_balance FROM factions WHERE faction_id = p_faction_id;

            SET r_result = 0;
            SET r_player_balance = v_player_balance;
            SET r_faction_balance = v_faction_balance;

            LEAVE transaction_economy;
        END IF;

        UPDATE factions
        SET balance = IF((balance + p_amount) < 999999999999999, balance + p_amount, balance)
        WHERE faction_id = p_faction_id;

        # If the transaction fails, it means that the faction balance has reached its limit.
        IF (SELECT ROW_COUNT() = 0) THEN
            UPDATE player_data
            SET coins = IF((coins + p_amount) < 999999999999999, coins + p_amount, coins)
            WHERE player = p_player;

            SELECT coins INTO v_player_balance FROM player_data WHERE player = p_player;
            SELECT balance INTO v_faction_balance FROM factions WHERE faction_id = p_faction_id;

            SET r_result = 1;
            SET r_player_balance = v_player_balance;
            SET r_faction_balance = v_faction_balance;

            LEAVE transaction_economy;
        END IF;
    ELSE
        UPDATE factions
        SET balance = IF((balance - p_amount) >= 0, balance - p_amount, balance)
        WHERE faction_id = p_faction_id;

        # The faction do not have enough balance to continue transaction
        IF (SELECT ROW_COUNT() = 0) THEN
            SELECT coins INTO v_player_balance FROM player_data WHERE player = p_player;
            SELECT balance INTO v_faction_balance FROM factions WHERE faction_id = p_faction_id;

            SET r_result = 0;
            SET r_player_balance = v_player_balance;
            SET r_faction_balance = v_faction_balance;

            LEAVE transaction_economy;
        END IF;

        UPDATE player_data
        SET coins = IF((coins + p_amount) < 999999999999999, coins + p_amount, coins)
        WHERE player = p_player;

        # If the transaction fails, it means that the player balance has reached its limit.
        IF (SELECT ROW_COUNT() = 0) THEN
            UPDATE factions
            SET balance = IF((balance + p_amount) < 999999999999999, balance + p_amount, balance)
            WHERE faction_id = p_faction_id;

            SELECT coins INTO v_player_balance FROM player_data WHERE player = p_player;
            SELECT balance INTO v_faction_balance FROM factions WHERE faction_id = p_faction_id;

            SET r_result = 1;
            SET r_player_balance = v_player_balance;
            SET r_faction_balance = v_faction_balance;

            LEAVE transaction_economy;
        END IF;
    END IF;

    SELECT coins INTO v_player_balance FROM player_data WHERE player = p_player;
    SELECT balance INTO v_faction_balance FROM factions WHERE faction_id = p_faction_id;

    SET r_result = 2;
    SET r_player_balance = v_player_balance;
    SET r_faction_balance = v_faction_balance;
END &&
DELIMITER ;