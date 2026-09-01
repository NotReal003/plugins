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

namespace libMMO\player;

use Closure;
use GlobalLogger;
use JsonException;
use libMMO\event\PlayerDataSaveEvent;
use libMMO\MMOPlugin;
use libMMO\player\Inventory as MMOInventory;
use libMMO\utils\AwaitUtils;
use libMMO\utils\BaseClass;
use libMMO\utils\Database;
use libMMO\utils\EventEmitter;
use libMMO\vaults\VaultEntry;
use NetherGames\NGEssentials\NGEssentials;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;
use Throwable;
use function array_search;
use function base64_decode;
use function base64_encode;
use function count;
use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function json_decode;
use function json_encode;
use const JSON_THROW_ON_ERROR;
use const PHP_INT_MAX;

abstract class PlayerData extends BaseClass
{
    /** @var int */
    public static int $offset = 0;

    public const DATA_LOADED = -1;
    public const BANK_MONEY = -2;
    public const BOUNTY = -3;
    public const RUNTIME_PRIVATE_VAULTS = -4;

    // Extra-data field, this can be used for caching, especially for voting.
    public const EXTRA_DATA = -5;
    public const PLAYER_TRADE_CACHE = -6;

    public const PLAYER_MONEY = 0;
    public const PLAYER_INVENTORY = 1;
    public const KIT_COOLDOWN = 2;
    public const CRATE_KEYS = 3;
    public const XP = 4;
    public const PROGRESS = 5;
    public const REWARDS = 6;
    public const DAILY_CHALLENGE = 7;
    public const PRIVATE_VAULTS = 8;
    public const PRIVATE_VAULTS_FASTCOPY = 9;
    // 10-119: Reserved for other plugins (Backward compatibility)
    public const ROLLBACK_INVENTORY = 120;
    public const FORM_BLOCKED = 121;

    protected const ARRAY = 0;
    protected const BOOL = 1;
    protected const FLOAT = 2;
    protected const INT = 3;
    protected const OBJECT = 4;
    protected const STRING = 5;

    /** @var array */
    protected array $playerData = [];
    /** @var array */
    protected array $toBeSaved = [];

    /** @var bool[] */
    protected array $onSaved = []; // "Player Name" -> "bool"
    /** @var Closure[][] */
    protected array $onCompletions = []; // "Player Name" -> [ id -> Closure ]

