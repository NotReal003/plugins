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

namespace factions\task\teleport;

use factions\Factions;
use factions\utils\Area;
use libMMO\MMOPlugin;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\world\sound\EndermanTeleportSound;

class TeleportTask extends Task
{
    /** @var true[] */
    public static array $teleportList = [];

    /** @var Player */
    protected Player $player;
    /** @var int */
    private int $countdown = 5;
    /** @var Position|null */
    private ?Position $targetPosition;
    /** @var Position */
    private Position $lastPosition;

    /** @var bool */
    protected bool $sendTeleportInfo = true;
    /** @var bool */
    private bool $teleportImmediately;

    /**
     * @param Player $player
     * @param Position|null $position
     */
    public function __construct(Player $player, ?Position $position = null)
    {
        $this->player = $player;
        $this->lastPosition = $player->getPosition();
        $this->targetPosition = $position;
        $this->teleportImmediately = !Area::inPvpArea($player);

        self::$teleportList[$player->getName()] = true;
    }

    public function onRun(): void
    {
        $player = $this->player;
        if (!$player->isOnline()) {
            $this->getHandler()->cancel();
            return;
        }

        // Teleport immediately if the player is not in badlands nor wilderness.
        if ($this->teleportImmediately) {
            $this->teleportToTarget();
        } else {
            // Let's give some player a bit of flexibility, they can turn their heads and move around
            // that is not > 0.5 blocks away.
            if ($player->getPosition()->distance($this->lastPosition) > 0.5) {
                $player->sendTitle(MMOPlugin::getPrefix() . TextFormat::RED . 'Failed', 'You must stay still!');
            } else if ($this->countdown >= 1) {
                $player->sendTitle(MMOPlugin::getPrefix() . TextFormat::YELLOW . 'Stay still', "Teleporting in $this->countdown seconds...");
                $this->countdown--;

                return;
            } else {
                $this->teleportToTarget();
            }
        }

        $this->getHandler()->cancel();
    }

    public function teleportToTarget(): void
    {
        $position = $this->targetPosition;
        if ($position->getWorld()->getOrLoadChunkAtPosition($position) === null) {
            $position->getWorld()->requestChunkPopulation($position->getFloorX() >> 4, $position->getFloorZ() >> 4, null)->onCompletion(function () use ($position): void {
                $this->player->teleport($position);

                if ($this->sendTeleportInfo) {
                    $this->player->sendMessage(MMOPlugin::getPrefix() . 'You have been successfully teleported.');
                    $this->player->getWorld()->addSound($this->player->getPosition(), new EndermanTeleportSound(), [$this->player]);
                }
            }, static function () {});
        } else {
            $this->player->teleport($position);

            if ($this->sendTeleportInfo) {
                $this->player->sendMessage(MMOPlugin::getPrefix() . 'You have been successfully teleported.');
                $this->player->getWorld()->addSound($this->player->getPosition(), new EndermanTeleportSound(), [$this->player]);
            }
        }
    }

    public function onCancel(): void
    {
        unset(self::$teleportList[$this->player->getName()]);
    }
}