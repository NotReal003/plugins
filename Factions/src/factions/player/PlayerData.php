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

namespace factions\player;

use Closure;
use factions\faction\object\Faction;
use factions\Factions;
use factions\player\tags\TagManager;
use factions\utils\GroupManager;
use factions\utils\object\HomeLocation;
use GlobalLogger;
use libMMO\MMOPlugin;
use libMMO\player\PlayerData as MMOPlayerData;
use pocketmine\player\Player;

class PlayerData extends MMOPlayerData
{

    public const REGISTER_DATE = 10;
    public const TAGS = 11;
    public const CURRENT_TAG = 12;
    public const KILLS = 13;
    public const KILL_STREAK = 14;
    public const BEST_STREAK = 15;
    public const FORM_BLOCKED = 16;
    public const DISABLE_HUD = 17;
    // DATA 18
    public const HOME_COORDINATES = 19;

    // RUNTIME DATA
    public const FACTION_ID = 20;
    public const TRANSFER_LOCATION = 22;
    public const COMMAND_COOLDOWN = 23;
    public const RUNTIME_TAGS = 24;
    public const AUTO_CLAIM = 25;
    public const OLD_PLAYER_NAME = 26;

    public function __construct(MMOPlugin $instance)
    {
        parent::__construct($instance);

        self::$offset = 1;
    }

    public function onPlayerChangeName(Player $player, string $oldName): void
    {
        parent::onPlayerChangeName($player, $oldName);

        $this->setValue($player, self::OLD_PLAYER_NAME, $oldName);

        $clientData = $player->getNetworkSession()->getPlayerInfo();
        if ($clientData !== null) {
            $deviceId = $clientData->getExtraData()['DeviceId'] ?? "NIL";
        } else {
            $deviceId = "NIL";
        }

        GlobalLogger::get()->warning("Player {$player->getName()} changed the name to $oldName, XUID: {$player->getXuid()}, Device Id: $deviceId");
    }

    public function isHudEnabled(Player|string $player): bool
    {
        return !$this->getBool($player, self::DISABLE_HUD);
    }

    public function setHudStatus(Player|string $player, bool $status = false): void
    {
        $this->setValue($player, self::DISABLE_HUD, !$status);
    }

    public function getKillStreak(Player|string $player): int
    {
        return $this->getInt($player, self::KILL_STREAK);
    }

    public function setKillStreak(Player|string $player, int $streak): void
    {
        $this->setValue($player, self::KILL_STREAK, $streak);
    }

    public function getBestStreak(Player|string $player): int
    {
        return $this->getInt($player, self::BEST_STREAK);
    }

    public function setBestStreak(Player|string $player, int $streak): void
    {
        $this->setValue($player, self::BEST_STREAK, $streak);
    }

    public function isAutoClaimEnabled(Player|string $player): bool
    {
        return $this->getBool($player, self::AUTO_CLAIM);
    }

    /**
     * @return Factions
     */
    public function getPlugin(): MMOPlugin
    {
        return parent::getPlugin();
    }

    /**
     * Attempt to search a faction for given player name.
     *
     * @param Player|string $player
     * @return Faction|null
     */
    public function getFaction(Player|string $player): ?Faction
    {
        $factionManager = $this->getPlugin()->getFactionManager();
        if (!$factionManager->isInFaction($player)) {
            return null;
        }

        return $factionManager->getFaction($this->getPlugin()->getPlayerData()->getInt($player, PlayerData::FACTION_ID));
    }

    /**
     * Set a home based on the given name into database.
     *
     * @param Player $player
     * @param string $homeName
     */
    public function setHomePosition(Player $player, string $homeName): void
    {
        $homeData = $this->getHomes($player);
        if (isset($homeData[$homeName])) {
            return;
        }

        $pos = $player->getPosition();
        $homeData[$homeName] = [$pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ(), $pos->getWorld()->getFolderName(), $this->getPlugin()->getEssentials()->getServerManager()->getServerRegion()];

        $this->setValue($player, self::HOME_COORDINATES, $homeData);
    }

    public function getHomes(Player|string $player): array
    {
        return $this->getArray($player, self::HOME_COORDINATES);
    }

    public function removeHome(Player $player, string $homeName): void
    {
        $homeData = $this->getHomes($player);
        if (!isset($homeData[$homeName])) {
            return;
        }
        unset($homeData[$homeName]);

        $this->setValue($player, self::HOME_COORDINATES, $homeData);
    }

    public function addKills(Player|string $player): void
    {
        $this->setValue($player, self::KILLS, $this->getKills($player) + 1);
    }

    public function getKills(Player|string $player): int
    {
        return $this->getInt($player, self::KILLS);
    }

