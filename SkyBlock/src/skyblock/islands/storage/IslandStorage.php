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

declare(strict_types=1);

namespace skyblock\islands\storage;

use Closure;
use Generator;
use GlobalLogger;
use libasyncio\FileCopyAsyncTask;
use libasyncio\FileDeleteAsyncTask;
use libasyncio\FileOrDirectoryCompressTask;
use libasyncio\FileOrDirectoryUncompressTask;
use libasyncio\s3\response\S3ObjectIdentifier;
use libasyncio\s3\task\S3UploadFileTask;
use libasyncio\compression\CompressionFormat;
use libasyncio\compression\Zstd;
use libasyncio\RecursiveCompressor;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\thread\NGThreadPool;
use pocketmine\event\world\ChunkUnloadEvent;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\Filesystem;
use pocketmine\utils\Utils;
use pocketmine\world\World;
use RuntimeException;
use skyblock\islands\Island;
use skyblock\islands\IslandManager;
use skyblock\SkyBlock;
use skyblock\task\IslandSearchAsyncTask;
use skyblock\utils\BaseListener;
use SOFe\AwaitGenerator\Await;
use Throwable;
use Symfony\Component\Filesystem\Path;

/**
 * Responsible in copying island worlds into IslandStorage,
 * this component is separated from IslandManager itself to provide better
 * understanding of the entire class itself.
 *
 * @package skyblock\islands
 */
class IslandStorage extends BaseListener
{
    public const ISLAND_PRE_DELETE = 0;
    public const ISLAND_POST_DELETE = 1;

    public const ISLAND_PREFIX = 'Island-';
    public const ISLAND_BACKUP = '-BACKUP';

    private const ISLAND_DEFAULT_NAME = 'DefaultIslands';
    private const ISLAND_CACHED_NAME = 'CachedIslands';
    private const BACKUP_ISLANDS_FILE = 'BackupIslands/';
    private const PLAYER_S1_ISLANDS_PATH = 'PlayerIslands-s1/';
    private const PLAYER_S2_ISLANDS_PATH = 'PlayerIslands-s2/';
    private const PLAYER_S3_ISLANDS_PATH = 'PlayerIslands-s3/';
    private const PLAYER_S4_ISLANDS_PATH = 'PlayerIslands-s4/';
    private const PLAYER_S5_ISLANDS_PATH = 'PlayerIslands-s5/';
    private const PLAYER_DEV_ISLANDS_PATH = 'PlayerIslands-dev/';


    /** @var string */
    public static string $islandStoragePath;

    /** @var true[] */
    private array $writeLocked = [];
    /** @var array */
    private array $writeLockQueue = [];

    public function __construct(SkyBlock $plugin)
    {
        parent::__construct($plugin);

        self::$islandStoragePath = $plugin->getDataFolder();

        foreach ([self::ISLAND_DEFAULT_NAME, self::ISLAND_CACHED_NAME] as $folder) {
            $path = Path::join($plugin->getDataFolder(), $folder);

            if (!file_exists($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $path));
            }
        }

        /** @var list<string> $glob */
        $glob = glob($plugin->getServer()->getDataPath() . '/worlds/' . self::ISLAND_PREFIX . '*', GLOB_ONLYDIR);

        foreach ($glob as $dir) {
            $ownerXuid = explode(self::ISLAND_PREFIX, $dir)[1];
            IslandManager::log("Found orphaned island $ownerXuid, attempting to save a backup.");

            RecursiveCompressor::compress($world = self::createWorldDirectory($ownerXuid), self::createIslandDirectory($ownerXuid, true, false), null, CompressionFormat::ZSTD);

            // This should be thread-blocking, which is okay, we want to block this thread until the backup operation
            // is completed, so that everything is backed up to the server before it is being started.
            S3UploadFileTask::uploadFileObject(SkyBlock::getCredentials(), self::createIslandIdentifier($ownerXuid, true), self::createIslandDirectory($ownerXuid, true));

            Filesystem::recursiveUnlink($world);
            Filesystem::recursiveUnlink($dir);
        }