    /**
     * Note for MMO developers: This function handles all data set from the other server, netsys is used to made this
     * transfer possible. However, this function does not release the immobility state after it is done loading the player
     * data. You must release the immobile state in {@link PlayerManager::setupPlayer}
     *
     * @noinspection PhpUnhandledExceptionInspection
     */
    public function loadData(Player $player, array $data): void
    {
        $player->setNoClientPredictions();

        Await::f2c(function () use ($player, $data) {
            $serverId = NGEssentials::getInstance()->getServerManager()->getUniqueId();

            if (empty($data)) {
                $onLock = false;

                lockData:
                if (!$player->isConnected()) {
                    return;
                }

                // Get the lock status for this player.
                Database::executeSelect(Database::PLAYER_LOCK_STATUS, ['xuid' => $player->getXuid()], yield, yield Await::REJECT);
                $lockStatus = yield Await::ONCE;

                // If it is not empty, then the player is already online in other server.
                // Also check if the player is online in the same server (secvln: probably loaded twice?)
                if (!empty($lockStatus) && ($serverOnline = $lockStatus[0]['server_online']) !== null && $lockStatus[0]['server_online'] !== $serverId) {
                    if (!$onLock) {
                        $onLock = true;
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Please wait a few moment for us to load your data from " . TextFormat::YELLOW . $serverOnline);
                    }

                    MMOPlugin::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(yield), 20);
                    yield Await::ONCE;

                    goto lockData;
                }

                Database::executeSelect(Database::PLAYER_GET_DATA, ['xuid' => $player->getXuid(), 'player_name' => $player->getName()], yield, yield Await::REJECT);
                $gameObjects = yield Await::ONCE;

                // Using LEFT JOIN + UNION + RIGHT JOIN, the result is the expected output should be the same as follows:
                // Reference: https://stackoverflow.com/questions/4796872/how-can-i-do-a-full-outer-join-in-mysql

                // [OK] xuid == null && relatedXuid == null                          -> No account has been created for this player (Or the condition are table empty)
                // [OK] xuid != null && relatedXuid == null                          -> Player changed their name
                // [OK] xuid == null && relatedXuid != null                          -> Another player with the same username has already been registered.
                // [OK] xuid != null && relatedXuid != null && relatedXuid != xuid   -> Another player with the same username has already been registered.
                // [OK] xuid != null && relatedXuid != null && relatedXuid == xuid   -> The player account matched with the database.

                if ($exists = (count($gameObjects) > 0)) {
                    $data = $gameObjects[0];
                    if (($data['xuid'] === null && $data['relatedXuid'] !== null) || ($data['xuid'] !== null && $data['relatedXuid'] !== null && $data['relatedXuid'] !== $data['xuid'])) {
                        $player->kick("Another player by the same username has already been registered. Contact support for further details at https://ngmc.co/lc");
                        return;
                    }
                }

                // Now lock the location and verify if it is updated if the player has already existed.
                // Sometimes, the player may join the server for the first time, so we cover that up too.
                if ($exists) {
                    Database::executeChange(Database::PLAYER_LOCK_VERIFY_LOCATION, ['xuid' => $player->getXuid(), 'server' => $serverId], yield, yield Await::REJECT);
                    $affectedRows = yield Await::ONCE;

                    if ($affectedRows === 0) {
                        $player->kick("Unable to load your dataset in this server, please contact a staff if the issue keeps occurring.");
                        return;
                    }
                } else {
                    // This condition here should reject the request if the player has already been
                    // created before. Thus preventing the code below from executing.
                    Database::executeInsert(Database::PLAYER_CREATE, ['xuid' => $player->getXuid(), 'player' => $player->getName(), 'server' => $serverId], yield, yield Await::REJECT);
                    yield Await::ONCE;
                }

                if ($player->isConnected()) {
                    unset($this->playerData[$player->getName()]); // Make sure we are on clean slate

                    /** @var VaultEntry[] $vaultEntries */
                    $vaultEntries = [];

                    for ($vaultNumber = 0; $vaultNumber <= VaultEntry::MAX_PRIVATE_VAULTS; $vaultNumber++) {
                        $vaultEntries[$vaultNumber] = VaultEntry::fastDeserialize($player, $vaultNumber, "[]");
                    }

                    $this->setValue($player, PlayerData::RUNTIME_PRIVATE_VAULTS, $vaultEntries);

                    if ($exists) {
                        $row = $gameObjects[0];

                        if ($player->getName() !== ($oldName = $row['player'])) {
                            $this->onPlayerChangeName($player, $oldName);
                        }

                        foreach ($row as $index => $value) {
                            if (is_int($id = array_search($index, $this->getColumnNames(), true))) {
                                if ($this->getDataTypes()[$id] === self::ARRAY) {
                                    try {
                                        $value = json_decode($value ?? "", true, 512, JSON_THROW_ON_ERROR);
                                    } catch (JsonException) {
                                        $value = [];
                                    }
                                }

                                if (!$this->setValue($player, $id, $value, false, true)) {
                                    $this->getPlugin()->getLogger()->alert("Dataset for player {$player->getName()}, xuid={$player->getXuid()}, id=$id is invalid");
                                    return;
                                }

                                if ($id === self::PRIVATE_VAULTS) {
                                    $vaults = $this->getArray($player, PlayerData::PRIVATE_VAULTS);

                                    foreach ($vaults as $vaultId => $vault) {
                                        $contents = MMOInventory::convertJsonToContents(MMOInventory::convertStringToInventoryJSON(base64_decode($vault ?? "[]")));

                                        $vaultEntries[$vaultId]->getInvMenu()->getInventory()->setContents($contents);
                                    }
                                }
                            }
                        }
                    }

                    $this->setValue($player, self::DATA_LOADED, true);
                    try {
                        $this->getPlugin()->getPlayerManager()->setupPlayer($player, !$exists);
                    } catch (Throwable $throwing) {
                        $this->setValue($player, self::DATA_LOADED, false);

                        GlobalLogger::get()->logException($throwing);
                        GlobalLogger::get()->emergency("Unable to setup player {$player->getName()}, kicking player now.");

                        AwaitUtils::waitPlayerSpawned($player, yield);
                        yield Await::ONCE;

                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "An internal error has occurred. To prevent data lost, your data will not be loaded, transferring you to the lobby.");

                        NGEssentials::getInstance()->getPlayerManager()->transferPlayer($player);
                    }
                } else {
                    Database::executeChange(Database::PLAYER_UNLOCK_LOCATION, ['xuid' => $player->getXuid(), 'server' => $serverId]);
                }
            } else {
                Database::executeChange(Database::PLAYER_LOCK_LOCATION, ['xuid' => $player->getXuid(), 'server' => $serverId], yield, yield Await::REJECT);
                yield Await::ONCE;

                if (!$player->isConnected()) {
                    Database::executeChange(Database::PLAYER_UNLOCK_LOCATION, ['xuid' => $player->getXuid(), 'server' => $serverId]);
                    return;
                }

                unset($this->playerData[$player->getName()]);

                $data[self::PLAYER_INVENTORY] = base64_decode($data[self::PLAYER_INVENTORY]);

                foreach ($data as $id => $value) {
                    if ($id === self::PRIVATE_VAULTS_FASTCOPY) {
                        $vaultEntries = [];

                        foreach ($value as $vaultId => $itemCopy) {
                            $vaultEntries[] = VaultEntry::fastDeserialize($player, $vaultId, $itemCopy);
                        }

                        if (!$this->setValue($player, PlayerData::RUNTIME_PRIVATE_VAULTS, $vaultEntries, false, true)) {
                            $this->getPlugin()->getLogger()->alert("Dataset for player {$player->getName()}, xuid={$player->getXuid()}, id=$id is invalid, raw=" . base64_encode(json_encode($value)));

                            return;
                        }
                    } else {
                        if (!$this->setValue($player, $id, $value, false, true)) {
                            $this->getPlugin()->getLogger()->alert("Dataset for player {$player->getName()}, xuid={$player->getXuid()}, id=$id is invalid, raw=" . base64_encode(json_encode($value)));

                            return;
                        }
                    }
                }

                $this->setValue($player, self::DATA_LOADED, true);
                $this->getPlugin()->getPlayerManager()->setupPlayer($player);
            }
        }, catches: function (Throwable $error) use ($player): void {
            $this->getPlugin()->getLogger()->logException($error);

            if ($player->isConnected()) {
                $player->kick('Data set loading failure, contact staff or try to reconnect again.');
            }
        });
    }

    public function onPlayerChangeName(Player $player, string $oldName): void
    {
        Database::executeChange(Database::PLAYER_CHANGE_NAME, ['xuid' => $player->getXuid(), 'player' => $player->getName()]);
    }

    public function getColumnNames(): array
    {
        return [
            self::PRIVATE_VAULTS => 'vaults',
            self::PLAYER_MONEY => 'money',
            self::BANK_MONEY => 'bank',
            self::BOUNTY => 'bounty',
            self::XP => 'xp',
            self::PLAYER_INVENTORY => 'inventory',
            self::ROLLBACK_INVENTORY => 'backup_inventory',
            self::CRATE_KEYS => 'crate_keys',
            self::KIT_COOLDOWN => 'kit_cooldown',
            self::PROGRESS => 'challenge_progress',
            self::REWARDS => 'rewards',
            self::EXTRA_DATA => 'extra_data',
            self::PLAYER_TRADE_CACHE => 'trade_cache',
            self::DAILY_CHALLENGE => 'daily_challenge',
            self::FORM_BLOCKED => 'form_status'
        ];
    }

    public function getDataTypes(): array
    {
        return [
            self::DATA_LOADED => self::BOOL,
            self::BOUNTY => self::INT,
            self::XP => self::INT,
            self::PLAYER_INVENTORY => self::STRING,
            self::ROLLBACK_INVENTORY => self::STRING,
            self::PLAYER_TRADE_CACHE => self::ARRAY,

            self::FORM_BLOCKED => self::BOOL,
            self::PLAYER_MONEY => self::INT,
            self::BANK_MONEY => self::INT,
            self::KIT_COOLDOWN => self::ARRAY,
            self::CRATE_KEYS => self::ARRAY,
            self::PROGRESS => self::ARRAY,
            self::REWARDS => self::ARRAY,
            self::EXTRA_DATA => self::ARRAY,
            self::DAILY_CHALLENGE => self::ARRAY,
            self::PRIVATE_VAULTS => self::ARRAY,
            self::PRIVATE_VAULTS_FASTCOPY => self::ARRAY,
            self::RUNTIME_PRIVATE_VAULTS => self::ARRAY,
        ];
    }

    /**
     * @param mixed $value
     */
    public function setValue(Player|string $player, int $id, mixed $value, bool $forceSave = false, bool $load = false): bool
    {
        if ($player instanceof Player) {
            $player = $player->getName();
        }

        if (($validatedValue = $this->validateValue($id, $value)) === null) {
            $this->getPlugin()->getLogger()->alert('Invalid datatype for player ' . $player . ', id: ' . $id . '| value: ' . (string)$value);

            $playerInstance = $this->getPlugin()->getServer()->getPlayerExact($player);
            if ($playerInstance instanceof Player && $playerInstance->isConnected()) {
                $playerInstance->kick('Invalid data set');
            }

            return false;
        } else {
            $this->playerData[$player][$id] = $validatedValue;

            if (($playerInstance = $this->getPlugin()->getServer()->getPlayerExact($player)) !== null) {
                if ($id === self::PLAYER_MONEY) {
                    $this->getPlugin()->getPlayerManager()->updateMoneyScoreboard($playerInstance);
                } elseif ($id === self::BANK_MONEY) {
                    $this->getPlugin()->getPlayerManager()->updateBankScoreboard($playerInstance, $value);
                }
            } else {
                if ($id === self::PLAYER_MONEY) {
                    $this->getPlugin()->getEventEmitter()->broadcastDefault($player, EventEmitter::NOTIFICATION_MONEY);
                } elseif ($id === self::BANK_MONEY) {
                    $this->getPlugin()->getEventEmitter()->broadcastDefault($player, EventEmitter::NOTIFICATION_BANK);
                }
            }

            if (!$load && isset($this->getColumnNames()[$id]) && $this->getBool($player, self::DATA_LOADED)) {
                if ($forceSave) {
                    $this->saveValue($player, $id);
                } else {
                    $this->toBeSaved[$player][$id] = true;
                }
            }
        }

        return true;
    }

    /**
     * @param int $id
     * @param mixed $value
     * @return array|bool|int|object|string|null
     */
    public function validateValue(int $id, $value)
    {
        $data_type = $this->getDataTypes()[$id];

        if ($data_type === self::ARRAY && is_array($value)) {
            return $value;
        }

        if ($data_type === self::BOOL) {
            if (is_bool($value)) {
                return $value;
            }

            if (is_string($value) || is_int($value) || is_float($value)) {
                return (bool)$value;
            }
        }

        if ($data_type === self::FLOAT && (is_float($value) || is_int($value))) {
            return $value;
        }

        if ($data_type === self::INT) {
            if (is_int($value)) {
                return $value;
            }

            if (is_float($value) || is_bool($value)) {
                return (int)$value;
            }
        }

        if ($data_type === self::OBJECT && is_object($value)) {
            return $value;
        }

        if ($data_type === self::STRING && is_string($value)) {
            return $value;
        }

        return null;
    }

    /**
     * @param Player|string $player
     * @param int $id
     * @return bool
     */
    public function getBool($player, int $id): bool
    {
        return (bool)$this->getValue($player, $id);
    }

    /**
     * @param int $id
     * @return array|bool|int|string|null
     */
    public function getDefaultValue(int $id)
    {
        $data_type = $this->getDataTypes()[$id];

        if ($data_type === self::ARRAY) {
            return [];
        }

        if ($data_type === self::BOOL) {
            return false;
        }

        if ($data_type === self::INT || $data_type === self::FLOAT) {
            return 0;
        }

        //OBJECT HAS NO DEFAULT VALUE;

        if ($data_type === self::STRING) {
            return '';
        }

        return null;
    }

    public function saveValue(string $player, int $id, ?callable $onSuccess = null): void
    {
        $columnName = $this->getColumnNames()[$id];

        $value = $this->getValue($player, $id);

        if ($this->getDataTypes()[$id] === self::ARRAY) {
            try {
                $value = json_encode($value ?? "", JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                $value = '';
            }
        }

        $this->onSaved[$player] = true;
        Database::executeInsertRaw('UPDATE player_data SET ' . $columnName . ' = ? WHERE player = ?;', [$value, $player], function (int $insertId, int $affectedRows) use ($onSuccess, $player): void {
            unset($this->onSaved[$player]);

            if (isset($this->onCompletions[$player])) {
                foreach ($this->onCompletions[$player] as $callback) {
                    $callback();
                }
            }

            unset($this->onCompletions[$player]);

            if ($onSuccess !== null) {
                $onSuccess($insertId, $affectedRows);
            }
        });
        if ($id < self::$offset) {
            $this->unsetValue($player, $id);
        }

        unset($this->toBeSaved[$player][$id]);
    }

    /**
     * @param Player|string $player
     * @param int $id
     */
    public function unsetValue($player, int $id = -PHP_INT_MAX): void
    {
        if ($player instanceof Player) {
            $player = $player->getName();
        }

        if ($id === -PHP_INT_MAX) {
            unset($this->playerData[$player]);
        } else {
            unset($this->playerData[$player][$id]);
        }
    }

    /**
     * @param Player|string $player
     * @param int $id
     * @return string
     */
    public function getString($player, int $id): string
    {
        return (string)$this->getValue($player, $id);
    }

    public function getData(Player $player): ?array
    {
        /** @var MMOPlayer $player */
        if ($this->getBool($player, self::DATA_LOADED)) {
            $array = $this->playerData[$player->getName()];

            $vaults = [];
            foreach ($array as $id => $element) {
                if ($id < self::$offset) {
                    if ($id === self::RUNTIME_PRIVATE_VAULTS) {
                        /** @var VaultEntry $vault */
                        foreach ($element as $vault) {
                            $vault->doCloseInventory();

                            $vaults[$vault->vaultId] = $vault->fastSerialize();
                        }
                    }

                    unset($array[$id]);
                }
            }

            $array[self::PLAYER_INVENTORY] = base64_encode($player->saveInventory());
            $array[self::XP] = $player->getXpManager()->getCurrentTotalXp();
            $array[self::PROGRESS] = $this->getPlugin()->getPlayerChallengeManager()->getPlayersChallengesAsArray($player);
            $array[self::PRIVATE_VAULTS_FASTCOPY] = $vaults;

            return $array;
        }

        return null;
    }

    /**
     * @param Player|string $player
     * @param int $id
     * @return float
     */
    public function getFloat($player, int $id): float
    {
        return (float)$this->getValue($player, $id);
    }

    public function saveData(Player $player, bool $clean = false): void
    {
        if ($this->getBool($player, self::DATA_LOADED)) {
            $event = new PlayerDataSaveEvent($player);
            $event->call();

            /** @var MMOPlayer $player */
            $this->saveMySQL($player);
        }

        if ($clean) {
            $this->unsetValue($player);
        }
    }

    public function saveMySQL(MMOPlayer $player): void
    {
        $values = [
            $player->saveInventory(),
            $player->getXpManager()->getCurrentTotalXp()
        ];
        $columns = [
            'inventory = ?',
            'xp = ?'
        ];

        /** @var VaultEntry $vault */
        foreach ($this->getValue($player, self::RUNTIME_PRIVATE_VAULTS) as $vault) {
            $vault->doCloseInventory();
        }

        if (isset($this->toBeSaved[$player->getName()])) {
            foreach ($this->toBeSaved[$player->getName()] as $id => $value) {
                $value = $this->getValue($player, $id);

                if ($this->getDataTypes()[$id] === self::ARRAY) {
                    try {
                        $value = json_encode($value ?? "", JSON_THROW_ON_ERROR);
                    } catch (JsonException $e) {
                        $value = '';
                    }
                }

                if ($this->getDataTypes()[$id] === self::BOOL) {
                    $value = $value ? 1 : 0;
                }

                $columnName = $this->getColumnNames()[$id];

                $values[] = $value;
                $columns[] = $columnName . ' = ?';
            }
        }

        $values[] = $player->getXuid();

        $this->onSaved[$playerName = $player->getName()] = true;
        Database::executeChangeRaw('UPDATE player_data SET ' . implode(', ', $columns) . ' WHERE xuid = ?', $values, function () use ($playerName): void {
            unset($this->onSaved[$playerName]);

            if (isset($this->onCompletions[$playerName])) {
                foreach ($this->onCompletions[$playerName] as $callback) {
                    $callback();
                }
            }

            unset($this->onCompletions[$playerName]);
        });

        unset($this->toBeSaved[$player->getName()]);
    }

    /**
     * This will return true if the player's data is being saved into database.
     * Do not execute any setValues() during this statement.
     *
     * @param string $playerName
     * @return bool
     */
    public function isBeingSaved(string $playerName): bool
    {
        return $this->onSaved[$playerName] ?? false;
    }

    /**
     * Push a callback into an array if the data is not being saved. If player's data
     * is not being saved, it will call the callback immediately.
     */
    public function addCallbackToPlayer(string $playerName, Closure $callback, bool $ignoreUnsavedData = false): void
    {
        if (!$this->isBeingSaved($playerName) && !$ignoreUnsavedData) {
            $callback();
        } else {
            $this->onCompletions[$playerName][] = $callback;
        }
    }

    /**
     * @param string $player
     * @param int $id
     * @param callable $onSuccess function(mixed $value);
     */
    public function loadValue(string $player, int $id, callable $onSuccess): void
    {
        if (isset($this->getColumnNames()[$id])) {
            $columnName = $this->getColumnNames()[$id];
            Database::executeSelectRaw('SELECT ' . $columnName . ' FROM player_data WHERE player = ?;', [$player], function (array $rows) use ($id, $columnName, $onSuccess, $player) {
                if (count($rows) > 0) {
                    if ($id === PlayerData::BANK_MONEY && ($p = $this->getPlugin()->getServer()->getPlayerExact($player)) !== null) {
                        $this->getPlugin()->getPlayerManager()->updateBankScoreboard($p, $rows[0][$columnName]);
                    }

                    if ($this->getDataTypes()[$id] === self::ARRAY) {
                        try {
                            $onSuccess(json_decode($rows[0][$columnName] ?? "", true, 512, JSON_THROW_ON_ERROR));
                        } catch (JsonException $exception) {
                            $onSuccess([]);
                        }
                    } else {
                        $onSuccess($rows[0][$columnName] ?? $this->getDefaultValue($id));
                    }
                }
            });
        }
    }

    /**
     * @param Player|string $player
     * @param int $id
     * @param int $addon
     * @param bool $forceSave
     * @return int
     */
    public function addInt($player, int $id, int $addon = 1, bool $forceSave = false): int
    {
        $int = $this->getInt($player, $id) + $addon;

        $this->setValue($player, $id, $int, $forceSave);

        return $int;
    }

    /**
     * @param Player|string $player
     * @param int $id
     * @return int
     */
    public function getInt($player, int $id): int
    {
        return (int)$this->getValue($player, $id);
    }

    public function getKeys(Player $player, int $keyType): int
    {
        return $this->getArray($player, self::CRATE_KEYS)[$keyType] ?? 0;
    }

    /**
     * @param Player|string $player
     * @param int $id
     * @return array
     */
    public function getArray($player, int $id): array
    {
        return (array)$this->getValue($player, $id);
    }

    public function reduceKey(Player $player, int $keyType, int $size = 1): void
    {
        $playerData = $this->getPlugin()->getPlayerData();
        $keys = $playerData->getArray($player, self::CRATE_KEYS);

        $keys[$keyType] = max(0, $keys[$keyType] - $size);
        if ($keys[$keyType] === 0) {
            unset($keys[$keyType]);
        }

        $playerData->setValue($player, self::CRATE_KEYS, $keys, true);
    }

    public function increaseKey(Player $player, int $keyType, int $amount = 1): void
    {
        $playerData = $this->getPlugin()->getPlayerData();
        $keys = $playerData->getArray($player, self::CRATE_KEYS);

        if (isset($keys[$keyType])) {
            $keys[$keyType] += $amount;
        } else {
            $keys[$keyType] = $amount;
        }

        $playerData->setValue($player, self::CRATE_KEYS, $keys, true);
    }

    public function isFormBlocked(Player|string $player): bool
    {
        return $this->getBool($player, self::FORM_BLOCKED);
    }

    /**
     * @return mixed
     */
    public function getValue(Player|string $player, int $id)
    {
        if ($player instanceof Player) {
            if (!isset($this->playerData[$player->getName()][$id])) {
                $this->playerData[$player->getName()][$id] = $this->getDefaultValue($id);
            }

            $player = $player->getName();
        }

        if (!isset($this->playerData[$player][$id])) {
            $this->playerData[$player][$id] = $this->getDefaultValue($id);
        }

        return $this->playerData[$player][$id];
    }
}