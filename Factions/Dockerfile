#
# PMMP Dockerfile
#
# docker build -t nethergamesmc/servers:factions --build-arg BUILD_FROM=nethergamesmc/servers:base -f Factions/Dockerfile .
#
# Expects the build context to contain, in addition to this repo:
#   xFactions.phar            - built by this repo's build workflow
#   VanillaGenerator.phar     - built from the VanillaGenerator repo
#   assets/Factions/          - checked out from the assets repo
#
ARG BUILD_FROM=nethergamesmc/servers:base
FROM $BUILD_FROM

# Factions ticks a lot of claimed chunks, so it uses the chunk-ticking tuned configuration.
ADD docker/Chunk_Ticking_pocketmine.yml /home/pocketmine.yml
# Survival, so players can build in their claims.
ADD docker/overrides/Factions/server.properties /home/server.properties

ARG SERVER_TYPE=Factions
COPY docker/ngessentials-config.yml /home/plugin_data/NGEssentials/config.yml
RUN sed -i "s/__SERVER_TYPE__/${SERVER_TYPE}/" /home/plugin_data/NGEssentials/config.yml
ADD xFactions.phar /home/plugins/xFactions.phar

# Farlands and Badlands worlds are generated at runtime, so the vanilla generator is required.
ADD VanillaGenerator.phar /home/plugins/VanillaGenerator.phar
