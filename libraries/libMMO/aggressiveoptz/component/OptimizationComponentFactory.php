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

namespace libMMO\aggressiveoptz\component;

use InvalidArgumentException;
use function array_key_exists;

final class OptimizationComponentFactory
{

    /**
     * @var OptimizationComponent[]
     * @phpstan-var array<string, class-string<OptimizationComponent>>
     */
    private array $registered = [];

    /**
     * @param string $identifier
     * @param string $component
     *
     * @phpstan-param class-string<OptimizationComponent> $component
     */
    public function register(string $identifier, string $component): void
    {
        if ($this->exists(($identifier))) {
            throw new InvalidArgumentException("Tried to override an already existing component with the identifier \"{$identifier}\" ({$this->registered[$identifier]})");
        }

        $this->registered[$identifier] = $component;
    }

    public function exists(string $identifier): bool
    {
        return array_key_exists($identifier, $this->registered);
    }

    /**
     * @param string $identifier
     * @return OptimizationComponent
     */
    public function build(string $identifier): OptimizationComponent
    {
        return new $this->registered[$identifier]();
    }
}