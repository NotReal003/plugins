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

namespace libMMO\crates\loottables;

class CrateLootTable
{
    /** @var string */
    private string $name;
    /** @var int */
    private int $keyDataType;

    /**
     * @var CrateLootTableEntry[]
     * @phpstan-var array<string, CrateLootTableEntry> $rewards
     */
    private array $rewards = [];
    /** @var float */
    private float $totalChance = 0.0;

    /**
     * CrateLootTable constructor.
     *
     * @param string $name
     * @param int $keyDataType
     * @param CrateLootTableEntry[] $entries
     *
     */
    public function __construct(string $name, int $keyDataType, array $entries)
    {
        $this->name = $name;
        $this->keyDataType = $keyDataType;

        foreach ($entries as $entry) {
            $this->totalChance += $entry->getChance();
            $this->rewards[(string)$this->totalChance] = $entry;
        }
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getKeyDataType(): int
    {
        return $this->keyDataType;
    }

    /**
     * @return CrateLootTableEntry[]
     * @phpstan-return array<string, CrateLootTableEntry>
     */
    public function getEntries(): array
    {
        return $this->rewards;
    }

    public function randomEntry(): ?CrateLootTableEntry
    {
        $random = round(mt_rand() / mt_getrandmax() * $this->totalChance, 2);
        foreach ($this->rewards as $chance => $reward) {
            if ($random <= (float)$chance) {
                return $reward;
            }
        }

        return null;
    }
}