        $plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
    }

    /**
     * Attempt to load a world based on the player xuid from the IslandStorage directory.
     * This function will always be executed asynchronously.
     *
     * @param string $ownerXuid
     * @param Closure $callback This will return each state of the world load order
     */
    public function loadIsland(string $ownerXuid, Closure $callback): void
    {
        $fileName = self::ISLAND_PREFIX . $ownerXuid;

        Await::f2c(function () use ($ownerXuid, $callback, $fileName): Generator {
            $identifier = self::createIslandIdentifier($ownerXuid, true);
            $worldPath = self::createWorldDirectory($ownerXuid);

            $storageManager = SkyBlock::getStorageManager();
            $storageManager->isObjectExists($identifier, yield, yield Await::REJECT);
            $hasBackup = yield Await::ONCE;

            // Attempt to load a backup world from another saved session.
            if ($hasBackup) {
                $cacheDirectory = self::createIslandDirectory($ownerXuid, true);

                $storageManager->getFileObject($identifier, $cacheDirectory, yield, yield Await::REJECT);
                $isSuccessful = yield Await::ONCE;

                if ($isSuccessful) {
                    NGThreadPool::getInstance()->submitTask(new FileOrDirectoryUncompressTask(str_replace('.' . CompressionFormat::ZSTD->getFileExtension(), '', $cacheDirectory), $worldPath, yield));
                    yield Await::ONCE;

                    NGThreadPool::getInstance()->submitTask(new IslandSearchAsyncTask($worldPath, yield));
                    NGThreadPool::getInstance()->submitTask(new FileDeleteAsyncTask($cacheDirectory, yield));
                    $storageManager->deleteObject($identifier, yield, yield Await::REJECT);

                    [$status] = yield Await::ALL;

                    $extraData = [];
                    if ($status[0] === IslandSearchAsyncTask::RESULT_FAILURE) {
                        GlobalLogger::get()->warning("Island search results for $ownerXuid fails, skipping.");
                    } else {
                        $extraData = $status[1];
                    }

                    if (Server::getInstance()->getWorldManager()->loadWorld($fileName)) {
                        $world = Server::getInstance()->getWorldManager()->getWorldByName($fileName);

                        $callback(new IslandLoadState(IslandLoadState::LOADED, $fileName, $world, $extraData));
                        return;
                    }
                } else {
                    $callback(new IslandLoadState(IslandLoadState::ERROR, $fileName));
                    return;
                }
            }

            $identifier = self::createIslandIdentifier($ownerXuid);
            $cacheDirectory = self::createIslandDirectory($ownerXuid);

            $storageManager->isObjectExists($identifier, yield, yield Await::REJECT);
            $worldExists = yield Await::ONCE;

            if ($worldExists) {
                $storageManager->getFileObject($identifier, $cacheDirectory, yield, yield Await::REJECT);
                $isSuccessful = yield Await::ONCE;

                if ($isSuccessful) {
                    NGThreadPool::getInstance()->submitTask(new FileOrDirectoryUncompressTask(str_replace('.' . CompressionFormat::ZSTD->getFileExtension(), '', $cacheDirectory), $worldPath, yield));
                    yield Await::ONCE;

                    NGThreadPool::getInstance()->submitTask(new IslandSearchAsyncTask($worldPath, yield));
                    NGThreadPool::getInstance()->submitTask(new FileDeleteAsyncTask($cacheDirectory, yield));

                    [$status] = yield Await::ALL;

                    $extraData = [];
                    if ($status[0] === IslandSearchAsyncTask::RESULT_FAILURE) {
                        GlobalLogger::get()->warning("Island search results for $ownerXuid fails, skipping.");
                    } else {
                        $extraData = $status[1];
                    }

                    if (Server::getInstance()->getWorldManager()->loadWorld($fileName)) {
                        $world = Server::getInstance()->getWorldManager()->getWorldByName($fileName);

                        $callback(new IslandLoadState(IslandLoadState::LOADED, $fileName, $world, $extraData));
                    } else {
                        $callback(new IslandLoadState(IslandLoadState::CORRUPTED, $fileName));
                    }
                } else {
                    $callback(null);
                }
            } else {
                $callback(null);
            }
        }, catches: function (Throwable $error) use ($callback, $fileName): void {
            $this->getPlugin()->getLogger()->logException($error);

            $callback(new IslandLoadState(IslandLoadState::ERROR, $fileName));
        });
    }

    /**
     * Attempt to unload an island from this server.
     *
     * @param World $world
     * @param Closure $callback
     */
    public function unloadIsland(World $world, Closure $callback): void
    {
        Await::f2c(function () use ($world, $callback): Generator {
            if (isset($this->writeLocked[$world->getFolderName()])) {
                $this->writeLockQueue[$world->getFolderName()][] = yield;

                yield Await::ONCE;
            }

            $wm = Server::getInstance()->getWorldManager();
            if ($world->isLoaded()) {
                $world->save(true);

                $wm->unloadWorld($world, true);
            }

            $this->writeIsland($world->getFolderName(), yield);
            $status = yield Await::ONCE;

            $callback($status);
        }, catches: function (Throwable $error) use ($callback): void {
            $this->getPlugin()->getLogger()->logException($error);

            $callback(false);
        });
    }

    private function putIslandRecursive(S3ObjectIdentifier $identifier, string $path, closure $onSuccess): void
    {
        Await::f2c(function () use ($identifier, $path) {
            SkyBlock::getStorageManager()->putFileObject($identifier, $path, yield, yield Await::REJECT);

            return yield Await::ONCE;
        }, $onSuccess, function (Throwable $error) use ($identifier, $path, $onSuccess): void {
            GlobalLogger::get()->error("Unable to upload file to host, retrying in 5 seconds...");
            GlobalLogger::get()->logException($error);

            $this->getPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($identifier, $path, $onSuccess): void {
                $this->putIslandRecursive($identifier, $path, $onSuccess);
            }), 5 * 20);
        });
    }

    /**
     * Attempt to copy the worlds' folder into a compressed directory of zstd.
     *
     * @param string $worldName
     * @param Closure $callback
     * @param bool $clean
     */
    private function writeIsland(string $worldName, Closure $callback, bool $clean = true): void
    {
        Await::f2c(function () use ($worldName, $callback, $clean): Generator {
            if (isset($this->writeLocked[$worldName])) {
                $this->writeLockQueue[$worldName][] = yield;

                yield Await::ONCE;
            }

            $this->writeLocked[$worldName] = true;

            $ownerXuid = str_replace(self::ISLAND_PREFIX, '', $worldName);

            // Copy the world into cache directory. Later we have to delete them.
            NGThreadPool::getInstance()->submitTask(new FileOrDirectoryCompressTask($from = self::createWorldDirectory($ownerXuid), self::createIslandDirectory($ownerXuid, addPrefix: false), yield));
            yield Await::ONCE;

            $to = self::createIslandDirectory($ownerXuid); // Path from the storage cache (disk)

            $this->putIslandRecursive(self::createIslandIdentifier($ownerXuid), self::createIslandDirectory($ownerXuid), yield);
            yield Await::ONCE;

            $callback(true); // Let the callback do its thing, if it fails then we do not delete the world.

            if ($clean) {
                // Then we delete the world saved in the server worlds directory.
                NGThreadPool::getInstance()->submitTask(new FileDeleteAsyncTask($from, yield));
                yield Await::ONCE;
            }

            // Delete cached islands file.
            NGThreadPool::getInstance()->submitTask(new FileDeleteAsyncTask($to, yield));
            yield Await::ONCE;

            unset($this->writeLocked[$worldName]);
            if (isset($this->writeLockQueue[$worldName])) {
                foreach ($this->writeLockQueue[$worldName] as $queue) {
                    $queue();
                }

                unset($this->writeLockQueue[$worldName]);
            }
        }, catches: function (Throwable $error) use ($callback): void {
            $this->getPlugin()->getLogger()->logException($error);

            $callback(false);
        });
    }

    /**
     * Attempt to delete the island world forever, this function will delete all the player island progress
     * in the loaded world AND the stored PlayerIslands data.
     *
     * @param World $world
     * @param Closure $callback
     */
    public function deleteIsland(World $world, Closure $callback): void
    {
        Await::f2c(function () use ($world, $callback): Generator {
            $ownerXuid = str_replace(self::ISLAND_PREFIX, '', $world->getFolderName());

            $this->unloadIsland($world, yield);
            $result = yield Await::ONCE;

            if ($result) {
                $callback(self::ISLAND_PRE_DELETE);

                // Delete both backup and original files.
                SkyBlock::getStorageManager()->deleteObject(self::createIslandIdentifier($ownerXuid), yield, yield Await::REJECT);
                SkyBlock::getStorageManager()->deleteObject(self::createIslandIdentifier($ownerXuid, true), yield, yield Await::REJECT);
                yield Await::ALL;

                $callback(self::ISLAND_POST_DELETE);
            } else {
                $callback(false);
            }
        }, catches: function (Throwable $error) use ($callback): void {
            $this->getPlugin()->getLogger()->logException($error);

            $callback(false);
        });
    }

    /**
     * Attempt to copy the world into the specified island worlds directory.
     *
     * @param Island $island
     * @param Closure $onComplete
     */
    public function saveIsland(Island $island, Closure $onComplete): void
    {
        Await::f2c(function () use ($island, $onComplete): Generator {
            if (Utils::getOS() === Utils::OS_WINDOWS) {
                GlobalLogger::get()->warning("Copy-on-write for Windows is not available, please use Linux-family operating system.");

                $onComplete(false);
            } else {
                $this->writeIsland($island->getWorld()->getFolderName(), yield, false);
                $status = yield Await::ONCE;

                $onComplete($status);
            }
        });
    }

    /**
     * Save the island immediately, this operation is a thread-blocking operation.
     * It will save the island in the main thread!
     *
     * @param Island $island The island that needs to be unloaded.
     * @return bool {@code true} If the island has been unloaded.
     */
    public function saveIslandImmediate(Island $island): bool
    {
        if ($island->getWorld() === null) {
            return false;
        }

        $wm = $this->getPlugin()->getServer()->getWorldManager();
        if ($island->getWorld()->isLoaded()) {
            $island->getWorld()->save(true);

            $wm->unloadWorld($island->getWorld(), true);
        }

        RecursiveCompressor::compress($world = self::createWorldDirectory($island->getOwnerXuid()), self::createIslandDirectory($island->getOwnerXuid(), addPrefix: false), Zstd::LEVEL_DEFAULT, CompressionFormat::ZSTD);
        Filesystem::recursiveUnlink($world);

        $island->setWorld(null);

        S3UploadFileTask::uploadFileObject(SkyBlock::getCredentials(), self::createIslandIdentifier($island->getOwnerXuid()), self::createIslandDirectory($island->getOwnerXuid()));

        IslandManager::log('Island ' . $island->getOwner() . ' has been unloaded from the server.');

        return true;
    }

    /**
     * Attempt to generate a new world for the given island, this function will be executed
     * asynchronously and a callback method will be called after this operation has completed.
     *
     * @param Island $island The island data.
     * @param int $type The type of the island.
     * @param Closure $callback The callback method indicates the operation is completed.
     */
    public function generateIslandWorld(Island $island, int $type, Closure $callback): void
    {
        Await::f2c(function () use ($island, $type, $callback) {
            NGThreadPool::getInstance()->submitTask(new FileCopyAsyncTask(Path::join(self::$islandStoragePath, 'DefaultIslands', Island::SKY_BLOCK_DATA[$type][Island::MAP_NAME]), Path::join($this->getPlugin()->getServer()->getDataPath(), 'worlds', self::ISLAND_PREFIX . $island->getOwnerXuid()), yield));
            yield Await::ONCE;

            $this->writeIsland(self::ISLAND_PREFIX . $island->getOwnerXuid(), yield, false);
            $status = yield Await::ONCE;

            if (!$status) {
                $callback(null);
            } else {
                $server = $this->getPlugin()->getServer();
                $server->getWorldManager()->loadWorld($worldName = self::ISLAND_PREFIX . $island->getOwnerXuid());

                $island->setWorld($server->getWorldManager()->getWorldByName($worldName));
                $island->spawnNPC();

                $island->getWorld()->save(true);

                $this->saveIsland($island, yield);
                yield Await::ONCE;

                $callback($island->getWorld());
            }
        }, catches: function (Throwable $error) use ($callback): void {
            $this->getPlugin()->getLogger()->logException($error);

            $callback(null);
        });
    }

    public static function createIslandIdentifier(string $ownerXuid, bool $isBackup = false): S3ObjectIdentifier
    {
        return new S3ObjectIdentifier(($isBackup ? self::BACKUP_ISLANDS_FILE : (NGEssentials::isInDevelopmentMode() ? self::PLAYER_DEV_ISLANDS_PATH : self::PLAYER_S5_ISLANDS_PATH)) . $ownerXuid . '.' . CompressionFormat::ZSTD->getFileExtension());
    }

    public static function createIslandDirectory(string $ownerXuid, bool $isBackup = false, bool $addPrefix = true): string
    {
        return Path::join(self::$islandStoragePath, 'CachedIslands', $ownerXuid . ($isBackup ? self::ISLAND_BACKUP : '') . ($addPrefix ? '.' . CompressionFormat::ZSTD->getFileExtension() : ''));
    }

    public static function createWorldDirectory(string $ownerXuid): string
    {
        return Path::join(Server::getInstance()->getDataPath(), 'worlds', self::ISLAND_PREFIX . $ownerXuid);
    }

    /**
     * @param ChunkUnloadEvent $event
     * @priority NORMAL
     */
    public function onChunkUnload(ChunkUnloadEvent $event): void
    {
        if (isset($this->writeLocked[$event->getWorld()->getFolderName()])) {
            $event->cancel();
        }
    }
}

