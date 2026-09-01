# Per-gamemode server configuration

Files here override the defaults in `docker/` for one gamemode. `docker/server.Dockerfile` copies everything in `overrides/<Game>/` over `/home/` after the shared configuration is in place, so a gamemode only needs to ship the files it actually changes.

This mirrors the production layout, where most servers use the shared `server.properties` and `pocketmine.yml` and only a few carry their own.

| Gamemode   | Overrides                             | Why                                                                                                                                           |
|:-----------|:--------------------------------------|:----------------------------------------------------------------------------------------------------------------------------------------------|
| `Factions` | `server.properties`                   | Survival gamemode (`gamemode=0`) so players can build inside their claims                                                                     |
| `SkyBlock` | `server.properties`, `pocketmine.yml` | Survival gamemode (`gamemode=0`) so players can build on their islands, `difficulty=3`, and memory/GC tuning for streaming many island worlds |
