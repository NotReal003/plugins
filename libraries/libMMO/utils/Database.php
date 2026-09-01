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

use Closure;
use Exception;
use GlobalLogger;
use JsonException;
use libMMO\EventListener;
use libMMO\MMOPlugin;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\tasks\PingDbTask;
use NetherGames\NGEssentials\thread\NGThreadPool;
use NetherGames\NGEssentials\utils\MySQLCredentials;
use pocketmine\scheduler\Task;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use poggit\libasynql\result\SqlChangeResult;
use poggit\libasynql\result\SqlInsertResult;
use poggit\libasynql\result\SqlSelectResult;
use poggit\libasynql\SqlError;
use poggit\libasynql\SqlThread;
use SOFe\AwaitGenerator\AwaitException;
use Throwable;
use function in_array;
use function microtime;

class Database
{
    public const PLAYER_GET_DATA = 'player.player_get_data';
    public const PLAYER_CREATE = 'player.create';
    public const PLAYER_CHANGE_NAME = 'player.change_name';
    public const PLAYER_SELECT_ALIKE = 'player.get_player_alike';
    public const PLAYER_UNLOCK_LOCATION = 'player.unlock_server_location';
    public const PLAYER_LOCK_LOCATION = 'player.lock_server_location';
    public const PLAYER_LOCK_VERIFY_LOCATION = 'player.lock_verify_server_location';
    public const PLAYER_LOCK_STATUS = 'player.get_lock_status';

    public const GET_ALL_EXPIRED_AUCTIONS = 'auctionhouse.get_all_expired';
    public const ADD_ITEM_STORAGE = 'item_storage.add';

    public const EXISTS_ITEM_STORAGE = 'item_storage.exists';
    public const REMOVE_ITEM_STORAGE = 'item_storage.remove';

    public const BACKUP_ADD_INVENTORY_DATA = 'backups.backup_inventory';
    public const BACKUP_DELETE_EXPIRED_DATA = 'backups.delete_expired_backup';
    public const BACKUP_GET_INVENTORY_ID = 'backups.get_inventory_backup';
    public const BACKUP_GET_PLAYER_ENTRIES = 'backups.get_player_entries';
    public const BACKUP_INSERT_IDS = 'backups.insert_ids';
    public const BACKUP_GET_IDS = 'backups.get_ids';

    public const BACKUP_GET_PLAYER_INVENTORY = 'backups.select_inventory_data';
    public const BACKUP_UPDATE_PLAYER_INVENTORY = 'backups.update_inventory_data';
    public const BACKUP_UPDATE_STATUS = 'backups.update_backup_status';

    public const CREATE_SERVER_NODE = 'server.create_server_node';
    public const DELETE_SERVER_NODE = 'server.delete_server_node';

    /** @var DataConnector|null */
    private static ?DataConnector $dataConnector = null;
    /** @var MMOPlugin */
    private static MMOPlugin $plugin;
    /** @var array */
    private static array $credentials = [];

    public function __construct(MMOPlugin $plugin, array $credentials)
    {
        self::$plugin = $plugin;
        self::$credentials = $credentials;

        try {
            [$host, $username, $password, $schema] = $credentials;

            self::$dataConnector = libasynql::create($plugin, [
                'type' => 'mysql',
                'mysql' => [
                    'host' => $host,
                    'username' => $username,
                    'password' => $password,
                    'schema' => $schema
                ],
                'worker-limit' => 4
            ], [
                'mysql' => 'mysql.sql'
            ], NGEssentials::isInDevelopmentMode());

            $plugin->getLogger()->info('§aMySQL Database connection established successfully!');
        } catch (SqlError $exception) {
            $plugin->getLogger()->info('§cMySQL Database connection failed: ' . $exception->getMessage());
        }
    }

    public static function executeChange(string $queryName, array $args = [], ?callable $onSuccess = null, ?callable $onError = null): void
    {
        if (self::isDatabaseOnline()) {
            $startTime = microtime(true);

            self::getMySQLDatabase()->executeImplLast($queryName, $args, SqlThread::MODE_CHANGE, static function (SqlChangeResult $result) use ($onSuccess, $args, $queryName, $startTime): void {
                if ($onSuccess !== null) {
                    $onSuccess($result->getAffectedRows());
                }

                self::calculateQueryDuration($queryName, $args, $startTime);
            }, self::onErrorWrapper($onError, $queryName, $startTime));
            return;
        }

        self::onDatabaseOffline($queryName, $args, $onError);
    }

    public static function isDatabaseOnline(): bool
    {
        return self::$dataConnector !== null;
    }

