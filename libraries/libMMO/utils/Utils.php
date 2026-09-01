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

use DateTime;
use GlobalLogger;
use libMMO\item\enchantment\CustomEnchantment;
use libMMO\utils\rollback\RollbackEngine;
use pocketmine\block\BlockTypeIds;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\Tag;
use pocketmine\nbt\TreeRoot;
use pocketmine\utils\TextFormat;
use RuntimeException;

class Utils
{
    public const READONLY_TAGS = 'readonly';

    public static function readOnlyTag(): CompoundTag
    {
        return CompoundTag::create()->setInt(self::READONLY_TAGS, 0);
    }

    /**
     * Check if an item is read-only.
     */
    public static function isReadOnlyItem(Item $item): bool
    {
        return ($item->getCustomBlockData() !== null && self::hasTag($item->getCustomBlockData(), self::READONLY_TAGS)) ||
            in_array(TextFormat::RESET . TextFormat::AQUA . 'Seller: ', $item->getLore()) ||
            in_array(TextFormat::RESET . TextFormat::YELLOW . 'Price: ', $item->getLore()) ||
            in_array(TextFormat::RESET . TextFormat::GRAY . 'Click to confirm purchase.', $item->getLore()) || RollbackEngine::isIllegalItem($item);
    }

    /**
     * Derives the snowflake machine id from the last two octets of this node's address.
     *
     * Outside Kubernetes there is no POD_IP, so fall back to the machine's own address instead of
     * refusing to start. Note that two instances sharing a host derive the same id, so set POD_IP
     * explicitly when running several on one machine.
     */
    public static function generateMachineId(): int
    {
        $podIp = getenv("POD_IP");
        if (empty($podIp)) {
            $podIp = self::getLocalAddress();

            GlobalLogger::get()->notice("'POD_IP' is not set, deriving the machine id from the local address $podIp instead. Set POD_IP explicitly if you run more than one instance on this host.");
        }

        $ip = explode('.', $podIp);

        return ((int)($ip[2] ?? 0) << 8) + (int)($ip[3] ?? 0);
    }

    /**
     * Best-effort lookup of this machine's own IPv4 address, falling back to loopback.
     */
    private static function getLocalAddress(): string
    {
        $hostname = gethostname();

        if ($hostname !== false) {
            $resolved = gethostbyname($hostname);

            if ($resolved !== $hostname && filter_var($resolved, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                return $resolved;
            }
        }

        return '127.0.0.1';
    }

    /**
     * Clear all bad items.
     *
     * @param Item[] $contents
     * @return Item[]
     */
    public static function doInventoryCleanup(array $contents): array
    {
        foreach ($contents as $id => $item) {
            if (self::isReadOnlyItem($item)) {
                unset($contents[$id]);
            }
        }

        return $contents;
    }

    /**
     * @param Item[] $contents
     * @return array
     */
    public static function doInventoryCheck(array $contents): array
    {
        $curseVL = 0;
        $maxLore = 0;
        $loreTileVL = 0;
        $durabilityVL = self::detectSameDurability($contents);
        foreach ($contents as $item) {
            if (!($item instanceof Durable)) {
                continue;
            }

            if (isset($itemsLore[$item->getStateId()])) {
                foreach ($itemsLore[$item->getStateId()] as $lore) {
                    $maxLore = max($maxLore, count($item->getLore()));

                    if (!empty($item->getLore()) && $item->getLore() === $lore) {
                        $loreTileVL++;
                        break;
                    }
                }
            }

            if ($item->getEnchantment(VanillaEnchantments::VANISHING()) !== null) {
                $curseVL++;
            }

            if (!empty($item->getLore())) {
                $itemsLore[$item->getStateId()][] = $item->getLore();
            }
        }

        return [$durabilityVL, $loreTileVL, $maxLore, $curseVL];
    }

    /**
     * @param Item[] $contents
     * @return int
     */
    private static function detectSameDurability(array $contents): int
    {
        $violation = 0;

        /** @var Item[] $durableItems */
        $durableItems = [];
        foreach ($contents as $item) {
            $enchantments = array_filter($item->getEnchantments(), function ($enchantment): bool {
                /** @phpstan-ignore-next-line */
                return $enchantment instanceof CustomEnchantment;
            });

            /** @phpstan-ignore-next-line */
            if ($item instanceof Durable && $item->getDamage() > 0 && count($enchantments) > 0) {
                if (!empty($durableItems)) {
                    foreach ($durableItems as $data) {
                        if ($data->equalsExact($item)) {
                            $violation++;
                        }
                    }
                }

                $durableItems[] = $item;
            }
        }

        return $violation;
    }

    /**
     * Returns whether the CompoundTag contains a child tag with the specified name.
     *
     * @phpstan-param class-string<Tag> $expectedClass
     */
    public static function hasTag(CompoundTag $tag, string $name, string $expectedClass = Tag::class): bool
    {
        assert(is_a($expectedClass, Tag::class, true));
        return $tag->getTag($name) instanceof $expectedClass;
    }

    /**
     * @param float $percentage in range of 0-1
     * @return string
     */
    public static function nicePercentFormat(float $percentage): string
    {
        $range = $percentage * 20;

        $values = TextFormat::GREEN . '';
        for ($i = 1; $i <= 20; $i++) {
            if ($range >= $i) {
                $values .= TextFormat::WHITE . '|';
            } else {
                $values .= TextFormat::GRAY . '|';
            }
        }

        return TextFormat::DARK_GRAY . '[' . $values . TextFormat::DARK_GRAY . ']';
    }

    /**
     * Evaluate if the player inventory can insert specific x1 items.
     *
     * @param PlayerInventory $inventory
     * @param int $targetItems
     * @return bool
     */
    public static function canAddItems(PlayerInventory $inventory, int $targetItems): bool
    {
        $contents = array_filter($inventory->getContents(true), function (Item $item): bool {
            return ItemTypeIds::toBlockTypeId($item->getTypeId()) === BlockTypeIds::AIR;
        });

        return count($contents) >= $targetItems;
    }


    /**
     * @param float $dist
     * @return float
     */
    public static function getGrapplingSpeed(float $dist): float
    {
        if ($dist > 600) {
            return 0.26;
        }
        if ($dist > 500) {
            return 0.24;
        }
        if ($dist > 300) {
            return 0.23;
        }
        if ($dist > 200) {
            return 0.201;
        }
        if ($dist > 100) {
            return 0.17;
        }
        if ($dist > 40) {
            return 0.11;
        }
        return 0.8;
    }

    public static function zlibEncodeItem(Item $item): string
    {
        $serializer = new BigEndianNbtSerializer();
        $nbt = $item->nbtSerialize();

        return bin2hex(zstd_compress($serializer->write(new TreeRoot($nbt))));
    }

    public static function zlibDecodeItem(string $data): Item
    {
        $serializer = new BigEndianNbtSerializer();
        $nbt = $serializer->read(zstd_uncompress(hex2bin($data)))->mustGetCompoundTag();

        return Item::nbtDeserialize($nbt);
    }

    public static function decodeItem(string $data): Item
    {
        try {
            return self::zlibDecodeItem($data);
        } catch (RuntimeException $e) {
            return Item::legacyJsonDeserialize(json_decode($data, true, 512, JSON_THROW_ON_ERROR));
        }
    }

    public static function timeElapsedString(int $timestamp, bool $full = false): string
    {
        $now = new DateTime;
        $ago = new DateTime();
        $ago->setTimestamp($timestamp);
        $diff = $now->diff($ago);

        $string = array(
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
}