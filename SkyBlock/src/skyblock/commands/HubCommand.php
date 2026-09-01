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

namespace skyblock\commands;

use libMMO\player\MMOPlayer;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\player\permissions\Permissions;
use NetherGames\NGEssentials\ServerManager;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use skyblock\SkyBlock;
use function str_starts_with;
use function strpos;

class HubCommand extends BaseCommand
{
    public function __construct(SkyBlock $plugin)
    {
        parent::__construct('hub', $plugin);

        $this->setPermission(Permissions::DEFAULT_COMMAND_PERMISSION);
        $this->setDescription('Command used for transferring to Lobby server');
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        if ($sender->isCombatTimerActive()) {
            $sender->sendMessage(TextFormat::RED . "You can't teleport while you are in combat.");
            return false;
        }

        if ($this->getOwningPlugin()->isAgora()) {
            if ($sender->getWorld()->getFolderName() === 'pvp') {
                $this->getOwningPlugin()->getScheduler()->scheduleRepeatingTask(new class($sender) extends Task {
                    /** @var Player */
                    private Player $player;
                    /** @var Position */
                    private Position $position;
                    /** @var int */
                    private int $time = 5;

                    public function __construct(Player $player)
                    {
                        $this->player = $player;
                        $this->position = $player->getPosition();
                    }

                    public function onRun(): void
                    {
                        $player = $this->player;

                        if ($player->isConnected()) {
                            if ($this->position->distance($player->getPosition()) > 1) {
                                $player->sendTitle(TextFormat::BOLD . TextFormat::DARK_GRAY . '(' . TextFormat::GOLD . '!' . TextFormat::DARK_GRAY . ') ' . TextFormat::RESET . TextFormat::RED . 'Failed', TextFormat::GRAY . 'You must stay still!');
                                $this->getHandler()->cancel();
                            } elseif ($this->time >= 2) {
                                $player->sendTitle(TextFormat::BOLD . TextFormat::DARK_GRAY . '(' . TextFormat::GOLD . '!' . TextFormat::DARK_GRAY . ') ' . TextFormat::RESET . TextFormat::YELLOW . 'Stay still', TextFormat::GRAY . 'Teleporting in ' . $this->time . ' seconds...');
                                $this->time--;
                            } elseif ($this->time === 1) {
                                $player->sendTitle(TextFormat::BOLD . TextFormat::DARK_GRAY . '(' . TextFormat::GOLD . '!' . TextFormat::DARK_GRAY . ') ' . TextFormat::RESET . TextFormat::YELLOW . 'Stay still', TextFormat::GRAY . 'Teleporting in ' . $this->time . ' second...');
                                $this->time--;
                            } else {
                                $ess = NGEssentials::getInstance();
                                $player->teleport($ess->getServerManager()->getSpawn());
                                $ess->getPlayerManager()->transferPlayer($player);
                                $this->getHandler()->cancel();
                            }
                        } else {
                            $this->getHandler()->cancel();
                        }
                    }
                }, 20);
            } else {
                $ess = NGEssentials::getInstance();
                $sender->teleport($ess->getServerManager()->getSpawn());
                $ess->getPlayerManager()->transferPlayer($sender);
            }
        } elseif (str_starts_with($sender->getWorld()->getFolderName(), 'IslandUpgrade-')) {
            $sender->sendMessage(TextFormat::RED . "You can't use this command here!");
        } else {
            $ess = NGEssentials::getInstance();
            $sender->teleport($ess->getServerManager()->getSpawn());
            $ess->getPlayerManager()->transferPlayer($sender);
        }

        return true;
    }
}