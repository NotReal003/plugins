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

namespace factions\faction\object;

use Closure;
use factions\economy\EconomyManager;
use factions\faction\claims\Claim;
use factions\Factions;
use factions\player\PlayerData;
use factions\task\teleport\TeleportTask;
use factions\task\teleport\TransferServerLogicTask;
use factions\utils\BaseClass;
use factions\utils\Database;
use factions\utils\EventEmitter;
use factions\utils\GroupManager;
use factions\utils\object\FactionLocation;
use factions\utils\Utils;
use Generator;
use GlobalLogger;
use libMMO\MMOPlugin;
use libMMO\player\PlayerData as MMOPlayerData;
use NetherGames\NGEssentials\player\Translator;
use pocketmine\math\AxisAlignedBB;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use poggit\libasynql\SqlError;
use RuntimeException;
use SOFe\AwaitGenerator\Await;
use Throwable;

class Faction extends BaseClass
{
    public const MAX_TOP_FACTIONS_MEMBERS = 30;
    public const MAX_NORMAL_MEMBERS = 25;
    public const MAX_ALLIES_SIZE = 5;

    public const REMOVED = -1;
    public const MEMBER = 0;
    public const OFFICER = 1;
    public const LEADER = 2;

    public const PERMISSIONS_SIZE = 5;
    public const DEFAULT_PERMISSION = self::ALLOW_STRENGTH_MODIFIER | self::ALLOW_TELEPORT_BASE;
    public const DEFAULT_OFFICER_PERMISSION = self::ALLOW_STRENGTH_MODIFIER | self::ALLOW_BASE_BUILD | self::ALLOW_BASE_INTERACTION | self::ALLOW_TELEPORT_BASE | self::ALLOW_ECONOMY_WITHDRAWAL;
    public const ALLOW_STRENGTH_MODIFIER = 1 << 0;
    public const ALLOW_BASE_BUILD = 1 << 1;
    public const ALLOW_BASE_INTERACTION = 1 << 2;
    public const ALLOW_TELEPORT_BASE = 1 << 3;
    public const ALLOW_ECONOMY_WITHDRAWAL = 1 << 4;

    public const ADD_MEMBER_LOCKED = 0;
    public const ADD_MEMBER_FULL = 1;
    public const ADD_MEMBER_EXISTS = 2;
    public const ADD_MEMBER_OK = 3;
    public const ADD_MEMBER_ERROR = 4;

    public const ADD_ALLY_LOCKED = 0;
    public const ADD_ALLY_EXISTS = 1;
    public const ADD_ALLY_FULL = 2;
    public const ADD_ALLY_PARENT_FULL = 3;
    public const ADD_ALLY_OK = 4;
    public const ADD_ALLY_ERROR = 5;

    public const REMOVE_ALLY_LOCKED = 0;
    public const REMOVE_ALLY_NOT_EXISTS = 1;
    public const REMOVE_ALLY_OK = 2;
    public const REMOVE_ALLY_ERROR = 3;

    private bool $isOperationLocked = false;
    private bool $allyOperationLocked = false;

    /** @var AxisAlignedBB|null */
    private ?AxisAlignedBB $factionFlyArea = null;

    // Constructor promotion keeps the field list and the constructor in one place.
    public function __construct(
        MMOPlugin                $instance,
        private int              $factionId,
        private string           $factionName,
        private string           $leader,
        private ?FactionLocation $factionHome = null,
        private string           $motd = '',
        private int              $balance = 0,
        private int              $strength = 100,
        private array            $permissions = [],
        private int              $autoKickDays = 0,
        private int              $autoKickDeaths = 0,
        private int              $maxFactionSize = self::MAX_NORMAL_MEMBERS,
        private int              $maxAlliesSize = self::MAX_ALLIES_SIZE,
        private array            $allyFactions = [],
        private array            $officers = [],
        private array            $members = [])
    {
        parent::__construct($instance);

        $this->members[] = $this->leader;

        if ($this->factionHome !== null && $this->factionHome->isValidServer()) {
            $minX = min($this->factionHome->getFloorX() + 30, $this->factionHome->getFloorX() - 30);
            $minY = min($this->factionHome->getFloorY() + 30, $this->factionHome->getFloorY() - 30);
            $minZ = min($this->factionHome->getFloorZ() + 30, $this->factionHome->getFloorZ() - 30);
            $maxX = max($this->factionHome->getFloorX() + 30, $this->factionHome->getFloorX() - 30);
            $maxY = max($this->factionHome->getFloorY() + 30, $this->factionHome->getFloorY() - 30);
            $maxZ = max($this->factionHome->getFloorZ() + 30, $this->factionHome->getFloorZ() - 30);

            $this->factionFlyArea = new AxisAlignedBB($minX, $minY, $minZ, $maxX, $maxY, $maxZ);
        }
    }

    /**
     * @return string[]
     */
    public function getMembers(bool $onlyMember = false): array
    {
        return $onlyMember ? array_diff($this->members, array_merge($this->getOfficers(), [$this->getLeader()])) : $this->members;
    }

    public function isMember(string $playerName): bool
    {
        return in_array($playerName, $this->getMembers(), true);
    }

