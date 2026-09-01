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

namespace libMMO\utils;

use NetherGames\NGEssentials\player\permissions\Permissions as NGPermissions;
use pocketmine\player\Player;

/**
 * Base permissions class, used to modify permissions made throughout the server.
 */
class Permissions
{
    /** @var bool */
    private static bool $initialized = false;

    /** @var string[] */
    public static array $basePermission = []; // Base permission, basic permission.
    /** @var string[] */
    public static array $elevatedPermission = []; // Reset progress, minihelper addition.

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$basePermission[] = NGPermissions::RANK_TRAINEE;
        self::$elevatedPermission[] = NGPermissions::RANK_MOD;
        self::$elevatedPermission[] = NGPermissions::RANK_DEVELOPER;

        self::$initialized = true;
    }

    /**
     * Indicate that the player has basic permission to perform basic moderation task
     * that does not require any modifications of the player itself.
     *
     * @param Player $player
     * @return bool
     */
    public static function hasPermission(Player $player): bool
    {
        self::init();

        foreach (self::$basePermission as $permission) {
            if ($player->hasPermission($permission)) {
                return true;
            }
        }

        return self::hasElevatedPermission($player);
    }

    /**
     * Indicate that the player has full authority against the player and is able
     * to modify anything in their behalf.
     *
     * @param Player $player
     * @return bool
     */
    public static function hasElevatedPermission(Player $player): bool
    {
        self::init();

        foreach (self::$elevatedPermission as $permission) {
            if ($player->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}