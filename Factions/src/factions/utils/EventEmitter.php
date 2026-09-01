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

use factions\faction\object\Faction;
use factions\faction\object\OfflineFaction;
use factions\Factions;
use factions\player\PlayerData;
use factions\utils\object\FactionLocation;
use Generator;
use GlobalLogger;
use libMMO\MMOPlugin;
use libMMO\player\PlayerData as MMOPlayerData;
use pocketmine\player\Player;
use pocketmine\Server;
use SOFe\AwaitGenerator\Await;

/**
 * Responsible in synchronizing factions data across all servers.
 *
 * @package factions\faction
 */
class EventEmitter extends \libMMO\utils\EventEmitter
{
    public const CHANNEL_FACTION = 'faction:payload'; // ??
    public const CHANNEL_CHAT = 'faction:chat';       // OK

    public const EVENT_CHANGE_STREAK = 3;

    public const EVENT_CHANGE_STRENGTH = 0;     // OK [ NO DUPE IS POSSIBLE ]
    public const EVENT_CHANGE_ALLIES = 1;       //
    public const EVENT_FACTION_DISBAND = 2;     //
    public const EVENT_CHANGE_ROLES = 3;        //
    public const EVENT_CHANGE_LEADER = 4;       // OK
    public const EVENT_CHANGE_HOME = 5;         // ?? [ TO DO ]
    public const EVENT_UPDATE_BALANCE = 6;      // OK [ NO DUPE IS POSSIBLE ]
    public const EVENT_UPDATE_NAME = 7;         // ??
    public const EVENT_CHANGE_MOTD = 8;         //
    public const EVENT_CHANGE_FACTION_NAME = 9; // ??
    public const EVENT_UPDATE_PERMISSION = 10;
    public const EVENT_UPDATE_AUTO_KICK_DAYS = 11;
    public const EVENT_UPDATE_AUTO_KICK_DEATHS = 12;
    public const EVENT_DELETE_HOME = 14;

    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct($plugin);