    public function getMemberByPrefix(string $playerName): ?string
    {
        $found = null;
        $name = strtolower($playerName);
        $delta = PHP_INT_MAX;
        foreach ($this->getMembers() as $player) {
            if (stripos($player, $name) === 0) {
                $curDelta = strlen($player) - strlen($name);
                if ($curDelta < $delta) {
                    $found = $player;
                    $delta = $curDelta;
                }
                if ($curDelta === 0) {
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * @return string
     */
    public function getLeader(): string
    {
        return $this->leader;
    }

    /**
     * @return string[]
     */
    public function getOfficers(): array
    {
        return $this->officers;
    }

    /**
     * @param Player|string $sender
     * @return int
     */
    public function getFactionRole(Player|string $sender): int
    {
        if ($sender instanceof Player) {
            $sender = $sender->getName();
        }

        if ($this->leader === $sender) {
            return self::LEADER;
        } else if (in_array($sender, $this->officers)) {
            return self::OFFICER;
        } else {
            return self::MEMBER;
        }
    }

    /**
     * @return string
     */
    public function getFactionName(): string
    {
        return $this->factionName;
    }

    /**
     * @return int
     */
    public function getFactionId(): int
    {
        return $this->factionId;
    }

    /**
     * @return string
     */
    public function getMotd(): string
    {
        return $this->motd;
    }

    /**
     * @param string $description
     * @param bool $update
     */
    public function setMotd(string $description, bool $update = false): void
    {
        $this->motd = $description;

        if ($update) {
            Database::executeInsert(Database::UPDATE_FACTION_MOTD, [
                'faction_id' => $this->factionId,
                'motd' => $description
            ]);

            $this->getPlugin()->getEventEmitter()->broadcastEvent($this, EventEmitter::EVENT_CHANGE_MOTD, [$description]);
        }
    }

    // ------------------------------------------------ ALLY OPERATIONS ------------------------------------------------

    /**
     * @param OfflineFaction|Faction $faction
     * @param bool $pushChanges
     * @param Closure|null $onSuccess
     *
     * @phpstan-param Closure(int) : void $onSuccess
     */
    public function addAllies(OfflineFaction|Faction $faction, bool $pushChanges = false, ?Closure $onSuccess = null): void
    {
        Await::f2c(function () use ($faction, $pushChanges): Generator {
            $this->allyFactions[$faction->getFactionId()] = new OfflineFaction($faction->getFactionId(), $faction->getFactionName());

            if (!$pushChanges) {
                return self::ADD_ALLY_OK;
            } else if ($this->allyOperationLocked) {
                return self::ADD_ALLY_LOCKED;
            }

            $this->allyOperationLocked = true;

            Database::executeSelect(Database::GET_FACTIONS_ALLIES_COUNT, [
                'faction_id' => $this->factionId,
                'faction_ally' => $faction->getFactionId()
            ], yield, yield Await::REJECT);

            $rows = yield Await::ONCE;

            foreach ($rows as ['allies_count' => $allyCount, 'faction_id' => $factionId]) {
                if ($factionId === $this->getFactionId() && $allyCount >= $this->getMaxAlliesSize()) {
                    unset($this->allyFactions[$faction->getFactionId()]);

                    return self::ADD_ALLY_FULL;
                } else if ($allyCount >= $faction->getMaxAlliesSize()) {
                    unset($this->allyFactions[$faction->getFactionId()]);

                    return self::ADD_ALLY_PARENT_FULL;
                }
            }

            Database::executeInsert(Database::ADD_FACTION_ALLY, [
                'faction_id' => $this->factionId,
                'faction_ally' => $faction->getFactionId(),
            ], yield Await::RESOLVE_MULTI, yield Await::REJECT);

            [, $affectedRows] = yield Await::ONCE;

            Database::executeSelect(Database::GET_FACTIONS_ALLIES_COUNT, [
                'faction_id' => $this->factionId,
                'faction_ally' => $faction->getFactionId()
            ], yield, yield Await::REJECT);

            $rows = yield Await::ONCE;

            $result = self::ADD_ALLY_OK;
            foreach ($rows as ['allies_count' => $allyCount, 'faction_id' => $factionId]) {
                if ($factionId === $this->getFactionId() && $allyCount > $this->getMaxAlliesSize()) {
                    $result = self::ADD_ALLY_FULL;
                } else if ($allyCount > $faction->getMaxAlliesSize()) {
                    $result = self::ADD_ALLY_PARENT_FULL;
                }

                if ($result !== self::ADD_ALLY_OK) {
                    Database::executeGeneric(Database::REMOVE_FACTION_ALLY, [
                        'faction_id' => $this->factionId,
                        'faction_ally' => $faction->getFactionId(),
                    ], yield, yield Await::REJECT);

                    yield Await::ONCE;

                    unset($this->allyFactions[$faction->getFactionId()]);

                    return $result;
                }
            }

            $this->allyOperationLocked = false;

            if ($affectedRows > 0) {
                $factionAlly = $this->getPlugin()->getFactionManager()->getFaction($faction->getFactionId());

                // Check if the faction is loaded in this server, if it is, then we simply remove the allies object
                // in the server's memory. The object will be synced to all servers in previous case.
                if ($factionAlly !== null) {
                    $factionAlly->addAllies($this);
                    $factionAlly->addStrength(50);
                } else {
                    $faction->addStrength(50);
                }

                $this->addStrength(50);

                $this->getPlugin()->getEventEmitter()->broadcastEvent($this, EventEmitter::EVENT_CHANGE_ALLIES, [0, $faction->getFactionId(), $faction->getFactionName()]);

                return self::ADD_ALLY_OK;
            }

            return self::ADD_ALLY_EXISTS;
        }, function (int $status) use ($pushChanges, $onSuccess): void {
            if ($pushChanges && $status !== self::ADD_ALLY_LOCKED) {
                $this->allyOperationLocked = false;
            }

            if ($onSuccess !== null) {
                $onSuccess($status);
            }
        }, function (Throwable $error) use ($faction, $pushChanges, $onSuccess): void {
            if ($onSuccess !== null) {
                unset($this->allyFactions[$faction->getFactionId()]);

                $onSuccess(self::ADD_ALLY_ERROR);
            }

            if ($pushChanges) {
                $this->allyOperationLocked = false;
            }

            if ($error instanceof SqlError && str_contains($error->getMessage(), "Deadlock found when trying to get lock")) {
                return;
            }

            $this->getPlugin()->getLogger()->logException($error);
        });
    }

    /**
     * @param OfflineFaction|Faction $faction
     * @param bool $pushChanges
     * @param Closure|null $onSuccess
     *
     * @phpstan-param Closure(int) : void $onSuccess
     */
    public function removeAlly(OfflineFaction|Faction $faction, bool $pushChanges = false, ?Closure $onSuccess = null): void
    {
        Await::f2c(function () use ($faction, $pushChanges): Generator {
            if (!$pushChanges) {
                unset($this->allyFactions[$faction->getFactionId()]);

                return self::REMOVE_ALLY_OK;
            } else if ($this->allyOperationLocked) {
                return self::REMOVE_ALLY_LOCKED;
            }

            $this->allyOperationLocked = true;

            Database::executeChange(Database::REMOVE_FACTION_ALLY, [
                'faction_id' => $this->getFactionId(),
                'faction_ally' => $faction->getFactionId(),
            ], yield Await::RESOLVE_MULTI, yield Await::REJECT);

            [$affectedRows] = yield Await::ONCE;

            $this->allyOperationLocked = false;

            unset($this->allyFactions[$faction->getFactionId()]);

            if ($affectedRows > 0) {
                $factionAlly = $this->getPlugin()->getFactionManager()->getFaction($faction->getFactionId());

                // Check if the faction is loaded in this server, if it is, then we simply remove the allies object
                // in the server's memory. The object will be synced to all servers in previous case.
                if ($factionAlly !== null) {
                    $factionAlly->removeAlly($this);
                    $factionAlly->subtractFromStrength(50);
                } else {
                    $faction->subtractFromStrength(50);
                }

                $this->subtractFromStrength(50);

                $this->getPlugin()->getEventEmitter()->broadcastEvent($this, EventEmitter::EVENT_CHANGE_ALLIES, [1, $faction->getFactionId(), $faction->getFactionName()]);

                return self::REMOVE_ALLY_OK;
            }

            return self::REMOVE_ALLY_NOT_EXISTS;
        }, function (int $status) use ($pushChanges, $onSuccess): void {
            if ($pushChanges && $status !== self::REMOVE_ALLY_LOCKED) {
                $this->allyOperationLocked = false;
            }

            if ($onSuccess !== null) {
                $onSuccess($status);
            }
        }, function (Throwable $error) use ($pushChanges, $onSuccess): void {
            if ($onSuccess !== null) {
                $onSuccess(self::REMOVE_ALLY_ERROR);
            }

            if ($pushChanges) {
                $this->allyOperationLocked = false;
            }

            $this->getPlugin()->getLogger()->logException($error);
        });
    }

    /**
     * Instead of calling {@link Faction::removeAlly()} recursively, this method will perform a faster variant
     * of allies removal by decreasing their strength on a single query only. For data accuracy, the allies were
     * returned on {@code $onSuccess}.
     *
     * @param Closure $onSuccess
     * @phpstan-param Closure(int[]) : void $onSuccess
     */
    public function removeAllyDisband(Closure $onSuccess): void
    {
        Await::f2c(function () {
            Database::executeSelect(Database::GET_FACTIONS_ALLIES, [
                'faction_id' => $this->getFactionId(),
            ], yield, yield Await::REJECT);

            $rows = yield Await::ONCE;

            // Allies data (Remove them one piece at a time).
            $allies = [];
            foreach ($rows as ['faction_id' => $factionAllyId, 'allied_name' => $factionName]) {
                $allies[] = $factionAllyId;
            }

            // 0 is null in this context.
            $query = 'UPDATE factions SET strength = strength - 50 WHERE faction_id IN (' . implode(',', array_merge($allies, [0])) . ')';

            Database::executeChangeRaw($query, [], yield, yield Await::REJECT);
            $affectedRows = yield Await::ONCE;

            if (count($allies) !== $affectedRows) {
                GlobalLogger::get()->critical("Query strength decrease returned unexpected affected rows, expected " . count($allies) . ", got " . $affectedRows);
            }

            return $allies;
        }, $onSuccess, function (Throwable $error): void {
            $this->getPlugin()->getLogger()->logException($error);
        });
    }

    public function isFactionAlly(int|string $factionId): bool
    {
        return $this->getAllyInfo($factionId) !== null;
    }

    public function getAllyInfo(int|string $factionId): ?OfflineFaction
    {
        foreach ($this->getAllies() as $ally) {
            if (is_int($factionId)) {
                if ($ally->getFactionId() === $factionId) {
                    return $ally;
                }
            } else {
                if (strcasecmp($ally->getFactionName(), $factionId) === 0) {
                    return $ally;
                }
            }
        }

        return null;
    }

    /**
     * @return OfflineFaction[]
     */
    public function getAllies(): array
    {
        return $this->allyFactions;
    }

    // ---------------------------------------------- FACTION OPERATIONS -----------------------------------------------

    public function setLeader(Player|string $member, bool $pushUpdate = false, bool $updateTags = false): void
    {
        if ($member instanceof Player) {
            $member = $member->getName();
        }
        $currentLeader = $this->leader;

        if (($key = array_search($member, $this->officers)) !== false) {
            unset($this->officers[$key]);
        }

        $this->leader = $member;

        $this->officers[] = $currentLeader;

        if ($updateTags || $pushUpdate) {
            $playerManager = Factions::getInstance()->getPlayerManager();

            foreach ($this->getMembers() as $playerInGame) {
                if (($player = Server::getInstance()->getPlayerExact($playerInGame)) !== null) {
                    $playerManager->updateFactionScoreboard($player);
                }
            }

            if (($player = Server::getInstance()->getPlayerExact($member)) !== null) {
                GroupManager::updateNameTag($player);
            }

            if (($player = Server::getInstance()->getPlayerExact($currentLeader)) !== null) {
                GroupManager::updateNameTag($player);
            }
        }

        if ($pushUpdate) {
            Await::f2c(function () use ($member, $currentLeader): Generator {
                Database::executeInsert(Database::UPDATE_FACTION_LEADER, [
                    'faction_id' => $this->getFactionId(),
                    'old_leader' => $currentLeader,
                    'new_leader' => $member,
                ], yield, yield Await::REJECT);

                yield Await::ONCE;

                $this->getPlugin()->getEventEmitter()->broadcastEvent($this, EventEmitter::EVENT_CHANGE_LEADER, [$member]);
            }, catches: function (Throwable $error) {
                $this->getPlugin()->getLogger()->logException($error);
            });
        }
    }

    /**
     * @param Player|string $player
     * @param int $role
     * @param bool $update
     * @param bool $updateTags
     * @param callable|null $onSuccess
     */
    public function addMember(Player|string $player, int $role = self::MEMBER, bool $update = false, bool $updateTags = false, ?callable $onSuccess = null): void
    {
        Await::f2c(function () use ($player, $role, $update, $updateTags): Generator {
            if ($player instanceof Player) {
                $player = $player->getName();
            }

            // Check if the faction has reached its maximum members limit.
            if ($update) {
                if ($this->isOperationLocked) {
                    return self::ADD_MEMBER_LOCKED;
                }

                $this->isOperationLocked = true;

                Database::executeSelect(Database::GET_FACTION_COUNT, [
                    'faction_id' => $this->getFactionId(),
                ], yield, yield Await::REJECT);

                $result = yield Await::ONCE;

                if (isset($result[0]) && $result[0]['members'] >= $this->maxFactionSize) {
                    return self::ADD_MEMBER_FULL;
                }
            }

            // The player is already a member, disregard anyway.
            if (in_array($player, $this->members, true)) {
                return self::ADD_MEMBER_EXISTS;
            }

            // Update the database first before we're adding the player into this faction.
            if ($update) {
                Database::executeInsert(Database::ADD_FACTION_PLAYER, [
                    'faction_id' => $this->getFactionId(),
                    'player_name' => $player,
                    'faction_role' => $role,
                ], yield, yield Await::REJECT);

                yield Await::ONCE;

                Database::executeSelect(Database::GET_FACTION_COUNT, [
                    'faction_id' => $this->getFactionId(),
                ], yield, yield Await::REJECT);

                $result = yield Await::ONCE;

                // Verify if the members does not reach the maximum members, (They can actually invite more than 1 players
                // if the database was lagging at condition "add_member", whereas the first check will be useless).
                if (isset($result[0]) && $result[0]['members'] >= $this->maxFactionSize) {
                    Database::executeInsert(Database::REMOVE_FACTION_PLAYER, [
                        'faction_id' => $this->getFactionId(),
                        'player_name' => $player,
                    ], yield, yield Await::REJECT);

                    yield Await::ONCE;

                    $this->removeMember($player);

                    return self::ADD_MEMBER_FULL;
                }

                $this->getPlugin()->getEventEmitter()->broadcastEvent($this, EventEmitter::EVENT_CHANGE_ROLES, [$player, $role]);
            }

            $this->members[] = $player;
            if ($role === self::OFFICER) {
                $this->officers[] = $player;
            } else if ($role === self::LEADER) {
                $this->leader = $player;
            }

            if ($updateTags || $update) {
                $target = Server::getInstance()->getPlayerExact($player);
                if ($target instanceof Player) {
                    $playerData = $this->getPlugin()->getPlayerData();
                    $playerData->setValue($target, PlayerData::FACTION_ID, $this->factionId);

                    $this->getPlugin()->getPlayerManager()->updateFactionScoreboard($target);

                    GroupManager::updateNameTag($target);
                }
            }

            if ($update) {
                $this->addStrength(10);
            }

            return self::ADD_MEMBER_OK;
        }, function (int $status) use ($update, $onSuccess): void {
            if ($update && $status !== self::ADD_MEMBER_LOCKED) {
                $this->isOperationLocked = false;
            }

            if ($onSuccess !== null) {
                $onSuccess($status);
            }
        }, function (Throwable $error) use ($update, $onSuccess): void {
            if ($onSuccess !== null) {
                $onSuccess(self::ADD_MEMBER_ERROR);
            }

            if ($update) {
                $this->isOperationLocked = false;
            }

            $this->getPlugin()->getLogger()->logException($error);
        });
    }

    /**
     * @param Player|string $member
     * @param bool $update
     * @param bool $updateTags
     * @param bool $dbUpdate
     */
    public function removeMember(Player|string $member, bool $update = false, bool $updateTags = false, bool $dbUpdate = true): void
    {
        if ($member instanceof Player) {
            $member = $member->getName();
        }

        if (($key = array_search($member, $this->members)) === false) {
            return;
        }
        unset($this->members[$key]);

        if (($key = array_search($member, $this->officers)) !== false) {
            unset($this->officers[$key]);
        }

        if ($updateTags || $update) {
            $target = Server::getInstance()->getPlayerExact($member);

            if ($target instanceof Player) {
                $playerData = $this->getPlugin()->getPlayerData();
                $playerData->setValue($member, PlayerData::FACTION_ID, 0);

                $scoreboard = $this->getPlugin()->getEssentials()->getServerData()->getScoreBoard();
                $scoreboard->setLines([$target], [12 => '', 13 => '', 14 => '', 15 => ''], true);

                GroupManager::updateNameTag($target);
            }
        }

        if ($update) {
            Await::f2c(function () use ($member, $dbUpdate): Generator {
                if ($dbUpdate) {
                    Database::executeGeneric(Database::REMOVE_FACTION_PLAYER, [
                        'player_name' => $member,
                        'faction_id' => $this->factionId,
                    ], yield, yield Await::REJECT);

                    yield Await::ONCE;
                }

                $this->subtractFromStrength(10);

                $this->getPlugin()->getEventEmitter()->broadcastEvent($this, EventEmitter::EVENT_CHANGE_ROLES, [$member, self::REMOVED]);
            }, catches: Database::getFailClosure());
        }
    }

    public function setMemberRole(Player|string $member, int $role = self::MEMBER, bool $pushUpdate = false, bool $updateTags = false): void
    {
        if ($member instanceof Player) {
            $member = $member->getName();
        }

        if ($member === $this->getLeader() || $role === self::LEADER) {
            throw new RuntimeException('Cannot change a member to leader directly, use the right function to change the guild leadership.');
        } else if ($role === self::MEMBER) {
            if (($key = array_search($member, $this->officers)) === false) {
                return;
            }

            unset($this->officers[$key]);
        } else if ($role === self::OFFICER) {
            if (in_array($member, $this->officers) !== false) {
                return;
            }

            $this->officers[] = $member;
        } else if ($role === self::REMOVED) {
            $this->removeMember($member, $pushUpdate, $updateTags);

            return;
        } else {
            throw new RuntimeException('Unknown role given, please check again.');
        }

        if ($pushUpdate || $updateTags) {
            if (($player = Server::getInstance()->getPlayerExact($member)) !== null) {
                GroupManager::updateNameTag($player);
            }
        }

        if ($pushUpdate) {
            Await::f2c(function () use ($member, $role): Generator {
                $data = [
                    'player_name' => $member,
                    'faction_role' => $role,
                    'faction_id' => $this->getFactionId()
                ];

                Database::executeInsert(Database::SET_FACTION_ROLE, $data, yield, yield Await::REJECT);

                yield Await::ONCE;

                $this->getPlugin()->getEventEmitter()->broadcastEvent($this, EventEmitter::EVENT_CHANGE_ROLES, [$member, $role]);
            }, catches: function (Throwable $error) {
                $this->getPlugin()->getLogger()->logException($error);
            });
        }
    }

    public function updatePlayerName(string $playerName, string $oldName, bool $update): void
    {
        if (($key = array_search($oldName, $this->members)) === false) {
            return;
        }

        $this->members[$key] = $playerName;
        if (($key = array_search($oldName, $this->officers)) !== false) {
            $this->officers[$key] = $playerName;
        } else if ($this->leader === $oldName) {
            $this->leader = $playerName;
        }

        if ($update) {
            $this->getPlugin()->getEventEmitter()->broadcastEvent($this, EventEmitter::EVENT_UPDATE_NAME, [$playerName, $oldName]);
        }
    }

    // --------------------------------------------- SETTINGS OPERATIONS ----------------------------------------------

    public function getAutoKickDays(): int {
        return $this->autoKickDays;
    }

    public function setAutoKickDays(int $autoKickDays, bool $update = true): void {
        $this->autoKickDays = $autoKickDays;

        if ($update) {
            Await::f2c(function () use ($autoKickDays) {
                Database::executeChange("factions.update_kick_days", [
                    'kick_days' => $autoKickDays,
                    'faction_id' => $this->factionId,
                ], yield, yield Await::REJECT);

                yield Await::ONCE;

                $this->getPlugin()->getEventEmitter()->broadcastEvent($this, EventEmitter::EVENT_UPDATE_AUTO_KICK_DAYS, [$autoKickDays]);
            });
        }
    }

    public function getAutoKickDeaths(): int {
        return $this->autoKickDeaths;
    }

    public function setAutoKickDeaths(int $autoKickDeaths, bool $update = true): void {
        $this->autoKickDeaths = $autoKickDeaths;

        if ($update) {
            Await::f2c(function () use ($autoKickDeaths) {
                Database::executeChange("factions.update_kick_deaths", [
                    'kick_deaths' => $autoKickDeaths,
                    'faction_id' => $this->factionId,
                ], yield, yield Await::REJECT);

                yield Await::ONCE;

                $this->getPlugin()->getEventEmitter()->broadcastEvent($this, EventEmitter::EVENT_UPDATE_AUTO_KICK_DEATHS, [$autoKickDeaths]);
            });
        }
    }

    // -------------------------------------------- PERMISSIONS OPERATIONS ---------------------------------------------

    /**
     * Check if the given player has the permission to do the id given. If the id were out of bounds,
     * then the result of this method will always be false.
     *
     * @param Player|string $player The player itself
     * @param int $permissionId
     * @return bool
     */
    public function hasPermission(Player|string $player, int $permissionId): bool
    {
        if ($player instanceof Player) {
            $player = $player->getName();
        }

        if ($permissionId < self::ALLOW_STRENGTH_MODIFIER || $permissionId > self::ALLOW_ECONOMY_WITHDRAWAL) {
            return false;
        }

        if ($this->getLeader() === $player) {
            return Utils::hasFlag(self::DEFAULT_OFFICER_PERMISSION, $permissionId);
        }

        if (isset($this->permissions[$player])) {
            return Utils::hasFlag($this->permissions[$player], $permissionId);
        }

        if ($this->getFactionRole($player) === self::OFFICER) {
            return Utils::hasFlag(self::DEFAULT_OFFICER_PERMISSION, $permissionId);
        }

        return Utils::hasFlag(self::DEFAULT_PERMISSION, $permissionId);
    }

    /**
     * Update player permission with the new flags. This will completely replace the currently existing flags
     * by the player.
     *
     * @param Player|null $player The player that executes this method.
     * @param string $target The player name.
     * @param int $newFlags The new flags for the player.
     * @param bool $pushUpdates
     */
    public function updatePermission(?Player $player, string $target, int $newFlags, bool $pushUpdates = true): void
    {
        Await::f2c(function () use ($player, $target, $newFlags, $pushUpdates) {
            if (isset($this->permissions[$target])) {
                $oldFlags = $this->permissions[$target];
            } else if ($this->getFactionRole($target) === self::OFFICER) {
                $oldFlags = self::DEFAULT_OFFICER_PERMISSION;
            } else {
                $oldFlags = self::DEFAULT_PERMISSION;
            }

            $permissions = $this->permissions;
            $permissions[$target] = $newFlags;

            if (!$pushUpdates) {
                $this->permissions = $permissions;

                $this->broadcastPermissionChange($player, $target, $oldFlags, $newFlags);
                return;
            }

            Database::executeChange("factions.update_permissions", [
                'permissions' => json_encode($permissions),
                'faction_id' => $this->factionId,
            ], yield, yield Await::REJECT);

            $affectedRows = yield Await::ONCE;
            if ($affectedRows > 0) {
                $this->permissions = $permissions;

                $this->broadcastPermissionChange($player, $target, $oldFlags, $newFlags);

                $this->getPlugin()->getEventEmitter()->broadcastEvent($this, EventEmitter::EVENT_UPDATE_PERMISSION, [$target, $newFlags]);
            } else {
                $player?->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "A database error has occurred while executing this command.");
            }
        });
    }

    private function broadcastPermissionChange(?Player $player, string $target, int $oldFlags, int $newFlags): void
    {
        $target = $this->getPlugin()->getServer()->getPlayerExact($target);
        for ($flag = 0; $flag < self::PERMISSIONS_SIZE; $flag++) {
            $value = Utils::hasFlag($newFlags, $flag);
            if (Utils::hasFlag($oldFlags, $flag) === $value) {
                continue;
            }

            switch ($flag) {
                case self::ALLOW_STRENGTH_MODIFIER:
                    $this->sendPermissionChanged($player, $target, $value, "drain and gain faction's strength.");
                    break;
                case self::ALLOW_BASE_BUILD:
                    $this->sendPermissionChanged($player, $target, $value, "build inside factions claims.");
                    break;
                case self::ALLOW_BASE_INTERACTION:
                    $this->sendPermissionChanged($player, $target, $value, "interact inside factions claims.");
                    break;
                case self::ALLOW_TELEPORT_BASE:
                    $this->sendPermissionChanged($player, $target, $value, "teleport into faction home.");
                    break;
                case self::ALLOW_ECONOMY_WITHDRAWAL:
                    $this->sendPermissionChanged($player, $target, $value, "withdraw balance from factions balance.");
                    break;
            }
        }
    }

    private function sendPermissionChanged(?Player $player, ?Player $target, bool $flag, string $message): void
    {
        $prefix = MMOPlugin::getPrefix() . ($flag ? TextFormat::GREEN : TextFormat::RED);
        $msg = ($flag ? "is now able to " : "can no longer ") . $message;
        if ($player instanceof Player) {
            $player->sendMessage($prefix . "The player " . $msg);
        }
        if ($target instanceof Player) {
            $target->sendMessage($prefix . "You " . $msg);
        }
    }

    // ---------------------------------------------- ECONOMY OPERATIONS -----------------------------------------------

    public function setBalance(int $balance): void
    {
        $this->balance = $balance;
    }

    public function getBalance(): int
    {
        return $this->balance;
    }

    public function withdrawBalance(Player $player, int $balance): void
    {
        Await::f2c(function () use ($player, $balance): Generator {
            Database::executeSelect(Database::FACTION_ECONOMY_TRANSACTION, [
                'player' => $player->getName(),
                'amount' => $balance,
                'faction_id' => $this->factionId,
                'transaction_mode' => EconomyManager::MODE_DECREASE
            ], yield, yield Await::REJECT);

            $rows = yield Await::ONCE;

            if (count($rows) > 0) {
                ['faction_balance' => $factionBalance, 'player_balance' => $playerBalance, 'result' => $result] = $rows[0];

                if ($factionBalance === null || $playerBalance === null) {
                    GlobalLogger::get()->warning("One of the given balance is null - database error?");
                    return;
                }

                if ($player->isConnected()) {
                    $this->getPlugin()->getPlayerData()->setValue($player, MMOPlayerData::PLAYER_MONEY, $playerBalance, load: true);

                    if ($result === 0) {
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Your faction don't have enough money.");
                    } else if ($result === 1) {
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't have over " . number_format(EconomyManager::MAX_MONEY_AMOUNT) . ' coins in total.');
                    } else {
                        $player->sendMessage(MMOPlugin::getPrefix() . "You withdrew " . number_format($balance) . " coins from the faction treasury.");
                    }
                }

                if ($result === 2) {
                    $this->getPlugin()->getEventEmitter()->broadcastEvent($this, EventEmitter::EVENT_UPDATE_BALANCE, [$this->balance = $factionBalance]);
                }
            }
        }, catches: function (Throwable $error) use ($player) {
            $this->getPlugin()->getLogger()->logException($error);

            if ($player->isConnected()) {
                $player->sendMessage(Translator::getTranslationPlayer($player, 'db.error'));
            }
        });
    }

    public function depositBalance(Player $player, int $balance): void
    {
        Await::f2c(function () use ($player, $balance): Generator {
            Database::executeSelect(Database::FACTION_ECONOMY_TRANSACTION, [
                'player' => $player->getName(),
                'amount' => $balance,
                'faction_id' => $this->factionId,
                'transaction_mode' => EconomyManager::MODE_INCREASE
            ], yield, yield Await::REJECT);

            $rows = yield Await::ONCE;

            if (count($rows) > 0) {
                ['faction_balance' => $factionBalance, 'player_balance' => $playerBalance, 'result' => $result] = $rows[0];

                if ($factionBalance === null || $playerBalance === null) {
                    GlobalLogger::get()->warning("One of the given balance is null - database error?");
                    return;
                }

                if ($player->isConnected()) {
                    $this->getPlugin()->getPlayerData()->setValue($player, MMOPlayerData::PLAYER_MONEY, $playerBalance, load: true);

                    if ($result === 0) {
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You don't have enough coins in your balance!");
                    } else if ($result === 1) {
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Your faction can't have over " . number_format(EconomyManager::MAX_MONEY_AMOUNT) . ' balance in total.');
                    } else {
                        $player->sendMessage(MMOPlugin::getPrefix() . "You deposited " . number_format($balance) . " coins into the faction treasury.");
                    }
                }

                if ($result === 2) {
                    $this->getPlugin()->getEventEmitter()->broadcastEvent($this, EventEmitter::EVENT_UPDATE_BALANCE, [$this->balance = $factionBalance]);
                }
            }
        }, catches: function (Throwable $error) use ($player) {
            $this->getPlugin()->getLogger()->logException($error);

            if ($player->isConnected()) {
                $player->sendMessage(Translator::getTranslationPlayer($player, 'db.error'));
            }
        });
    }

    // ---------------------------------------------- FACTION OPERATIONS -----------------------------------------------

    public function setFactionName(string $factionName, bool $update = false): void
    {
        $this->factionName = $factionName;

        foreach ($this->getMembers() as $member) {
            if (($player = Server::getInstance()->getPlayerExact($member)) !== null) {
                GroupManager::updateNameTag($player);
            }
        }

        if ($update) {
            $this->getPlugin()->getEventEmitter()->broadcastEvent($this, EventEmitter::EVENT_CHANGE_FACTION_NAME, [$factionName]);
        }
    }

    public function updateFactionName(Player $leader, string $factionName): void
    {
        Await::f2c(function () use ($leader, $factionName): Generator {
            $factionName = TextFormat::clean($factionName);
            if (strlen($factionName) > 16) {
                $leader->sendMessage(TextFormat::RED . "Your faction name can't be more than 16 characters long.");
                return;
            }

            Factions::getInstance()->getFactionManager()->checkFactionName($leader, $factionName, yield);
            yield Await::ONCE;

            Database::executeChange(Database::UPDATE_FACTION_NAME, [
                'faction_name' => $factionName,
                'faction_id' => $this->factionId
            ], yield, yield Await::REJECT);

            $affectedRows = yield Await::ONCE;
            if ($affectedRows > 0) {
                GlobalLogger::get()->info("Faction renamed from $this->factionName into $factionName by {$leader->getName()}");

                $this->setFactionName($factionName, true);

                $leader->sendMessage(MMOPlugin::getPrefix() . "Successfully renamed your faction into: " . $factionName);
            } else {
                $leader->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . $factionName . " is an existing faction.");
            }
        });
    }

    public function setHomeCoordinates(Player|FactionLocation $sender): void
    {
        if ($sender instanceof FactionLocation) {
            $this->factionHome = $sender;

            return;
        }

        Await::f2c(function () use ($sender): Generator {
            $pos = $sender->getPosition();

            $serverRegion = $this->getPlugin()->getEssentials()->getServerManager()->getServerRegion();
            $data = [$pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ(), $pos->getWorld()->getFolderName(), $serverRegion];

            Database::executeChange(Database::SET_FACTION_HOME, [
                'home_cords' => json_encode($data),
                'faction_id' => $this->getFactionId()
            ], yield, yield Await::REJECT);

            yield Await::ONCE;

            $this->factionHome = new FactionLocation($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ(), $pos->getWorld()->getFolderName(), $serverRegion);
            $this->factionFlyArea = new AxisAlignedBB(
                min($this->factionHome->getFloorX() + 30, $this->factionHome->getFloorX() - 30),
                min($this->factionHome->getFloorY() + 30, $this->factionHome->getFloorY() - 30),
                min($this->factionHome->getFloorZ() + 30, $this->factionHome->getFloorZ() - 30),
                max($this->factionHome->getFloorX() + 30, $this->factionHome->getFloorX() - 30),
                max($this->factionHome->getFloorY() + 30, $this->factionHome->getFloorY() - 30),
                max($this->factionHome->getFloorZ() + 30, $this->factionHome->getFloorZ() - 30)
            );

            $sender->sendMessage(MMOPlugin::getPrefix() . "Successfully changed your factions home location.");

            $this->getPlugin()->getEventEmitter()->broadcastEvent($this, EventEmitter::EVENT_CHANGE_HOME, $data);
        }, catches: Database::getFailClosure());
    }

    public function unsetHomeCoordinates(?Player $sender = null): void
    {
        Await::f2c(function () use ($sender): Generator {
            Database::executeChange(Database::UNSET_FACTION_HOME, [
                'faction_id' => $this->factionId
            ], yield, yield Await::REJECT);

            yield Await::ONCE;

            $this->factionHome = null;
            $this->factionFlyArea = null;

            $sender?->sendMessage(MMOPlugin::getPrefix() . "Successfully deleted your factions home location.");

            $this->getPlugin()->getEventEmitter()->broadcastEvent($this, EventEmitter::EVENT_DELETE_HOME);
        }, catches: Database::getFailClosure());
    }

    public function teleportToHome(Player $player): void
    {
        $scheduler = $this->getPlugin()->getScheduler();
        if (($homeLocation = $this->getHome()) === null) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Your faction leader hasn\'t set a faction home.');
            return;
        }

        if (isset(TeleportTask::$teleportList[$player->getName()])) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Please wait for you to be teleported first.');
            return;
        }

