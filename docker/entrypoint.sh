#!/usr/bin/env bash
#
# Wires gamemode assets into place, then hands off to the PocketMine launcher.
#
# The assets repository (https://github.com/NetherGamesMC/assets) is hundreds of megabytes and lives
# outside this repo, so it is mounted at runtime rather than baked into the image. Mount it read-only
# at /assets and this reproduces the layout the production images use:
#
#   /assets/<Game>/*            -> /home/plugin_data/x<Game>/*  (arenas, arenas.yml, WaitingLobby, ...)
#   /assets/<Game>/WaitingLobby -> /home/worlds/Hub             (additionally, as the default world)
#   /assets/<Game>/worlds/*     -> /home/worlds/*               (Lobby ships whole worlds instead)
#
# WaitingLobby must stay inside the plugin data folder as well as being seeded as the default world:
# libminigames\Arena::__construct copies it from there into a fresh world for every arena it creates,
# so a data folder without it starts matches with no waiting lobby.
#
# Nothing is overwritten: the server writes to its worlds, so anything already present wins. Set
# NG_ASSETS_FORCE=1 to replace existing copies instead.
#
set -euo pipefail

GAME="${NG_GAME:-}"
ASSETS="${NG_ASSETS:-/assets}"
SRC="${ASSETS}/${GAME}"

copy_missing() {
    local from="$1" to="$2"
    if [ ! -e "$from" ]; then
        return 0
    fi
    if [ -e "$to" ] && [ "${NG_ASSETS_FORCE:-0}" != "1" ]; then
        echo "[assets] keeping existing $to"
        return 0
    fi
    rm -rf "$to"
    mkdir -p "$(dirname "$to")"
    cp -r "$from" "$to"
    echo "[assets] installed $to"
}

if [ -z "$GAME" ]; then
    echo "[assets] NG_GAME is not set; skipping asset wiring"
elif [ ! -d "$SRC" ]; then
    if [ -d "$ASSETS" ]; then
        echo "[assets] no assets for ${GAME} under ${ASSETS}; PocketMine will generate a world"
    else
        echo "[assets] ${ASSETS} not mounted; PocketMine will generate a world"
    fi
else
    echo "[assets] wiring ${GAME} from ${SRC}"

    # The data folder is named after the plugin, which is not always x<Game>: SkyBlock's plugin is
    # NGSkyBlock and Lobby's is plain Lobby. Read it from the packed plugin.yml rather than guessing,
    # so a renamed plugin cannot silently strand its assets in a directory nothing reads.
    PLUGIN_NAME="$(cat /home/.ng-plugin-name 2>/dev/null || true)"
    if [ -z "$PLUGIN_NAME" ]; then
        PLUGIN_NAME="x${GAME}"
        echo "[assets] plugin name unknown, assuming ${PLUGIN_NAME}"
    fi
    PLUGIN_DATA="/home/plugin_data/${PLUGIN_NAME}"

    # A "worlds" directory means whole worlds rather than arena data — that is how Lobby ships.
    if [ -d "${SRC}/worlds" ]; then
        for world in "${SRC}/worlds"/*; do
            [ -e "$world" ] || continue
            copy_missing "$world" "/home/worlds/$(basename "$world")"
        done
    fi

    # Everything else belongs to the plugin's data folder. Copying wholesale rather than naming
    # arenas/arenas.yml keeps per-gamemode extras working — TheBridge ships a map_rotation.php.
    for item in "${SRC}"/*; do
        [ -e "$item" ] || continue
        name="$(basename "$item")"
        if [ "$name" = "worlds" ]; then
            continue
        fi
        copy_missing "$item" "${PLUGIN_DATA}/${name}"
    done

    copy_missing "${SRC}/WaitingLobby" "/home/worlds/Hub"
fi

exec /home/start.sh "$@"
