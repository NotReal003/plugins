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

use libMMO\aggressiveoptz\AggressiveOptzAPI;
use Logger;
use LogicException;
use PrefixedLogger;
use function array_key_exists;

final class OptimizationComponentManager
{

    /** @var AggressiveOptzAPI */
    private AggressiveOptzAPI $api;
    /** @var Logger */
    private $logger;
    /** @var OptimizationComponent[] */
    private array $enabled = [];

    public function __construct(AggressiveOptzAPI $api)
    {
        $this->api = $api;
        $this->logger = new PrefixedLogger($api->getLogger(), "OC-Manager");
    }

    /**
     * @param string $identifier
     */
    public function enable(string $identifier): void
    {
        if ($this->isEnabled($identifier)) {
            throw new LogicException("Tried to enable an already enabled component: {$identifier}");
        }

        $this->enabled[$identifier] = $this->api->getComponentFactory()->build($identifier);
        $this->enabled[$identifier]->enable($this->api);
        $this->logger->debug("Enabled component: {$identifier}");
    }

    public function isEnabled(string $identifier): bool
    {
        return array_key_exists($identifier, $this->enabled);
    }

    public function disable(string $identifier): void
    {
        if (!$this->isEnabled($identifier)) {
            throw new LogicException("Tried to disable an already disabled component: {$identifier}");
        }

        $this->enabled[$identifier]->disable($this->api);
        unset($this->enabled[$identifier]);
        $this->logger->debug("Disabled component: {$identifier}");
    }
}