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

namespace libMMO;

use libMMO\utils\BaseClass;
use NetherGames\NGEssentials\utils\scoreboard\Scoreboard;
use function is_object;

abstract class ServerData extends BaseClass
{
    //DATA TYPES
    public const ARRAY = 0;
    public const BOOL = 1;
    public const FLOAT = 2;
    public const INT = 3;
    public const OBJECT = 4;
    public const STRING = 5;

    /** @var array */
    private array $serverData = [];

    public function getFloat(int $id): float
    {
        return (float)$this->getValue($id);
    }

    /**
     * @param int $id
     * @return array|bool|int|Scoreboard|string|null
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

    public function getDataTypes(): array
    {
        return [

        ];
    }

    public function getString(int $id): string
    {
        return (string)$this->getValue($id);
    }

    /**
     * @param int $id
     * @return bool
     */
    public function getBool(int $id): bool
    {
        return (bool)$this->getValue($id);
    }

    public function addInt(int $id, int $addon = 1): int
    {
        $int = $this->getInt($id) + $addon;

        $this->setValue($id, $int);

        return $int;
    }

    public function getInt(int $id): int
    {
        return (int)$this->getValue($id);
    }

    /**
     * @param int $id
     * @param mixed $value
     */
    public function setValue(int $id, $value): void
    {
        if (($validatedValue = $this->validateValue($id, $value)) === null) {
            $this->getPlugin()->getLogger()->alert('Invalid datatype for id: ' . $id . '| value: ' . (string)$value);
        } else {
            $this->serverData[$id] = $validatedValue;
        }
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

    public function unsetValue(int $id): void
    {
        unset($this->serverData[$id]);
    }

    public function getArray(int $id): array
    {
        return (array)$this->getValue($id);
    }

    /**
     * @param int $id
     * @return mixed
     */
    private function getValue(int $id)
    {
        if (!isset($this->serverData[$id])) {
            $this->serverData[$id] = $this->getDefaultValue($id);
        }

        return $this->serverData[$id];
    }
}