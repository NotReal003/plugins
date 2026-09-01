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
declare(strict_types=1);

namespace skyblock\entities\helpers;

use pocketmine\entity\Entity;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;
use skyblock\SkyBlock;
use skyblock\utils\BaseClass;

class HelperManager extends BaseClass
{
    public function __construct(SkyBlock $plugin)
    {
        parent::__construct($plugin);

        $entityFactory = EntityFactory::getInstance();

        $entityFactory->register(MiniHarvester::class, function (World $world, CompoundTag $nbt): MiniHarvester {
            return new MiniHarvester(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, [self::fromJobType(MiniHelper::HARVESTER)]);
        $entityFactory->register(MiniLumberjack::class, function (World $world, CompoundTag $nbt): MiniLumberjack {
            return new MiniLumberjack(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, [self::fromJobType(MiniHelper::LUMBERJACK)]);
        $entityFactory->register(MiniMiner::class, function (World $world, CompoundTag $nbt): MiniMiner {
            return new MiniMiner(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, [self::fromJobType(MiniHelper::MINER)]);
    }

    public static function fromJobType(int $jobType): string
    {
        switch ($jobType) {
            case MiniHelper::LUMBERJACK:
                return 'Lumberjack';
            case MiniHelper::HARVESTER:
                return 'Harvester';
            default:
                return 'Miner';
        }
    }

    /**
     * @param int $jobType
     * @param Location $location
     * @param CompoundTag $nbt
     * @param mixed ...$args
     * @return Entity
     */
    public static function getEntityFromJobType(int $jobType, Location $location, CompoundTag $nbt, ...$args): Entity
    {
        switch ($jobType) {
            case MiniHelper::LUMBERJACK:
                return new MiniLumberjack($location, $nbt, ...$args);
            case MiniHelper::HARVESTER:
                return new MiniHarvester($location, $nbt, ...$args);
            default:
                return new MiniMiner($location, $nbt, ...$args);
        }
    }
}