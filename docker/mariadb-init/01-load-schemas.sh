#!/usr/bin/env bash
#
# Loads the repository's schemas into a freshly initialised MariaDB. The entrypoint runs this once,
# when the data volume is empty; wipe the volume to re-run it.
#
set -euo pipefail

mariadb -uroot -p"${MARIADB_ROOT_PASSWORD}" <<SQL
CREATE DATABASE IF NOT EXISTS ngdata;
CREATE DATABASE IF NOT EXISTS skyblock;
CREATE DATABASE IF NOT EXISTS factions;
SQL

# NGEssentials.
mariadb -uroot -p"${MARIADB_ROOT_PASSWORD}" ngdata < /schemas/ngdata_players.sql

# SkyBlock. Loads cleanly on MariaDB; MySQL 8 rejects it.
mariadb -uroot -p"${MARIADB_ROOT_PASSWORD}" skyblock < /schemas/table_mysql.sql

# Factions.
mariadb -uroot -p"${MARIADB_ROOT_PASSWORD}" factions < /schemas/factions_table_mysql.sql

# Stored procedures. These are loaded after the tables they operate on, and must be applied with the
# client rather than piped through the server directly, because the files use DELIMITER — a client
# directive the server itself does not understand. Both plugins CALL these at runtime, so a database
# without them starts fine and then fails the first time a vault is opened or money changes hands.
mariadb -uroot -p"${MARIADB_ROOT_PASSWORD}" ngdata < /schemas/ngdata_procedures.sql

for procedure in /schemas/factions_procedures/*.sql; do
    [ -e "$procedure" ] || continue
    mariadb -uroot -p"${MARIADB_ROOT_PASSWORD}" factions < "$procedure"
done

echo "schemas loaded: ngdata ($(mariadb -uroot -p"${MARIADB_ROOT_PASSWORD}" -N -e 'SHOW TABLES IN ngdata' | wc -l) tables), skyblock ($(mariadb -uroot -p"${MARIADB_ROOT_PASSWORD}" -N -e 'SHOW TABLES IN skyblock' | wc -l) tables), factions ($(mariadb -uroot -p"${MARIADB_ROOT_PASSWORD}" -N -e 'SHOW TABLES IN factions' | wc -l) tables)"
echo "procedures loaded: ngdata ($(mariadb -uroot -p"${MARIADB_ROOT_PASSWORD}" -N -e "SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema='ngdata'")), factions ($(mariadb -uroot -p"${MARIADB_ROOT_PASSWORD}" -N -e "SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema='factions'"))"
