<?php
/**
 *        ______         _   _
 *       |  ____|       | | (_)
 *  __  _| |__ __ _  ___| |_ _  ___  _ __  ___
 *  \ \/ /  __/ _` |/ __| __| |/ _ \| '_ \/ __|
 *   >  <| | | (_| | (__| |_| | (_) | | | \__ \
 *  /_/\_\_|  \__,_|\___|\__|_|\___/|_| |_|___/
 *
 * Copyright (C) 2016-2021 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author larryTheCoder
 */

declare(strict_types=1);

namespace factions\utils;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\utils\TextFormat;

class Utils
{
    public const N = 'N';
    public const NE = '/';
    public const E = 'E';
    public const SE = '\\';
    public const S = 'S';
    public const SW = '/';
    public const W = 'W';
    public const NW = '\\';

    public const HEX_SYMBOL = 'e29688';

    public static function niceNumber(int $value): string
    {
        return $value === 0 ? TextFormat::RED . $value . TextFormat::RESET : ($value <= 5 ? TextFormat::YELLOW . $value . TextFormat::RESET : TextFormat::GREEN . $value . TextFormat::RESET);
    }

    public static function hasFlag(int $flags, int $flag): bool
    {
        return ($flags & $flag) === $flag;
    }

    /**
     * @param string $name
     * @return bool
     */
    public static function checkFactionName(string $name): bool
    {
        foreach (['owner', '0wner', '0wn3r', 'admin', '4dmin', '4dm1n', 'mod', 'm0d', 'crew', 'cr3w', 'train', 'tr4in', 'tra1n', 'tr41n'] as $term) {
            if (stripos($name, $term) !== false) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param string[] $names
     * @return string
     */
    public static function niceArrayString(array $names): string
    {
        $result = "";
        foreach ($names as $name) {
            $result .= ($name . ", ");
        }

        return substr($result, 0, strlen($result) - 2);
    }

    public static function getRandomKillMessage(int $cause, bool $tagged = false): string
    {
        switch ($cause) {
            case EntityDamageEvent::CAUSE_ENTITY_ATTACK:
                $messages = [
                    '{PLAYER} §r§7was killed by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was struck down by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was turned to dust by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was turned to ash by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was melted by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was filled full of lead by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7met their end by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7died in combat with {DAMAGER}§r§7.',
                    '{PLAYER} §r§7fell to the great marksmanship of {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was given the cold shoulder by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was out of the league of {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was no match for {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was glazed in BBQ sauce by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was not spicy enough for {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was wrapped into a gift for {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was put on the naughty list by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was turned into gingerbread by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was bit by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7be sent to Davy Jones\' locker by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7be killed by magic by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was spooked by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was totally spooked by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was tragically backstabbed by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was heartlessly let go by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was rekt by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7took the L to {DAMAGER}§r§7.',
                    '{PLAYER} §r§7got roasted by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was smacked by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was bested by {DAMAGER}§r§7.'
                ];
                break;
            case EntityDamageEvent::CAUSE_PROJECTILE:
                $messages = [
                    '{PLAYER} §r§7was killed by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was struck down by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was turned to dust by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was turned to ash by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was melted by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was filled full of lead by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7met their end by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7died in combat with {DAMAGER}§r§7.',
                    '{PLAYER} §r§7fell to the great marksmanship of {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was given the cold shoulder by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was out of the league of {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was no match for {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was glazed in BBQ sauce by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was not spicy enough for {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was wrapped into a gift for {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was put on the naughty list by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was turned into gingerbread by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was bit by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7be sent to Davy Jones\' locker by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7be killed by magic by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was spooked by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was totally spooked by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was tragically backstabbed by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was heartlessly let go by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was rekt by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7took the L to {DAMAGER}§r§7.',
                    '{PLAYER} §r§7got roasted by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was smacked by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was bested by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was shot by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was thrown chilli powder at by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7caught the ball thrown by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was shot and killed by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7be killed with metal by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7was remotely spooked by {DAMAGER}§r§7.',
                    '{PLAYER} §r§7heart was pierced by {DAMAGER}§r§7.'
                ];
                break;
            case EntityDamageEvent::CAUSE_FALL:
                if ($tagged) {
                    $messages = [
                        '{PLAYER} §r§7was knocked off a cliff by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was turned to dust by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was turned to ash by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7met their end by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7fought to the edge with {DAMAGER}§r§7.',
                        '{PLAYER} §r§7fell to the great marksmanship of {DAMAGER}§r§7.',
                        '{PLAYER} §r§7stumbled off a ledge with the help of {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was given the cold shoulder by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was out of the league of {DAMAGER}§r§7.',
                        '{PLAYER} §r§7slipped in BBQ sauce off the edge spilled by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7hit the hard wood floor because of {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was pushed down a slope by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was turned into gingerbread by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was spooked off the map by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was heartlessly let go by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was rekt by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7took the L to {DAMAGER}§r§7.',
                        '{PLAYER} §r§7got roasted by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was smacked by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was bested by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was knocked off the edge by {DAMAGER}§r§7.'
                    ];
                } else {
                    $messages = [
                        '{PLAYER} §r§7fell to their death.',
                        '{PLAYER} §r§7fell off a cliff.',
                        '{PLAYER} §r§7was turned to dust.',
                        '{PLAYER} §r§7was turned to ash.',
                        '{PLAYER} §r§7stumbled off a ledge.',
                        '{PLAYER} §r§7slipped in BBQ sauce off the edge.',
                        '{PLAYER} §r§7hit the hard wood floor.'
                    ];
                }
                break;
            case EntityDamageEvent::CAUSE_VOID:
                if ($tagged) {
                    $messages = [
                        '{PLAYER} §r§7was knocked into the void by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was knocked off a cliff by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was turned to dust by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was turned to ash by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7met their end by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7fought to the edge with {DAMAGER}§r§7.',
                        '{PLAYER} §r§7fell to the great marksmanship of {DAMAGER}§r§7.',
                        '{PLAYER} §r§7stumbled off a ledge with the help of {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was given the cold shoulder by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was out of the league of {DAMAGER}§r§7.',
                        '{PLAYER} §r§7slipped in BBQ sauce off the edge spilled by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was pushed down a slope by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was turned into gingerbread by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7howled into the void for {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was cannonballed to death by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was spooked off the map by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was heartlessly let go by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was delivered into nothingness by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was rekt by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7took the L to {DAMAGER}§r§7.',
                        '{PLAYER} §r§7got roasted by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was smacked by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was bested by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was knocked off the edge by {DAMAGER}§r§7.'
                    ];
                } else {
                    $messages = [
                        '{PLAYER} §r§7fell to their death.',
                        '{PLAYER} §r§7fell into the void.',
                        '{PLAYER} §r§7fell off a cliff.',
                        '{PLAYER} §r§7was turned to dust.',
                        '{PLAYER} §r§7was turned to ash.',
                        '{PLAYER} §r§7stumbled off a ledge.',
                        '{PLAYER} §r§7slipped in BBQ sauce off the edge.',
                        '{PLAYER} §r§7howled into the void.',
                        '{PLAYER} §r§7fell into nothingness.'
                    ];
                }
                break;
            case EntityDamageEvent::CAUSE_LAVA:
                if ($tagged) {
                    $messages = [
                        '{PLAYER} §r§7was turned to dust by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was turned to ash by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was melted by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7met their end by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7fell to the great marksmanship of {DAMAGER}§r§7.',
                        '{PLAYER} §r§7stumbled off a ledge with the help of {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was given the cold shoulder by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was out of the league of {DAMAGER}§r§7.',
                        '{PLAYER} §r§7slipped in BBQ sauce off the edge spilled by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was turned into gingerbread by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was cannonballed to death by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was heartlessly let go by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was rekt by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7took the L to {DAMAGER}§r§7.',
                        '{PLAYER} §r§7got roasted by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was smacked by {DAMAGER}§r§7.',
                        '{PLAYER} §r§7was bested by {DAMAGER}§r§7.'
                    ];
                } else {
                    $messages = [
                        '{PLAYER} §r§7fell to their death.',
                        '{PLAYER} §r§7fell off a cliff.',
                        '{PLAYER} §r§7was turned to dust.',
                        '{PLAYER} §r§7was turned to ash.',
                        '{PLAYER} §r§7stumbled off a ledge.',
                        '{PLAYER} §r§7slipped in BBQ sauce off the edge.'
                    ];
                }
                break;
            default:
                $messages = [
                    '{PLAYER} §r§7died.'
                ];
                break;
        }

        return $messages[array_rand($messages)];
    }

    /**
     * @return string
     */
    public static function getMapBlock(): string
    {
        return hex2bin(self::HEX_SYMBOL);
    }

    /**
     * @param int $degrees
     * @param string $colorActive
     * @param string $colorDefault
     *
     * @return array
     */
    public static function getASCIICompass(int $degrees, string $colorActive, string $colorDefault): array
    {
        $ret = [];
        $point = self::getCompassPointForDirection($degrees);
        $row = '';
        $row .= ($point === 'NW' ? $colorActive : $colorDefault) . self::NW;
        $row .= ($point === 'N' ? $colorActive : $colorDefault) . self::N;
        $row .= ($point === 'NE' ? $colorActive : $colorDefault) . self::NE;
        $ret[] = $row;
        $row = '';
        $row .= ($point === 'W' ? $colorActive : $colorDefault) . self::W;
        $row .= $colorDefault . '+';
        $row .= ($point === 'E' ? $colorActive : $colorDefault) . self::E;
        $ret[] = $row;
        $row = '';
        $row .= ($point === 'SW' ? $colorActive : $colorDefault) . self::SW;
        $row .= ($point === 'S' ? $colorActive : $colorDefault) . self::S;
        $row .= ($point === 'SE' ? $colorActive : $colorDefault) . self::SE;
        $ret[] = $row;
        return $ret;
    }

    /**
     * @param int $degrees
     *
     * @return null|string
     */
    public static function getCompassPointForDirection(int $degrees): ?string
    {
        $degrees = ($degrees - 180) % 360;
        if ($degrees < 0) {
            $degrees += 360;
        }
        if (0 <= $degrees && $degrees < 22.5) {
            return 'N';
        }
        if (22.5 <= $degrees && $degrees < 67.5) {
            return 'NE';
        }
        if (67.5 <= $degrees && $degrees < 112.5) {
            return 'E';
        }
        if (112.5 <= $degrees && $degrees < 157.5) {
            return 'SE';
        }
        if (157.5 <= $degrees && $degrees < 202.5) {
            return 'S';
        }
        if (202.5 <= $degrees && $degrees < 247.5) {
            return 'SW';
        }
        if (247.5 <= $degrees && $degrees < 292.5) {
            return 'W';
        }
        if (292.5 <= $degrees && $degrees < 337.5) {
            return 'NW';
        }
        if (337.5 <= $degrees && $degrees < 360.0) {
            return 'N';
        }
        return null;
    }

}