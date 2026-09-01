# Error control
# 0 - The player balance is out of bounds
# 1 - The operation were completed.
DELIMITER &&
CREATE OR REPLACE PROCEDURE economy_transaction(
    IN p_player VARCHAR(16),
    IN p_balance BIGINT(20),
    IN p_transaction_mode INTEGER,
    OUT r_balance BIGINT(20),
    OUT r_result INTEGER
)
BEGIN
    DECLARE v_balance BIGINT(20);

    IF (p_transaction_mode = 0) THEN
        UPDATE player_data
        SET coins = IF((coins + p_balance) < 999999999999999, coins + p_balance, coins)
        WHERE player = p_player;
    ELSE
        UPDATE player_data
        SET coins = IF((coins - p_balance) >= 0, coins - p_balance, coins)
        WHERE player = p_player;
    END IF;

    IF (SELECT ROW_COUNT() > 0) THEN
        SET r_result = 1;
    ELSE
        SET r_result = 0;
    END IF;

    SELECT coins INTO v_balance FROM player_data WHERE player = p_player;
    SET r_balance = v_balance;
END &&
DELIMITER ;