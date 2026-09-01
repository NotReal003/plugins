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

namespace factions\player\tags;

use GlobalLogger;
use libVanilla\LibVanillaItems;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\TextFormat;

/**
 * Static based tag management system, uses integer to store player
 * tag data. It is more efficient as we want to use less database storage.
 * It's also scalable and maintainable if we want to put more tags.
 *
 * @package factions\player\tags
 */
class TagManager
{
    public const ID_TO_TAGS = [
        0x01 => "FBI",
        0x02 => "Tryhard",
        0x03 => "Win10FTW",
        0x04 => "Momma",
        0x05 => "Call-um",
        0x06 => "K3ith-OS",
        0x07 => "2k21",
        0x08 => "Salty",
        0x09 => "ChangeMyMind",
        0x0A => "Meme",
        0x0B => "DriesIsABoy",
        0x0C => "NoU",
        0x0D => "DidIAsk?",
        0x0E => "E-Girl",
        0x0F => "OG",
        0x10 => "Hacker",
        0x11 => "God",
        0x12 => "Potato",
        0x13 => "ObamaCare"
    ];

    public static function searchTagsId(string $tag): ?int
    {
        $tagId = array_search($tag, self::ID_TO_TAGS, true);

        return is_integer($tagId) ? $tagId : null;
    }

    /**
     * Parse the given binary flags from database into a series
     * of string tags.
     *
     * @param int $tagBinary
     * @return string[]
     */
    public static function flagsToTags(int $tagBinary): array
    {
        $tags = [];
        foreach (self::ID_TO_TAGS as $flagId => $tag) {
            if ((($tagBinary >> $flagId) & 1) === 1) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    /**
     * @param string[] $tagList
     * @return int
     */
    public static function tagsToFlags(array $tagList): int
    {
        $flags = 0x0;
        foreach ($tagList as $tagName) {
            if (in_array($tagName, self::ID_TO_TAGS)) {
                $flags |= 1 << array_search($tagName, self::ID_TO_TAGS);
            } else {
                GlobalLogger::get()->critical("Unable to find tag " . $tagName . ", something is wrong?");
            }
        }

        return $flags;
    }


    /**
     * Returns a random tags for the given total tags, there will be no duplicates of the tag for the
     * returned objects.
     *
     * @param int $totalTags
     * @return Item[]
     */
    public static function getRandomTag(int $totalTags): array
    {
        $tags = [];
        while (count($tags) < $totalTags) {
            $tagId = array_rand(TagManager::ID_TO_TAGS);
            $tagName = TagManager::ID_TO_TAGS[$tagId];

            if (isset($tags[$tagId])) {
                continue;
            }

            $nameTag = LibVanillaItems::NAME_TAG();
            $nameTag->setCustomName(TextFormat::RESET . TextFormat::LIGHT_PURPLE . $tagName . " Tag");
            $nameTag->setCustomBlockData(CompoundTag::create()->setInt('CustomTagId', $tagId)->setString('CustomTagName', $tagName));

            $tags[$tagId] = $nameTag;
        }

        return array_values($tags);
    }
}