        $playerData = $this->getPlugin()->getPlayerData();
        $this->addListener(function (int $notificationId, string $channel, string $payload) use ($plugin, $playerData): void {
            switch ($channel) {
                case self::CHANNEL_DEFAULT:
                    if ($notificationId === self::EVENT_CHANGE_STREAK) {
                        $data = json_decode($payload, true, 1024, JSON_THROW_ON_ERROR);

                        $player = $plugin->getServer()->getPlayerExact($data[0]);
                        if ($player === null || !$player->isConnected() || !$playerData->getBool($player, MMOPlayerData::DATA_LOADED)) {
                            break;
                        }

                        $playerData->setValue($player->getName(), PlayerData::KILL_STREAK, $data[1][0], load: true);
                    }
                    break;
                case self::CHANNEL_CHAT:
                    [$message, $factionId, $allies] = json_decode($payload, true, 1024, JSON_THROW_ON_ERROR);

                    $factionManager = $this->getPlugin()->getFactionManager();
                    $faction = $factionManager->getFaction($factionId);
                    $members = $faction !== null ? $faction->getMembers() : [];

                    if ($allies) {
                        $allies = $factionManager->getFactionAlly($factionId);

                        foreach ($allies as $ally) {
                            $members = array_merge($members, $ally->getMembers());
                        }
                    }

                    $receivers = [];
                    foreach ($members as $receiver) {
                        if (($player = $plugin->getServer()->getPlayerExact($receiver)) !== null) {
                            $receivers[] = $player;
                        }
                    }

                    $plugin->getServer()->broadcastMessage($message, $receivers);
                    break;
                case self::CHANNEL_FACTION:
                    [$factionId, $factionName, $data] = json_decode($payload, true, 1024, JSON_THROW_ON_ERROR);

                    $factionManager = $this->getPlugin()->getFactionManager();
                    $faction = $factionManager->getFaction($factionId);
                    $offlineFaction = new OfflineFaction($factionId, $factionName);

                    switch ($notificationId) {
                        case self::EVENT_CHANGE_STRENGTH:
                            $faction?->setStrength($data[0]);

                            if (Factions::isBadlands()) {
                                break;
                            }

                            foreach ($this->getPlugin()->getClaimManager()->getClaimsByFaction($offlineFaction) as $claims) {
                                $claims->setStrength($data[0]);
                            }
                            break;
                        case self::EVENT_CHANGE_ALLIES:
                            [$eventId, $allyFactionId, $allyFactionName] = $data;

                            $allyFaction = $factionManager->getFaction($allyFactionId);

                            if ($eventId === 0) {
                                $allyFaction?->addAllies(new OfflineFaction($factionId, $factionName));
                                $faction?->addAllies(new OfflineFaction($allyFactionId, $allyFactionName));
                            } else {
                                $allyFaction?->removeAlly(new OfflineFaction($factionId, $factionName));
                                $faction?->removeAlly(new OfflineFaction($allyFactionId, $allyFactionName));
                            }
                            break;
                        case self::EVENT_FACTION_DISBAND:
                            $players = [];
                            foreach (($faction?->getMembers() ?? []) as $member) {
                                $target = Server::getInstance()->getPlayerExact($member);
                                if ($target instanceof Player && $target->isConnected()) {
                                    $playerData->setValue($target, PlayerData::FACTION_ID, 0);

                                    $factionManager->collectGarbage($target, $faction);

                                    $players[] = $target;
                                }
                            }

                            $scoreboard = $this->getPlugin()->getEssentials()->getServerData()->getScoreBoard();
                            $scoreboard->setLines($players, [12 => '', 13 => '', 14 => '', 15 => ''], true);

                            foreach ($data as $ally) {
                                $allyFaction = $factionManager->getFaction($ally);

                                $allyFaction?->removeAlly($offlineFaction);
                                $allyFaction?->subtractFromStrength(0);
                            }

                            if (!Factions::isBadlands()) {
                                $this->getPlugin()->getClaimManager()->purgeClaims($factionId);
                            }
                            break;
                        case self::EVENT_CHANGE_ROLES:
                            [$member, $role] = $data;

                            if ($faction?->isMember($member)) {
                                $faction->setMemberRole($member, $role, updateTags: true);
                            } else {
                                $faction?->addMember($member, $role, updateTags: true);
                            }
                            break;
                        case self::EVENT_CHANGE_LEADER:
                            $faction?->setLeader($data[0], updateTags: true);
                            break;
                        case self::EVENT_CHANGE_HOME:
                            $faction?->setHomeCoordinates(new FactionLocation($data[0], $data[1], $data[2], $data[3], $data[4]));
                            break;
                        case self::EVENT_DELETE_HOME:
                            $faction?->unsetHomeCoordinates();
                            break;
                        case self::EVENT_UPDATE_BALANCE:
                            $faction?->setBalance($data[0]);
                            break;
                        case self::EVENT_UPDATE_NAME:
                            $faction?->updatePlayerName($data[0], $data[1], false);
                            break;
                        case self::EVENT_CHANGE_MOTD:
                            $faction?->setMotd($data[0]);
                            break;
                        case self::EVENT_CHANGE_FACTION_NAME:
                            $faction?->setFactionName($data[0]);
                            break;
                        case self::EVENT_UPDATE_PERMISSION:
                            $faction?->updatePermission(null, $data[0], $data[1], false);
                            break;
                        case self::EVENT_UPDATE_AUTO_KICK_DAYS:
                            $faction?->setAutoKickDays($data[0], false);
                            break;
                        case self::EVENT_UPDATE_AUTO_KICK_DEATHS:
                            $faction?->setAutoKickDeaths($data[0], false);
                            break;
                        default:
                            GlobalLogger::get()->warning("[Synchronizer] Unknown event id $notificationId for faction id $factionId payload " . $payload);
                    }

                    break;
            }
        });
    }

    /**
     * @param Faction|OfflineFaction $faction The main faction id of the
     * @param int $event The event id of a broadcaster event.
     * @param array|null $data Usually an array of the event containing important data for servers.
     */
    public function broadcastEvent(Faction|OfflineFaction $faction, int $event, ?array $data = null): void
    {
        $this->publishEvent(json_encode([$faction->getFactionId(), $faction->getFactionName(), $data]), $event, self::CHANNEL_FACTION);
    }

    /**
     * Publish chat messages to all related servers.
     */
    public function broadcastChatMessages(string $message, int $factionId, bool $allies = false): void
    {
        $this->publishEvent(json_encode([$message, $factionId, $allies]), 0, self::CHANNEL_CHAT);
    }

    /**
     * @return Factions
     */
    public function getPlugin(): MMOPlugin
    {
        return parent::getPlugin();
    }
}