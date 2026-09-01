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

namespace libMMO\aggressiveoptz\helper\world;

use libMMO\aggressiveoptz\AggressiveOptzAPI;
use pocketmine\event\EventPriority;
use pocketmine\event\world\ChunkLoadEvent;
use pocketmine\event\world\ChunkUnloadEvent;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\world\World;
use function array_key_exists;

final class AggressiveOptzWorldCacheManager
{

    /** @var AggressiveOptzWorldCache[] */
    private array $worlds = [];

    public function __construct()
    {
    }

    public function init(AggressiveOptzAPI $api): void
    {
        $api->registerEvent(function (WorldLoadEvent $event): void {
            $this->onWorldLoad($event->getWorld());
        }, EventPriority::LOWEST);
        $api->registerEvent(function (WorldUnloadEvent $event): void {
            $this->onWorldUnload($event->getWorld());
        }, EventPriority::MONITOR);
        $api->registerEvent(function (ChunkLoadEvent $event): void {
            $this->onChunkLoad($event->getWorld(), $event->getChunkX(), $event->getChunkZ());
        }, EventPriority::LOWEST);
        $api->registerEvent(function (ChunkUnloadEvent $event): void {
            $this->onChunkUnLoad($event->getWorld(), $event->getChunkX(), $event->getChunkZ());
        }, EventPriority::MONITOR);

        foreach ($api->getServer()->getWorldManager()->getWorlds() as $world) {
            $this->onWorldLoad($world);
        }
    }

    public function get(World $world): ?AggressiveOptzWorldCache
    {
        return $this->worlds[$world->getId()] ?? null;
    }

    private function onWorldLoad(World $world): void
    {
        $this->worlds[$world->getId()] = new AggressiveOptzWorldCache($world);
    }

    private function onWorldUnload(World $world): void
    {
        unset($this->worlds[$world->getId()]);
    }

    private function onChunkLoad(World $world, int $x, int $z): void
    {
        if (!isset($this->worlds[$world->getId()])) {
            $this->onWorldLoad($world);
        }

        $this->worlds[$world->getId()]->onChunkLoad($x, $z);
    }

    private function onChunkUnload(World $world, int $x, int $z): void
    {
        if (array_key_exists($id = $world->getId(), $this->worlds)) {
            $this->worlds[$id]->onChunkUnload($x, $z);
        }
    }
}