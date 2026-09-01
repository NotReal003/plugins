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
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew
 *
 */

namespace skyblock\block;

use pocketmine\block\tile\Spawnable;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\Server;
use pocketmine\world\World;

/**
 * Note: this doesn't implement a vanilla spawner, and it IS NOT saved as one.
 * Instead, it has its own save ID, and just masquerades as a vanilla spawner on the network.
 * This allows greater flexibility for customisation.
 */
final class SpawnerTile extends Spawnable
{
    /** @var SpawnerLevel */
    private SpawnerLevel $spawnerLevel;
    /** @var int */
    private int $spawnInterval = 15;

    public function __construct(World $world, Vector3 $pos)
    {
        $this->spawnerLevel = SpawnerLevel::default();
        parent::__construct($world, $pos);
        //this avoids having to make yet another error-prone timer mechanism, just use block scheduled updates instead
        $world->scheduleDelayedBlockUpdate($this->getPosition()->asVector3(), $this->spawnInterval * 20);
    }

    public function getSpawnerLevel(): SpawnerLevel
    {
        return $this->spawnerLevel;
    }

    public function setSpawnerLevel(SpawnerLevel $spawnerLevel): void
    {
        $this->spawnerLevel = $spawnerLevel;
        $this->setDirty();
    }

    public function getSpawnInterval(): int
    {
        return $this->spawnInterval;
    }

    public function setSpawnInterval(int $spawnInterval): void
    {
        $this->spawnInterval = $spawnInterval;
        $this->setDirty();
    }

    public function addAdditionalSpawnData(CompoundTag $nbt, TypeConverter $typeConverter): void
    {
        //forcibly overwrite the network ID, PM doesn't allow us to have different network and disk IDs right now :(
        $nbt->setString(self::TAG_ID, 'MobSpawner');
        $nbt->setString('EntityIdentifier', $this->spawnerLevel->getEntityNetworkId());
        $nbt->setFloat('DisplayEntityScale', 1);
    }

    public function readSaveData(CompoundTag $nbt): void
    {
        $level = SpawnerLevel::get($nbt->getInt('NGSpawnerLevel', 1));
        if ($level === null) {
            Server::getInstance()->getLogger()->error('NG Spawner has invalid spawner level, changing to default level 1');
            $level = SpawnerLevel::default();
        }
        $this->spawnerLevel = $level;
        $this->spawnInterval = $nbt->getInt('SpawnInterval', 15);
    }

    protected function writeSaveData(CompoundTag $nbt): void
    {
        $nbt->setInt('NGSpawnerLevel', $this->spawnerLevel->getId());
        $nbt->setInt('SpawnInterval', $this->spawnInterval);
    }
}