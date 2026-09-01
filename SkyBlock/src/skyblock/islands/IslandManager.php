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
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder
 */
/** @noinspection PhpDocMissingThrowsInspection */

declare(strict_types=1);

namespace skyblock\islands;

use Closure;
use Generator;
use GlobalLogger;
use libMMO\MMOPlugin;
use NetherGames\NGEssentials\ServerManager;
use pocketmine\player\Player;
use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;
use skyblock\islands\feature\elevator\Elevator;
use skyblock\islands\feature\IslandLevelManager;
use skyblock\islands\listener\IslandListener;
use skyblock\islands\storage\IslandLoadState;
use skyblock\islands\storage\IslandStorage;
use skyblock\player\PlayerData;
use skyblock\SkyBlock;
use skyblock\utils\BaseClass;
use skyblock\utils\Database;
use SOFe\AwaitGenerator\Await;
use Throwable;

/**
 * Another new implementation of island loading system. Uses await-generator to fulfill async
 * tasks, perhaps this will fix the memory leak that is floating around with the old island
 * management implementation.
 *
 * @package skyblock\islands
 */
class IslandManager extends BaseClass
{
    public const STATUS_NOT_CREATED = 0;
    public const STATUS_CREATED_NOT_LOADED = 1;
    public const STATUS_CREATED_AND_LOCKED = 2;

    public const ISLAND_LOADED = 0;
    public const ISLAND_PRE_LOADED = 1;
    public const ISLAND_ALREADY_LOADED = 2;
    public const ISLAND_NOT_EXISTS = 3;
    public const ISLAND_LOAD_ERROR = 4;
    public const ISLAND_WORLD_ERROR = 5;
    public const ISLAND_WORLD_LOST = 6;
    public const ISLAND_LOADING_DISABLED = 7;

    /** @var IslandManager|null */
    public static ?IslandManager $islandManager = null;

    /** @var IslandStorage */
    private IslandStorage $islandStorage;
    /** @var IslandLevelManager */
    private IslandLevelManager $islandLevelManager;
    /** @var Island[] */
    private array $islands = []; // Format [ Player Name -> Island ]
    /** @var int */
    private int $pendingUnload = 0;
    /**
     * @var PromiseResolver|null
     * @phpstan-var PromiseResolver<null>
     */
    private ?PromiseResolver $resolver = null;

