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

use factions\Factions;
use factions\task\teleport\TeleportTask;
use Generator;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use SOFe\AwaitGenerator\Await;

class WarzoneCommand extends BaseCommand
{

    public function __construct(MMOPlugin $owningPlugin)
    {
        parent::__construct('warzone', $owningPlugin);
        $this->setAliases(['wz']);

        $this->setDescription('Teleport to the warzone location');
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
                $spawn = Factions::getSpawnLocation();

                $wild->requestChunkPopulation($spawn->getFloorX() >> 4, $spawn->getFloorZ() >> 4, null)->onCompletion(yield, function (): void {});

                yield Await::ONCE;

                $task = new TeleportTask($sender, Position::fromObject($spawn, $wild));
                $this->getOwningPlugin()->getScheduler()->scheduleRepeatingTask($task, 20);
            });
        }

        return true;
    }
}