    public static function getMySQLDatabase(): DataConnector
    {
        return self::$dataConnector;
    }

    private static function calculateQueryDuration(string $queryName, array $args, float $startTime, ?SqlError $error = null): void
    {
        $data = '';
        try {
            $data = json_encode($args, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
        }

        $duration = microtime(true) - $startTime;

        $logger = self::getPlugin()->getLogger();
        if ($error === null) {
            if ($duration > 20) {
                $logger->error('§c' . $queryName . ' took ' . $duration . ' seconds to execute!');
            } else {
                $logger->info("Query $queryName, $data executed in $duration seconds");
            }
        } else {
            $logger->emergency("Query $queryName, $data failed in $duration seconds: " . $error->getErrorMessage());
        }
    }

    public static function getPlugin(): MMOPlugin
    {
        return self::$plugin;
    }

    private static function onErrorWrapper(?callable $handler, string $query, float $startTime): callable
    {
        return static function (SqlError $result) use ($handler, $query, $startTime): void {
            if ($handler !== null) {
                $handler($result);
            }

            if ($result->getStage() === SqlError::STAGE_CONNECT || ($result->getStage() === SqlError::STAGE_EXECUTE && in_array($result->getErrorMessage(), ['Connection was killed', 'MySQL server has gone away'], true))) {
                self::setOffline();
            }

            self::calculateQueryDuration($query, [], $startTime, $result);
        };
    }

    private static function setOffline(): void
    {
        if (self::$dataConnector !== null) {
            self::$dataConnector = null;
            self::$plugin->getLogger()->emergency('§cMySQL Database connection lost!');

            self::getPlugin()->getScheduler()->scheduleRepeatingTask(new class(self::$credentials) extends Task {
                /** @var array */
                private array $credentials;

                public function __construct(array $credentials)
                {
                    $this->credentials = $credentials;
                }

                public function onRun(): void
                {
                    if (MySQLCredentials::isDatabaseOnline() || EventListener::$restarting) {
                        $this->getHandler()->cancel();
                    } else {
                        NGThreadPool::getInstance()->submitTask(new PingDbTask($this->credentials));
                    }
                }
            }, 60 * 20);
        }
    }

    private static function onDatabaseOffline(string $query, array $args, ?callable $onError): void
    {
        if ($onError !== null) {
            $onError(new SqlError(SqlError::STAGE_EXECUTE, "MySQL server has gone away", $query, $args));
        }
    }

    public static function executeGenericRaw(string $query, array $args = [], ?callable $onSuccess = null, ?callable $onError = null): void
    {
        if (self::isDatabaseOnline()) {
            $startTime = microtime(true);

            self::getMySQLDatabase()->executeImplRaw([$query], [$args], [SqlThread::MODE_GENERIC], static function () use ($onSuccess, $args, $query, $startTime) {
                if ($onSuccess !== null) {
                    $onSuccess();
                }

                self::calculateQueryDuration($query, $args, $startTime);
            }, self::onErrorWrapper($onError, $query, $startTime));
            return;
        }

        self::onDatabaseOffline($query, $args, $onError);
    }

    public static function executeChangeRaw(string $query, array $args = [], ?callable $onSuccess = null, ?callable $onError = null): void
    {
        if (self::isDatabaseOnline()) {
            $startTime = microtime(true);

            self::getMySQLDatabase()->executeImplRaw([$query], [$args], [SqlThread::MODE_CHANGE], static function (array $results) use ($onSuccess, $args, $query, $startTime) {
                if ($onSuccess !== null) {
                    /** @var SqlChangeResult $result */
                    $result = $results[0];
                    $onSuccess($result->getAffectedRows());
                }

                self::calculateQueryDuration($query, $args, $startTime);
            }, self::onErrorWrapper($onError, $query, $startTime));
            return;
        }

        self::onDatabaseOffline($query, $args, $onError);
    }

    public static function executeInsert(string $queryName, array $args = [], ?callable $onInserted = null, ?callable $onError = null): void
    {
        if (self::isDatabaseOnline()) {
            $startTime = microtime(true);

            self::getMySQLDatabase()->executeImplLast($queryName, $args, SqlThread::MODE_INSERT, static function (SqlInsertResult $result) use ($onInserted, $args, $queryName, $startTime) {
                if ($onInserted !== null) {
                    $onInserted($result->getInsertId(), $result->getAffectedRows());
                }

                self::calculateQueryDuration($queryName, $args, $startTime);
            }, self::onErrorWrapper($onError, $queryName, $startTime));
            return;
        }

        self::onDatabaseOffline($queryName, $args, $onError);
    }

    public static function executeInsertRaw(string $query, array $args = [], ?callable $onInserted = null, ?callable $onError = null): void
    {
        if (self::isDatabaseOnline()) {
            $startTime = microtime(true);

            self::getMySQLDatabase()->executeImplRaw([$query], [$args], [SqlThread::MODE_INSERT], static function (array $results) use ($onInserted, $args, $query, $startTime) {
                if ($onInserted !== null) {
                    /** @var SqlInsertResult $result */
                    $result = $results[0];
                    $onInserted($result->getInsertId(), $result->getAffectedRows());
                }

                self::calculateQueryDuration($query, $args, $startTime);
            }, self::onErrorWrapper($onError, $query, $startTime));
            return;
        }

        self::onDatabaseOffline($query, $args, $onError);
    }

    public static function executeGeneric(string $queryName, array $args = [], ?callable $onSuccess = null, ?callable $onError = null): void
    {
        if (self::isDatabaseOnline()) {
            $startTime = microtime(true);

            self::getMySQLDatabase()->executeImplLast($queryName, $args, SqlThread::MODE_GENERIC, static function () use ($onSuccess, $args, $queryName, $startTime) {
                if ($onSuccess !== null) {
                    $onSuccess();
                }

                self::calculateQueryDuration($queryName, $args, $startTime);
            }, self::onErrorWrapper($onError, $queryName, $startTime));
            return;
        }

        self::onDatabaseOffline($queryName, $args, $onError);
    }

    public static function executeSelect(string $queryName, array $args = [], ?callable $onSelect = null, ?callable $onError = null): void
    {
        if (self::isDatabaseOnline()) {
            $startTime = microtime(true);

            self::getMySQLDatabase()->executeImplLast($queryName, $args, SqlThread::MODE_SELECT, static function (SqlSelectResult $result) use ($onSelect, $args, $queryName, $startTime) {
                if ($onSelect !== null) {
                    $onSelect($result->getRows(), $result->getColumnInfo());
                }

                self::calculateQueryDuration($queryName, $args, $startTime);
            }, self::onErrorWrapper($onError, $queryName, $startTime));
            return;
        }

        self::onDatabaseOffline($queryName, $args, $onError);
    }

    public static function executeSelectRaw(string $query, array $args = [], ?callable $onSelect = null, ?callable $onError = null): void
    {
        if (self::isDatabaseOnline()) {
            $startTime = microtime(true);

            self::getMySQLDatabase()->executeImplRaw([$query], [$args], [SqlThread::MODE_SELECT], static function (array $results) use ($onSelect, $args, $query, $startTime) {
                if ($onSelect !== null) {
                    /** @var SqlSelectResult $result */
                    $result = $results[0];
                    $onSelect($result->getRows());
                }

                self::calculateQueryDuration($query, $args, $startTime);
            }, self::onErrorWrapper($onError, $query, $startTime));
            return;
        }

        self::onDatabaseOffline($query, $args, $onError);
    }

    public static function getFailClosure(bool $keepSilent = false): Closure
    { //This seems obsolete due to the wrapper, but it's used in the old code
        $trace = new Exception("This is the original stack trace for the following error.");
        return static function ($result) use ($trace, $keepSilent): void {
            if ($keepSilent) {
                return; // Ignore certain errors.
            }

            if ($result instanceof SqlError) {
                if ($result->getStage() === SqlError::STAGE_CONNECT) {
                    self::setOffline();
                }

                self::getPlugin()->getLogger()->emergency($result->getQuery() . ' - ' . $result->getErrorMessage());
                GlobalLogger::get()->logException(new AwaitException("An SqlError was thrown, following are the original stack trace.", 0, $trace));
            } else if ($result instanceof Throwable) {
                GlobalLogger::get()->logException(new AwaitException($result->getMessage(), 0, $trace));
            } else if ($result !== null) {
                GlobalLogger::get()->info('Unknown throwable context given: ' . get_class($result));
                GlobalLogger::get()->logException(new AwaitException("An exception was thrown without context.", 0, $trace));
            }
        };
    }

    public function close(): void
    {
        if (self::isDatabaseOnline()) {
            $plugin = self::getPlugin();
            $logger = $plugin->getLogger();
            $logger->info("Closing database connection");
            if (!$plugin->getServer()->isRunning()) {
                $logger->info("Waiting for all queries to be executed");
                self::getMySQLDatabase()->waitAll();
                self::getMySQLDatabase()->close();
            }
            self::$dataConnector = null;
            $logger->info("Database connection closed");
        }
    }
}