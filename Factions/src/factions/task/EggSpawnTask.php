<?php
/**
 *        ______         _   _
 *       |  ____|       | | (_)
 *  __  _| |__ __ _  ___| |_ _  ___  _ __  ___
 *  \ \/ /  __/ _` |/ __| __| |/ _ \| '_ \/ __|
 *   >  <| | | (_| | (__| |_| | (_) | | | \__ \
 *  /_/\_\_|  \__,_|\___|\__|_|\___/|_| |_|___/
 *
 * Copyright (C) 2016-2021 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author larryTheCoder
 */

declare(strict_types=1);

namespace factions\task;

use factions\block\DragonEgg;
use factions\Factions;
use factions\utils\Area;
use Generator;
use pocketmine\math\Vector3;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use SOFe\AwaitGenerator\Await;

class EggSpawnTask extends Task
{
    public function onRun(): void
    {
        Await::f2c(function (): Generator {
            $wm = Server::getInstance()->getWorldManager();
            $world = $wm->getWorldByName('wild');

            requestNew:

            $x = $world->getSpawnLocation()->getFloorX() + mt_rand(-23500, 23500);
            $z = $world->getSpawnLocation()->getFloorZ() + mt_rand(-23500, 23500);

            if (Area::isAreaInside(Position::fromObject(new Vector3($x, 0, $z), $world))) {

                Factions::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(yield), 1);
                yield Await::ONCE;

                goto requestNew;
            }

            $attempts = 0;

            requestChunk:
            $world->requestChunkPopulation($x >> 4, $z >> 4, null)->onCompletion(yield, static function () {});

            yield Await::ONCE;

            if ($attempts > 5) {

                Factions::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(yield), 1);
                yield Await::ONCE;

                goto requestNew;
            }


            if (($highGrounds = $world->getHighestBlockAt($x, $z)) === null) {
                ++$attempts;

                goto requestChunk;
            }

            if (!$world->getBlock(new Vector3($x, $highGrounds, $z))->isSolid()) {
                $x += mt_rand(-20, 20);
                $z += mt_rand(-20, 20);

                ++$attempts;

                Factions::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(yield), 1);
                yield Await::ONCE;

                goto requestChunk;
            }

            if (!$world->isInWorld($x, $highGrounds + 1, $z)) {
                goto requestNew;
            }

            $position = new Position($x, $highGrounds + 1, $z, $world);

            $world->setBlock($position, new DragonEgg(), false);
            Server::getInstance()->broadcastMessage("\n\n" .
                TextFormat::RED . TextFormat::BOLD . "** " .
                TextFormat::RESET . TextFormat::RED . "An egg has dropped at x: {$position->getFloorX()} | z: {$position->getFloorZ()} " .
                TextFormat::RED . TextFormat::BOLD . "**" . "\n" .
                TextFormat::RESET . TextFormat::RED . "Find it for a chance to fight a boss!" . "\n\n");
        });
    }
}