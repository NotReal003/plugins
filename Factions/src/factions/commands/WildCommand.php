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

namespace factions\commands;

use factions\task\teleport\TeleportTask;
use Generator;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;

class WildCommand extends BaseCommand
{

    public function __construct(MMOPlugin $owningPlugin)
    {
        parent::__construct('wild', $owningPlugin);

        $this->setDescription('Teleport to a random wilderness location');
    }

    public function executeCommand(Player $sender, string $commandLabel, array $args): bool
    {
        // This will never happen xd
        if (!($sender instanceof MMOPlayer)) {
            $sender->sendMessage('Hello, how are you? Im under the water, please send help huhu.');
        } else if ($sender->isCombatTimerActive()) {
            $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'You cannot teleport while combat tagged.');
        } else if (isset(TeleportTask::$teleportList[$sender->getName()])) {
            $this->sendFailureMessage($sender, 'Please wait for you to be teleported first.');
        } else {
            Await::f2c(function () use ($sender): Generator {
                $wm = Server::getInstance()->getWorldManager();

                $wild = $wm->getWorldByName('wild');
                $spawn = $wild->getSpawnLocation();

                $x = $spawn->getFloorX() + mt_rand(-15000, 15000);
                $z = $spawn->getFloorZ() + mt_rand(-15000, 15000);

                $wild->requestChunkPopulation($x >> 4, $z >> 4, null)->onCompletion(yield, function (): void {});

                yield Await::ONCE;

                $task = new TeleportTask($sender, $wild->getSafeSpawn(new Vector3($x, $wild->getHighestBlockAt($x, $z) + 1, $z)));
                $this->getOwningPlugin()->getScheduler()->scheduleRepeatingTask($task, 20);
            });
        }

        return true;
    }
}