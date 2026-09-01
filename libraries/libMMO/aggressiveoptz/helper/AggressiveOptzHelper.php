<?php
/**
 *   _ _ _     __  __ __  __  ____
 *  | (_) |   |  \/  |  \/  |/ __ \
 *  | |_| |__ | \  / | \  / | |  | |
 *  | | | '_ \| |\/| | |\/| | |  | |
 *  | | | |_) | |  | | |  | | |__| |
 *  |_|_|_.__/|_|  |_|_|  |_|\____/
 *
 * Copyright (C) 2016-2024 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder, Studgi
 */

declare(strict_types=1);

namespace libMMO\aggressiveoptz\helper;

use libMMO\aggressiveoptz\AggressiveOptzAPI;
use libMMO\aggressiveoptz\helper\world\AggressiveOptzWorldCacheManager;

final class AggressiveOptzHelper
{

    /** @var AggressiveOptzWorldCacheManager */
    private AggressiveOptzWorldCacheManager $world_cache_manager;

    public function __construct()
    {
        $this->world_cache_manager = new AggressiveOptzWorldCacheManager();
    }

    public function init(AggressiveOptzAPI $api): void
    {
        $this->world_cache_manager->init($api);
    }

    public function getWorldCacheManager(): AggressiveOptzWorldCacheManager
    {
        return $this->world_cache_manager;
    }
}