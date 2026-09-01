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

namespace factions\koth\task;

use factions\koth\Koth;
use factions\utils\Area;
use libminigames\Arena;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;

class KothGameTask extends Task
{
    /** @var Koth */
    private Koth $arena;
    /** @var int */
    private int $time = 2 * 60 * 20;

    public function __construct(Koth $koth)
    {
        $this->arena = $koth;
        $this->arena->setStatus(Arena::STATUS_RUNNING);
    }

    public function onRun(): void
    {
        if (!$this->arena->isKothRunning()) {
            $this->getHandler()->cancel();
            return;
        }

        $this->time--;
        if ($this->time <= 0 || count($this->arena->getPlayers()) <= 1) {
            $this->arena->endMatch();
            $this->getHandler()->cancel();
        } else {
            foreach ($this->arena->getPlayers() as $player) {
                $player->setHealth(20);
                if ($player->isOnFire()) {
                    $player->extinguish();
                }

                $percent = floor(($this->arena->getCaptureProgress($player) / Koth::OBJECTIVE_CAPTURE_TIME) * 100);
                if ($percent >= 100) {
                    $this->arena->setWinner($player);
                    $this->arena->endMatch();

                    $this->getHandler()->cancel();
                    break;
                } elseif (Area::inKothArea($player)) {
                    $this->arena->addToCaptureProgress($player);
                    $this->arena->showKOTHBossBar($player);

                    $player->sendPopup(TextFormat::GREEN . TextFormat::BOLD . 'Capturing...');
                }
            }
        }
    }

}