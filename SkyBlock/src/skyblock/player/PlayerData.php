<?php
/**
 *         _____ _          _     _            _
 *        / ____| |        | |   | |          | |
 *  __  _| (___ | | ___   _| |__ | | ___   ___| | __
 *  \ \/ /\___ \| |/ / | | | '_ \| |/ _ \ / __| |/ /
 *   >  < ____) |   <| |_| | |_) | | (_) | (__|   <
 *  /_/\_\_____/|_|\_\\__, |_.__/|_|\___/ \___|_|\_\
 *                     __/ |
 *                    |___/
 *
 * Copyright (C) 2016-2022 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew
 *
 */
declare(strict_types=1);

namespace skyblock\player;

use libMMO\MMOPlugin;
use skyblock\SkyBlock;

class PlayerData extends \libMMO\player\PlayerData
{
    // PUBLIC RUNTIME DATA
    public const TARGET_ISLAND = 10;
    public const TARGET_ISLAND_ADMIN = 11;
    // PRIVATE RUNTIME DATA
    public const HAS_ISLAND = 48;
    public const NEW_ISLAND = 49;
    public const KILL_STREAK = 50;

    public function getColumnNames(): array
    {
        return parent::getColumnNames() + [self::KILL_STREAK => 'kill_streak'];
    }

    /**
     * @return SkyBlock
     */
    public function getPlugin(): MMOPlugin
    {
        /** @var SkyBlock $plugin */
        $plugin = parent::getPlugin();

        return $plugin;
    }

    public function getDefaultValue(int $id)
    {
        if ($id === self::NEW_ISLAND) {
            return -1;
        }

        return parent::getDefaultValue($id);
    }

    public function getDataTypes(): array
    {
        return parent::getDataTypes() + [
                self::TARGET_ISLAND => self::STRING,
                self::TARGET_ISLAND_ADMIN => self::STRING,
                self::HAS_ISLAND => self::BOOL,
                self::NEW_ISLAND => self::INT,
                self::KILL_STREAK => self::INT
            ];
    }
}