        if (!$this->hasPermission($player, self::ALLOW_TELEPORT_BASE)) {
            $player->sendMessage(Factions::getPrefix() . TextFormat::RED . 'You do not have permission to teleport to your faction\'s base.');

            return;
        }

        if (!$homeLocation->isValidServer()) {
            if (($transportServer = Factions::getInstance()->getRegionManager()->getFarlandsByRegion($homeLocation->getServerRegion())) === null) {
                $player->sendMessage(Factions::getPrefix() . TextFormat::RED . 'The server you are teleporting into is currently offline.');
            } else {
                $scheduler->scheduleRepeatingTask(new TransferServerLogicTask($player, $homeLocation, $transportServer), 20);
            }

            return;
        }

        if ($homeLocation->getPosition()->isValid() && $homeLocation->getPosition()->getWorld()->getFolderName() === "wild") {
            $scheduler->scheduleRepeatingTask(new TeleportTask($player, $homeLocation->getPosition()), 20);
        } else {
            $player->sendMessage(Factions::getPrefix() . TextFormat::RED . 'Something went wrong while teleporting to your faction home.');
        }
    }

    public function getHome(): ?FactionLocation
    {
        return $this->factionHome;
    }

    public function isAreaHome(Position $position): bool
    {
        return $this->factionFlyArea !== null && $this->factionFlyArea->isVectorInside($position->asVector3());
    }

    public function isDatabaseLocked(): bool
    {
        return $this->allyOperationLocked || $this->isOperationLocked;
    }

    public function getHomeClaim(): ?Claim
    {
        if ($this->getStrength() < 500 || $this->factionHome === null || !$this->factionHome->isValidServer()) {
            return null;
        }

        return $this->getPlugin()->getClaimManager()->getClaimInPosition($this->factionHome->getPosition());
    }

    /**
     * In order for the strength to sync, we autoincrement this value from
     * the database instead of doing so in server. Then we collect the result of the
     * increased strength.
     *
     * @param int $strength
     */
    public function addStrength(int $strength): void
    {
        Await::f2c(function () use ($strength): Generator {
            Database::executeChange(Database::STRENGTH_INCREASE, [
                'strength' => $strength,
                'faction_id' => $this->factionId,
            ], yield, yield Await::REJECT);

            yield Await::ONCE;

            Database::executeSelect(Database::STRENGTH_GET, [
                'faction_id' => $this->factionId
            ], yield, yield Await::REJECT);

            $select = yield Await::ONCE;

            if (!isset($select[0])) {
                return;
            }

            if (!Factions::isBadlands()) {
                foreach ($this->getPlugin()->getClaimManager()->getClaimsByFaction($this) as $claims) {
                    $claims->setStrength($select[0]['strength']);
                }
            }

            $this->setStrength($select[0]['strength']);
            $this->getPlugin()->getEventEmitter()->broadcastEvent($this, EventEmitter::EVENT_CHANGE_STRENGTH, [$this->strength]);
        }, catches: Database::getFailClosure());
    }

    public function setStrength(int $strength): void
    {
        $this->strength = $strength;

        $this->getPlugin()->getPlayerManager()->updateStrengthScoreboard($this);
    }

    /**
     * @param int $strength
     */
    public function subtractFromStrength(int $strength): void
    {
        Await::f2c(function () use ($strength): Generator {
            Database::executeChange(Database::STRENGTH_DECREASE, [
                'strength' => $strength,
                'faction_id' => $this->factionId,
            ], yield, yield Await::REJECT);

            yield Await::ONCE;

            Database::executeSelect(Database::STRENGTH_GET, [
                'faction_id' => $this->factionId
            ], yield, yield Await::REJECT);

            $select = yield Await::ONCE;

            if (!isset($select[0])) {
                return;
            }

            $this->setStrength($select[0]['strength']);
            $this->getPlugin()->getEventEmitter()->broadcastEvent($this, EventEmitter::EVENT_CHANGE_STRENGTH, [$this->strength]);
        }, catches: Database::getFailClosure());
    }

    /**
     * @return int
     */
    public function getStrength(): int
    {
        return $this->strength;
    }

    public function getMaxFactionSize(): int
    {
        return $this->maxFactionSize;
    }

    public function getMaxAlliesSize(): int
    {
        return $this->maxAlliesSize;
    }
}
