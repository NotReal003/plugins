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

namespace libMMO\aggressiveoptz;

use libMMO\MMOPlugin;

final class AggressiveOptzLoader
{

    private const COMPONENTS_CONFIG = [
        "aggressiveoptz:falling_block" => [
            "enabled" => true,
            "configuration" => [
                "falling_block_queue_size" => 16,
                "falling_block_max_height" => 16
            ]
        ],
        "aggressiveoptz:liquid_falling" => [
            "enabled" => true,
            "configuration" => []
        ]
    ];

    /** @var AggressiveOptzAPI */
    private AggressiveOptzAPI $api;

    public function enable(MMOPlugin $plugin): void
    {
        $this->api = new AggressiveOptzAPI($plugin);
        $this->loadComponentsFromConfig(self::COMPONENTS_CONFIG);
        $this->api->init();
    }

    /**
     * @param array<string, mixed> $config
     *
     * @phpstan-param array<string, array{enabled: bool, configuration: array<string, mixed>}> $config
     */
    public function loadComponentsFromConfig(array $config): void
    {
        $component_manager = $this->api->getComponentManager();

        $component_manager->enable("aggressiveoptz:falling_block");
        $component_manager->enable("aggressiveoptz:liquid_falling");
    }

    public function getApi(): AggressiveOptzAPI
    {
        return $this->api;
    }

    protected function onDisable(): void
    {
    }
}