    public function getHomeByName(Player|string $player, string $homeName): ?HomeLocation
    {
        $homeData = $this->getHomes($player);
        if (!isset($homeData[$homeName])) {
            return null;
        }
        $home = $homeData[$homeName];

        return new HomeLocation($homeName, $home[0], $home[1], $home[2], $home[3], $home[4]);
    }

    public function isFormBlocked(Player|string $player): bool
    {
        return $this->getBool($player, self::FORM_BLOCKED);
    }

    public function canExecuteCommand(Player|string $player): bool
    {
        $commandTime = $this->getInt($player, self::COMMAND_COOLDOWN);

        return (time() - $commandTime) >= 15;
    }

    public function setCommandTime(Player|string $player): void
    {
        $this->setValue($player, self::COMMAND_COOLDOWN, time());
    }

    public function addTags(Player|string $player, int $tagId): void
    {
        if (!array_key_exists($tagId, TagManager::ID_TO_TAGS)) {
            return;
        }

        $tags = $this->getOwnedTags($player);
        $tags[] = TagManager::ID_TO_TAGS[$tagId];

        $this->setValue($player, self::RUNTIME_TAGS, $tags);
        $this->setValue($player, self::TAGS, TagManager::tagsToFlags($tags), true);
    }

    /**
     * @param Player|string $player
     * @return string[]
     */
    public function getOwnedTags(Player|string $player): array
    {
        return $this->getArray($player, self::RUNTIME_TAGS);
    }

    /**
     * @param Player|string $player
     * @return string
     */
    public function getCurrentTag(Player|string $player): string
    {
        return TagManager::ID_TO_TAGS[$this->getInt($player, self::CURRENT_TAG)] ?? "";
    }

    /**
     * @param Player|string $player
     * @param string $tag
     * @return void
     */
    public function setCurrentTag(Player|string $player, string $tag): void
    {
        if (($tagId = array_search($tag, TagManager::ID_TO_TAGS)) === false) {
            return;
        }

        $this->setValue($player, self::CURRENT_TAG, $tagId);

        if ($player instanceof Player) {
            GroupManager::updateNameTag($player, true);
        }
    }

    public function loadMoneyBalance(Player|string $player, ?Closure $closure = null): void
    {
        if ($player instanceof Player) {
            $player = $player->getName();
        }

        $this->loadValue($player, MMOPlayerData::PLAYER_MONEY, function (int $currentBalance) use ($player, $closure): void {
            $this->setValue($player, self::PLAYER_MONEY, $currentBalance, load: true);

            if ($closure !== null) {
                $closure($currentBalance);
            }
        });
    }

    public function getColumnNames(): array
    {
        // Orphaned columns: groupId.
        return [
            self::PLAYER_INVENTORY => 'inventory',
            self::CRATE_KEYS => 'crate_keys',
            self::KIT_COOLDOWN => 'kit_cooldown',
            self::REGISTER_DATE => 'registerDate',
            self::TAGS => 'tags',
            self::CURRENT_TAG => 'currentTag',
            self::PLAYER_MONEY => 'coins',
            self::KILLS => 'kills',
            self::KILL_STREAK => 'streak',
            self::BEST_STREAK => 'bestStreak',
            self::ROLLBACK_INVENTORY => 'backup_inventory',
            self::BOUNTY => 'bounty',
            self::XP => 'xp',
            self::PRIVATE_VAULTS => 'vaults',
            self::EXTRA_DATA => 'extra_data',
            self::PLAYER_TRADE_CACHE => 'trade_cache',
            self::HOME_COORDINATES => 'home_coords',
            self::FORM_BLOCKED => 'form_status',
        ];
    }

    public function getDataTypes(): array
    {
        return parent::getDataTypes() + [
                self::CRATE_KEYS => self::ARRAY,
                self::KIT_COOLDOWN => self::ARRAY,
                self::REGISTER_DATE => self::INT,
                self::TAGS => self::INT,
                self::CURRENT_TAG => self::INT,
                self::KILLS => self::INT,
                self::KILL_STREAK => self::INT,
                self::BEST_STREAK => self::INT,
                self::EXTRA_DATA => self::ARRAY,
                self::HOME_COORDINATES => self::ARRAY,

                self::FACTION_ID => self::INT,
                self::FORM_BLOCKED => self::BOOL,
                self::TRANSFER_LOCATION => self::STRING,
                self::COMMAND_COOLDOWN => self::INT,
                self::RUNTIME_TAGS => self::ARRAY,
                self::AUTO_CLAIM => self::BOOL,
                self::OLD_PLAYER_NAME => self::STRING,
                self::DISABLE_HUD => self::BOOL,
            ];
    }
}