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
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder
 */
declare(strict_types=1);

namespace skyblock\islands\feature\task;

use pocketmine\entity\Location;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;
use skyblock\entities\boss\Boss;
use skyblock\entities\boss\BossManager;
use skyblock\islands\feature\boss\BossLevelup;
use skyblock\islands\IslandManager;

class LevelupTick extends Task
{
    public const SPAWN_POS_BOSS = [100, 4, 128];
    public const DURATION = (15 * 60) + 5; // 15 Minutes Max + Countdown Time
    public const ANNOUNCE_SECONDS = [60 * 14, 60 * 10, 60 * 5];

    /** @var int */
    public int $timer = 0;
    /** @var BossLevelup */
    public BossLevelup $levelup;

    public function __construct(BossLevelup $levelup)
    {
        $this->levelup = $levelup;

        IslandManager::getIslandManager()->getIslandLevelManager()->addLevelUp($levelup);
    }

    public function onRun(): void
    {
        $levelup = $this->levelup;
        $world = $levelup->getWorld();

        if ($this->timer === 0) {
            foreach ($levelup->getParticipants() as $participant) {
                $participant->teleport($world->getSafeSpawn());
            }
        } elseif ($this->timer < 5) {
            foreach ($world->getPlayers() as $player) {
                $player->sendMessage(TextFormat::YELLOW . 'The fight starts in ' . TextFOrmat::RED . (5 - $this->timer) . TextFormat::YELLOW . ' seconds!');
            }
        } elseif ($this->timer === 5) {
            [$x, $y, $z] = self::SPAWN_POS_BOSS;
            $level = $levelup->getIsland()->getXpLevel();
            /** @var Boss $boss */
            $boss = BossManager::spawnBoss(new Location($x, $y, $z, $levelup->getWorld(), 0, 0), $this->getAccordingBoss($level));
            $boss->setBossLevel($levelup->rateBossLevel());
            if ($level <= 3) {
                $boss->setMaxHealth(25 * $level);
                $boss->setHealth($boss->getMaxHealth());
                $boss->setDamage(4); // Default Damage of 4 for levels below or equal to 3 for non-premium players
            }
            $boss->spawnToAll();

            $levelup->setBossEntity($boss);
        } elseif (in_array($this->timer, self::ANNOUNCE_SECONDS, true)) {
            foreach ($world->getPlayers() as $player) {
                $player->sendMessage(TextFormat::YELLOW . 'The fight ends in ' . TextFOrmat::RED . ((self::DURATION - $this->timer) / 60) . TextFormat::YELLOW . ' minutes!');
            }
        }

        if ($levelup->playersAlive === 0) {
            foreach ($levelup->getParticipants() as $player) {
                // Notify each participant that the fight was lost.
                if ($player->isConnected()) {
                    $player->sendMessage(TextFormat::RED . 'You lost the battle!');
                    $player->sendTitle(TextFormat::BOLD . TextFormat::RED . 'GAME OVER!', TextFormat::GRAY . 'You lost the battle!');
                }
            }

            $levelup->finish();
        } else {
            $this->timer++;
        }
    }

    public function getAccordingBoss(int $level): int
    {
        if ($level % 3 === 0) {
            return Boss::MEDUSA;
        }

        if ($level % 2 === 0) {
            return Boss::BIG_FOOT;
        }

        return Boss::DESERTER;
    }
}