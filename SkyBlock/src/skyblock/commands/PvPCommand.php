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
use NetherGames\NGEssentials\player\PlayerData as NGPlayerData;
use NetherGames\NGEssentials\player\PlayerStats;
use pocketmine\entity\utils\ExperienceUtils;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;
use pocketmine\world\Position;
use pocketmine\world\World;
use skyblock\SkyBlock;

class PvPCommand extends BaseCommand
{
    private const MIN_XP_LEVEL = 30;
    private const MIN_PLAY_TIME = 24; # hours

    public function __construct(SkyBlock $plugin)
    {
        parent::__construct('pvp', $plugin);

        $this->setDescription('Teleport to the PvP arena');
    }

    public static function checkPvPAllowed(Player $player, callable $callback): void
    {
        Utils::validateCallableSignature(function (?string $reason): void {}, $callback);

        $xpLevel = NGEssentials::getInstance()->getPlayerData()->getInt($player, NGPlayerData::XP);

        if (ExperienceUtils::getLevelFromXp($xpLevel) < self::MIN_XP_LEVEL) {
            $callback(TextFormat::RED . 'You must have a network level of ' . self::MIN_XP_LEVEL . ' (from NetherGames minigames) in order to play in this arena!');
            return;
        }

        PlayerStats::getOnlineTime($player, PlayerStats::TYPE_GLOBAL, static function (int $time) use ($callback): void {
            if ($time < self::MIN_PLAY_TIME * 60) {
                $callback(TextFormat::RED . 'You must have played for at least ' . self::MIN_PLAY_TIME . ' hours in order to play in this arena!');
            } else {
                $callback(null);
            }
        });
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        if ($sender->isCombatTimerActive()) {
            $sender->sendMessage(TextFormat::RED . "You can't teleport while you are in combat.");

            return false;
        }

        $plugin = $this->getOwningPlugin();
        if ($sender->getWorld()->getFolderName() === 'pvp') {
            $plugin->getScheduler()->scheduleRepeatingTask(new class($sender) extends Task {
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
                            $player->teleport(NGEssentials::getInstance()->getServerManager()->getSpawn());
                            $this->getHandler()->cancel();
                        }
                    } else {
                        $this->getHandler()->cancel();
                    }
                }
            }, 20);
        } else {
            self::checkPvPAllowed($sender, function (?string $reason) use ($sender): void {
                if (!$sender->isConnected()) {
                    return;
                }

                if ($reason === null) {
                    $pos = $this->getPosition();
                    $sender->teleport(Position::fromObject($pos->add(.5, 0, .5), $pos->getWorld()));
                } else {
                    $sender->sendMessage(SkyBlock::getPrefix() . $reason);
                }
            });
        }

        return true;
    }

    public function getPosition(): Position
    {
        /** @var World $world */
        $world = $this->getOwningPlugin()->getServer()->getWorldManager()->getWorldByName('pvp');

        return $world->getSpawnLocation();
    }
}