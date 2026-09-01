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

namespace skyblock\islands\feature\block;

use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;

final class CobbleGenWeightTable
{
    /** @var Block[] */
    private array $table;

    /**
     * @param BlockToWeightPair[] $substitutes
     */
    public function __construct(array $substitutes)
    {
        $substituteWeight = 0;
        $this->table = [];
        foreach ($substitutes as $substitute) {
            $substituteWeight += $substitute->getWeight();
            if ($substituteWeight > 100) {
                throw new InvalidArgumentException('Substitute blocks must have a total probability weight less than 100');
            }
        }
        foreach ($substitutes as $substitute) {
            for ($i = 0; $i < $substitute->getWeight(); ++$i) {
                $this->table[] = $substitute->getBlock();
            }
        }
        $cobblestone = VanillaBlocks::COBBLESTONE();
        for ($size = count($this->table); $size < 100; ++$size) {
            $this->table[] = $cobblestone;
        }
    }

    /**
     * @return Block[]
     */
    public function getTable(): array
    {
        return $this->table;
    }

    public function pickBlock(): Block
    {
        return clone $this->table[array_rand($this->table)];
    }
}