    public function __construct(SkyBlock $plugin)
    {
        parent::__construct($plugin);

        $this->islandStorage = new IslandStorage($plugin);
        $this->islandLevelManager = new IslandLevelManager($plugin);

        $plugin->getServer()->getPluginManager()->registerEvents($this->islandLevelManager, $plugin);
        $plugin->getServer()->getPluginManager()->registerEvents(new IslandListener($this), $plugin);
        $plugin->getServer()->getPluginManager()->registerEvents(new Elevator(), $plugin);

        $plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (): void {
            foreach ($this->getPlugin()->getServer()->getWorldManager()->getWorlds() as $world) {
                if (!str_contains($world->getFolderName(), IslandStorage::ISLAND_PREFIX)) continue;

                // The condition null can sometime happens when asynchronous operation
                // is not finished executing.
                $island = $this->getIslandByWorld($world);
                if ($island !== null && count($world->getPlayers()) === 0 && $island->isUnloadUnlocked()) {
                    $this->saveIsland($island, true);
                }
            }
        }), 20);

        self::$islandManager = $this;
    }

    public static function getIslandManager(): IslandManager
    {
        return self::$islandManager;
    }

    /**
     * @return Promise Resolved to a boolean indicating that all islands has been unloaded.
     * @phpstan-return Promise<null>
     */
    public function getUnloadingResolver(): Promise
    {
        $resolver = $this->resolver = new PromiseResolver();
        return $resolver->getPromise();
    }

    /**
     * @return Island[]
     */
    public function getLoadedIslands(): array
    {
        return $this->islands;
    }

    /**
     * Attempt to get the island loaded in this server by the player
     * xuid itself. This will return null if it is not loaded.
     * <p>
     * The condition of this function will never return an unloaded or null
     * world. This island is already loaded in the server and the world must never
     * be unloaded.
     *
     * @param string $playerName
     * @return Island|null
     */
    public function getIslandByOwner(string $playerName): ?Island
    {
        return $this->getLoadedIslands()[$playerName] ?? null;
    }

    /**
     * Attempt to get the island loaded by using the world itself.
     *
     * @param World $world
     * @return Island|null
     */
    public function getIslandByWorld(World $world): ?Island
    {
        foreach ($this->getLoadedIslands() as $island) {
            if (($islandWorld = $island->getWorld()) !== null && $islandWorld->getId() === $world->getId()) {
                return $island;
            }
        }

        return null;
    }

    /**
     * This function will return all friends that has created an island in this network.
     *
     * @param Player $player The player themself
     * @param Closure $callable a callback with signature of <code>function(?{@link Island} $island) : void {}</code>
     */
    public function getFriendsWithIsland(Player $player, Closure $callable): void
    {
        $socialManager = $this->getPlugin()->getEssentials()->getPlayerManager()->getSocialManager();

        if (count($friends = array_values($socialManager->getFriendsManager()->getFriends($player))) === 0) {
            $callable([]);
        } else {
            Await::f2c(function () use ($friends, $callable): Generator {
                $arguments = [];
                $questionMarks = [];

                foreach ($friends as $friend) {
                    $questionMarks[] = '?';
                    $arguments[] = $friend;
                }

                Database::executeSelectRaw('SELECT owner FROM instance WHERE owner IN (' . implode(',', $questionMarks) . ')', $arguments, yield);
                $rows = yield Await::ONCE;

                $friendsWithIsland = [];
                foreach ($rows as $row) {
                    $friendsWithIsland[] = $row['owner'];
                }

                sort($friendsWithIsland);

                $callable($friendsWithIsland);
            });
        }
    }

    /**
     * This function will return the island data for the given xuid data. Unlike
     * {@see IslandManager::loadIsland()}, this method will not try load the player island.
     *
     * @param string $owner The player name itself.
     * @param Closure $onComplete a callback to retrieve island data <code>function(?{@link Island} $island) : void{}</code>
     */
    public function loadIslandData(string $owner, Closure $onComplete): void
    {
        Await::f2c(function () use ($owner, $onComplete): Generator {
            Database::executeSelect(Database::GET_ISLAND, ['owner' => $owner], yield);
            $rows = yield Await::ONCE;

            if (count($rows) > 0) {
                $row = $rows[0];

                retry:
                try {
                    $data = json_decode($row['package'], true, 512, JSON_THROW_ON_ERROR);
                } catch (Throwable) {
                    if (str_contains($row['package'], 'NAN')) {
                        GlobalLogger::get()->warning("Found NAN object in $owner island package, attempting to correct the package.");

                        $row['package'] = str_replace('NAN', '0', $row['package']);

                        goto retry;
                    } else {
                        $onComplete(null);
                    }

                    return;
                }

                $onComplete(new Island($owner, $row['xuid'], $data, (bool)$row['public']));
            } else {
                $onComplete(null);
            }
        });
    }

    /**
     * Attempt to load the player island. This method will return a full
     * island data, meaning that, it will allocate the island for this server and
     * load the world immediately. If the island is loaded in other server, it will fails.
     * <p>
     * In case the island has already been loaded, it will be returned.
     *
     * @param string $owner The player name
     * @param Closure $callable a callback to retrieve island data <code>function(int $error_code, ?{@link Island} $island) : void{}</code>
     */
    public function loadIsland(string $owner, Closure $callable): void
    {
        if (ServerManager::$draining) {
            self::log("Island for $owner is trying to load an island while the server is draining.");

            $callable(self::ISLAND_LOADING_DISABLED, null);
            return;
        }

        Await::f2c(function () use ($owner, $callable): Generator {
            $island = $this->getIslandByOwner($owner);
            if ($island !== null) {
                self::log("Island for $owner is already loaded in this server.");

                $callable(self::ISLAND_PRE_LOADED, $island);
            } else {
                $start = microtime(true);
                $memStart = memory_get_usage();

                $this->getIslandLocation($owner, yield Await::RESOLVE_MULTI);
                /**
                 * @var int $status
                 */
                [$status] = yield Await::ONCE;

                if ($status === self::STATUS_NOT_CREATED) {
                    $callable(self::ISLAND_NOT_EXISTS, null);
                    return true;
                }

                // Claim the island with a compare-and-swap, then re-read to confirm: another server can
                // win the race between the update and the check, so the lock is only held once the
                // stored location actually matches this server.
                $serverId = $this->getPlugin()->getEssentials()->getServerManager()->getUniqueId();
                while (true) {
                    Database::executeChangeRaw("UPDATE instance SET location = IF(location IS NULL, ?, location) WHERE owner = ?", [
                        $this->getPlugin()->getEssentials()->getServerManager()->getUniqueId(),
                        $owner,
                    ], yield, yield Await::REJECT);

                    $affectedRows = yield Await::ONCE;
                    if ($affectedRows === 0) {
                        self::log("Unable to set island location for $owner, is the island already been loaded?");
                        $callable(self::ISLAND_ALREADY_LOADED, null);

                        return true;
                    }

                    Database::executeSelect(Database::GET_ISLAND_LOCATION, ['owner' => $owner], yield);
                    $rows = yield Await::ONCE;

                    $location = $rows[0]['location'] ?? "";
                    if ($location === $serverId) {
                        self::log("Island location for $owner is locked.");

                        break; // We have achieved lock.
                    } else if (!empty($location)) {
                        self::log("Unable to set island location for $owner, is the island already been loaded? [Lock System]");
                        $callable(self::ISLAND_ALREADY_LOADED, null);

                        return true;
                    }
                }

                $this->loadIslandData($owner, yield);

                /** @var Island|null $island */
                $island = yield Await::ONCE;

                if ($island === null) {
                    $callable(self::ISLAND_NOT_EXISTS, null);

                    self::log("Island for $owner is missing??? The world exists but the island data could not be found.");
                    return false;
                }

                $this->getIslandStorage()->loadIsland($island->getOwnerXuid(), yield);

                /** @var IslandLoadState|null $state */
                $state = yield Await::ONCE;

                if ($state === null) {
                    self::log("Island for $owner is lost, unable to load their island from world location.");

                    $callable(self::ISLAND_WORLD_LOST, null);
                    return false;
                }

                if ($state->getCondition() === IslandLoadState::ERROR) {
                    self::log("Unable to load world for $owner, there was an error occurred");

                    $callable(self::ISLAND_LOAD_ERROR, null);
                    return false;
                }

                if ($state->getCondition() !== IslandLoadState::LOADED) {
                    self::log("Unable to load world for $owner, the world is corrupted?");

                    $callable(self::ISLAND_WORLD_ERROR, null);
                    return false;
                }

                $island->setWorld($state->getWorld());
                $island->setExtraData($state->getExtraData());
                $this->islands[$owner] = $island;

                $end = microtime(true);
                $memEnd = memory_get_usage();

                $callable(self::ISLAND_LOADED, $island);

                self::log("Island for $owner is loaded into server, took " . round(($end - $start) * 1000, 2) . "ms to execute, memory footprint (asynchronous) " . round(($memEnd - $memStart) / 1048576, 2) . "MB");
            }

            return true;
        }, function (bool $result) use ($owner): void {
            if ($result) {
                return;
            }

            Await::f2c(function () use ($owner) {
                Database::executeChangeRaw("UPDATE instance SET location = IF(location = ?, NULL, location) WHERE owner = ?", [
                    $this->getPlugin()->getEssentials()->getServerManager()->getUniqueId(),
                    $owner,
                ], yield, yield Await::REJECT);

                yield Await::ONCE;

                self::log("Island for player $owner is unlocked due to an unexpected behaviour.");
            }, catches: function (Throwable $error): void {
                $this->getPlugin()->getLogger()->logException($error);
            });
        }, function (Throwable $error) use ($owner, $callable): void {
            $callable(self::ISLAND_LOAD_ERROR, null);

            $this->getPlugin()->getLogger()->logException($error);

            Await::f2c(function () use ($owner) {
                Database::executeChangeRaw("UPDATE instance SET location = IF(location = ?, NULL, location) WHERE owner = ?", [
                    $this->getPlugin()->getEssentials()->getServerManager()->getUniqueId(),
                    $owner,
                ], yield, yield Await::REJECT);

                yield Await::ONCE;

                self::log("Island for player $owner is unlocked due to an unexpected behaviour.");
            }, catches: function (Throwable $error): void {
                $this->getPlugin()->getLogger()->logException($error);
            });
        });
    }

    /**
     * Attempt to retrieve the island location from the database, this method
     * will return the server of where the island is located at.
     *
     * <pre>
     * The following id will be returned for the given callback:
     *  1  ->  Island is non-existent.
     *  2  ->  Island is not loaded.
     *  3  ->  Island is loaded, the server id is loaded in the next variable.
     * </pre>
     *
     * @param string $owner
     * @param callable $callable a callback to retrieve island location <code>function(int $status, ?string $serverUniqueId) : void{}</code>
     */
    public function getIslandLocation(string $owner, callable $callable): void
    {
        Await::f2c(function () use ($owner, $callable): Generator {
            if ($this->getIslandByOwner($owner) !== null) {
                self::log('Location for island owner ' . $owner . ' is cached in this server.');

                $callable(self::STATUS_CREATED_AND_LOCKED, $this->getPlugin()->getEssentials()->getServerManager()->getUniqueId());
            } else {
                Database::executeSelect(Database::GET_ISLAND_LOCATION, ['owner' => $owner], yield);
                $rows = yield Await::ONCE;

                if (count($rows) > 0) {
                    self::log('Location for island owner ' . $owner . ' is cached in server ' . $rows[0]['location'] . '.');

                    $location = $rows[0]['location'];
                    $callable($location === null ? self::STATUS_CREATED_NOT_LOADED : self::STATUS_CREATED_AND_LOCKED, $location);
                } else {
                    self::log('Island does not exist for ' . $owner . ' in getIslandLocation');

                    $callable(self::STATUS_NOT_CREATED, null);
                }
            }
        });
    }

    /**
     * Attempt to save an island of the given allocated island data.
     * It will as well saves the world into PlayerIslands directory.
     *
     * @param Island $island
     * @param bool $unload
     */
    public function saveIsland(Island $island, bool $unload = false): void
    {
        if ($unload) {
            $island->setUnloadLock(false);
            $island->setLocked(true);
        }

        $this->pendingUnload++;
        Await::f2c(function () use ($island, $unload): Generator {
            $start = microtime(true);
            $memStart = memory_get_usage();

            self::log("Saving island " . $island->getOwner() . ($unload ? " and attempting to unload the world." : " while the world is loaded."));

            Database::executeChange(Database::SET_ISLAND, [
                'xuid' => $island->getOwnerXuid(),
                'package' => json_encode($island->getData(), JSON_THROW_ON_ERROR)
            ], yield, yield Await::REJECT);
            yield Await::ONCE;

            if (($world = $island->getWorld()) === null) {
                self::log("{$island->getOwner()} cannot be unloaded, it was never been loaded before!");

                return;
            }

            if ($unload) {
                self::log("Attempting to unload {$island->getOwner()} island.");

                unset($this->islands[$island->getOwner()]);

                $island->setWorld(null);

                $this->getIslandStorage()->unloadIsland($world, yield);
                $result = yield Await::ONCE;

                if (!$result) {
                    self::log("Island unload for player {$island->getOwner()} is unsuccessful.");

                    return;
                }

                Database::executeChangeRaw("UPDATE instance SET location = IF(location = ?, NULL, location) WHERE owner = ?", [
                    $this->getPlugin()->getEssentials()->getServerManager()->getUniqueId(),
                    $island->getOwner(),
                ], yield, yield Await::REJECT);
                yield Await::ONCE;

                self::log("Island unload for player {$island->getOwner()} is successful.");
            } else {
                $this->getIslandStorage()->saveIsland($island, yield);
                yield Await::ONCE;

                $island->setLocked(false);
            }

            $end = microtime(true);
            $memEnd = memory_get_usage();

            self::log("Save island {$island->getOwner()} completed! " . round(($end - $start) * 1000, 2) . "ms to execute, memory footprint (asynchronous) " . round(($memEnd - $memStart) / 1048576, 2) . "MB");
        }, function () {
            $this->pendingUnload--;

            if ($this->pendingUnload <= 0 && $this->resolver !== null) {
                self::log("There are no pending upload waiting to resolve, firing promise resolver.");

                $this->resolver->resolve(null);
            }
        });
    }

    public function createIsland(Player $player, int $type = Island::TYPE_SCRUBLAND): void
    {
        if ($this->getPlugin()->isAgora()) {
            $this->getPlugin()->getPlayerData()->setValue($player, PlayerData::NEW_ISLAND, $type);

            $this->getPlugin()->getPlayerManager()->transferPlayer($player, ServerManager::GAME_TYPE_SKYLAND);
            return;
        }

        $player->sendMessage(MMOPlugin::getPrefix() . "Please wait while the server is creating an island for you.");

        Await::f2c(function () use ($player, $type) {
            $start = microtime(true);
            $memStart = memory_get_usage();

            $island = new Island($player->getName(), $player->getXuid(), [Island::DATA_TYPE => $type]);

            $this->getIslandStorage()->generateIslandWorld($island, $type, yield);

            /** @var World|null $world */
            $world = yield Await::ONCE;

            if ($world === null) {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "The server was unable to load your island, please report this issue to a staff member.");

                return;
            }

            $serverId = $this->getPlugin()->getEssentials()->getServerManager()->getUniqueId();
            $package = json_encode($island->getData(), JSON_THROW_ON_ERROR);

            Database::executeInsert(Database::CREATE_ISLAND, [
                'xuid' => $island->getOwnerXuid(),
                'owner' => $island->getOwner(),
                'location' => $serverId,
                'package' => $package
            ], yield Await::RESOLVE_MULTI, yield Await::REJECT);

            [$insertId, $rowsAffected] = yield Await::ONCE;

            if ($rowsAffected === 0) {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You already have owned an island.");
                return;
            }

            // The island will automatically be unloaded if the is no players inside this island.
            $this->islands[$player->getName()] = $island;

            $this->getPlugin()->getPlayerData()->setValue($player, PlayerData::HAS_ISLAND, true);

            if (($player = $this->getPlugin()->getServer()->getPlayerExact($island->getOwner())) !== null) {
                $player->teleport($world->getSafeSpawn());
            }

            $end = microtime(true);
            $memEnd = memory_get_usage();

            self::log("Island creation for {$island->getOwner()} done! " . round(($end - $start) * 1000, 2) . "ms to execute, memory footprint (asynchronous) " . round(($memEnd - $memStart) / 1048576, 2) . "MB");
        }, catches: static function (Throwable $error) use ($player): void {
            if ($player->isConnected()) {
                $player->sendMessage("An error has occurred while attempting to create an island, try again later.");
            }

            GlobalLogger::get()->logException($error);
        });
    }

    /**
     * Permanently deletes island from the server.
     * This action is non-reversible, it will delete the world in PlayerIslands directory.
     *
     * @param Island $island
     */
    public function deleteIsland(Island $island): void
    {
        self::log("Attempting to delete island {$island->getOwner()}");

        if ($island->getWorld() === null || $island->isLocked()) {
            return;
        }

        $island->setLocked(true);
        $island->setUnloadLock(false);

        unset($this->islands[$island->getOwner()]);

        $this->getIslandStorage()->deleteIsland($island->getWorld(), function ($status) use ($island) {
            if ($status === IslandStorage::ISLAND_POST_DELETE) {
                Await::f2c(function () use ($island) {
                    Database::executeInsert(Database::REMOVE_ISLAND, [
                        'xuid' => $island->getOwnerXuid()
                    ], yield, yield Await::REJECT);
                    yield Await::ONCE;

                    if (($player = $this->getPlugin()->getServer()->getPlayerExact($island->getOwner())) !== null) {
                        $player->sendMessage(TextFormat::RED . 'Your island has been permanently deleted.');
                    }

                    self::log("Island deletion completed for {$island->getOwner()}");
                }, catches: function (Throwable $error) use ($island): void {
                    $this->getPlugin()->getLogger()->logException($error);

                    if (($player = $this->getPlugin()->getServer()->getPlayerExact($island->getOwner())) !== null) {
                        $player->sendMessage(TextFormat::RED . 'The server was unable to delete your island, please contact a staff member to resolve this issue.');
                    }

                    self::log("Island deletion failed for {$island->getOwner()}");
                });
            } else if ($status === false) {
                if (($player = $this->getPlugin()->getServer()->getPlayerExact($island->getOwner())) !== null) {
                    $player->sendMessage(TextFormat::RED . 'The server was unable to delete your island, please contact a staff member to resolve this issue.');
                }

                self::log("Island deletion failed for {$island->getOwner()}");
            }
        });

    }

    public function getIslandStorage(): IslandStorage
    {
        return $this->islandStorage;
    }

    public function getIslandLevelManager(): IslandLevelManager
    {
        return $this->islandLevelManager;
    }

    public static function log(string $message): void
    {
        SkyBlock::getInstance()->getLogger()->warning($message);
    }

    public function saveAllIslands(): void
    {
        Await::f2c(function () {
            foreach ($this->getPlugin()->getServer()->getWorldManager()->getWorlds() as $world) {
                if (!str_contains($world->getFolderName(), IslandStorage::ISLAND_PREFIX)) continue;

                // The condition null can sometime happen when asynchronous operation is not finished executing.
                $island = $this->getIslandByWorld($world);
                if ($island !== null) {
                    Database::executeChange(Database::SET_ISLAND, [
                        'xuid' => $island->getOwnerXuid(),
                        'package' => json_encode($island->getData(), JSON_THROW_ON_ERROR)
                    ], yield, yield Await::REJECT);
                    yield Await::ONCE;

                    $this->getIslandStorage()->saveIslandImmediate($island);

                    Database::executeChangeRaw("UPDATE instance SET location = IF(location = ?, NULL, location) WHERE owner = ?", [
                        $this->getPlugin()->getEssentials()->getServerManager()->getUniqueId(),
                        $island->getOwner(),
                    ], yield, yield Await::REJECT);
                    yield Await::ONCE;
                }
            }
        });
    }
}