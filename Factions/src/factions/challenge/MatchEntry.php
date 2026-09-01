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

namespace factions\challenge;

use factions\player\MMOPlayer;
use libMMO\MMOPlugin;
use pocketmine\entity\Location;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class MatchEntry extends Task
{
    // Added rules:
    // - No bows (Remove ability to use bows in arena)
    // - Reach de-buff (Attack vector will reduce reach efficiency by 8.3% per tick cps)
    // - No enchantments (All custom enchantments are disabled)
    // - No escape (Disable escape enchantment)
    // - Sumo (Just sumo, nothing else).

    // Custom configuration:
    // - Vertical/horizontal kb modifier
    // - Timer modification (Default 5 minutes)
    // - Winner prize for a match (bet)
    // - Play with bots (Solo mode)
    // - Set if the items dropped caused by death is safe from removal.
    //

    private const PLAYER_SPAWN_ARRAY = [
        1 => [[-65, 52, 75, 90], [-85, 52, 75, 270]],
        3 => [[-86, 52, 77, 90], [-86, 52, 73, 90], [-64, 52, 77, 270], [-64, 52, 73, 270]],
    ];
    /** @var int */
    public int $betPlaced = 0;
    /** @var int */
    public int $timer = 0;
    /** @var int */
    private int $currentTime = -5;
    /** @var Player[]|null[] */
    private array $players = [];
    /** @var Player[]|null[] */
    private array $target = [];
    /** @var bool */
    private bool $safeMode = true;
    /** @var int */
    private int $pointer = 0;
    /** @var bool */
    private bool $isSoloMode;
    /** @var Player */
    private Player $request;

    public function __construct(Player $player, bool $isOneVsOne)
    {
        $this->request = $player;
        $this->isSoloMode = $isOneVsOne;

        $this->resetPlayerEntries();
    }

    public function resetPlayerEntries(): void
    {
        $this->players = [];
        $this->target = [];

        $this->players[0] = $this->request;
    }

    public function isPlayerInEntry(Player $player): bool
    {
        foreach (array_merge($this->players, $this->target) as $players) {
            if ($player->getName() === $players->getName()) {
                return true;
            }
        }

        return false;
    }

    public function inverseSoloMode(): void
    {
        $this->safeMode = !$this->safeMode;
    }

    public function getPlayerRequested(): Player
    {
        return $this->request;
    }

    /**
     * @param int $pointer
     * @return string|null
     */
    public function getPlayerByPointer(int $pointer): ?string
    {
        if (!$this->isSoloMode()) {
            if ($pointer === 0) {
                return $this->players[1]?->getName();
            } else if ($pointer === 1) {
                return $this->target[0]?->getName();
            } else if ($pointer === 2) {
                return $this->target[1]?->getName();
            }
        }

        return $this->target[0]?->getName();
    }

    public function isSoloMode(): bool
    {
        return $this->isSoloMode;
    }

    public function setPlayer(?string $playerName, bool $updatePointer = true): bool
    {
        if ($playerName !== null && (($player = Server::getInstance()->getPlayerExact($playerName)) === null || !$player->isConnected())) {
            return false;
        }
        $pointer = $this->getPointer();

        if (!$this->isSoloMode()) {
            if ($pointer === 0) {
                $this->players[1] = $player ?? $playerName;
            } else if ($pointer === 1) {
                $this->target[0] = $player ?? $playerName;
            } else if ($pointer === 2) {
                $this->target[1] = $player ?? $playerName;
            } else {
                return false;
            }
        } else {
            $this->target[0] = $player ?? $playerName;
        }

        if ($updatePointer) {
            $this->setNextPointer();
        }

        return true;
    }

    public function getPointer(): int
    {
        return $this->pointer;
    }

    public function setPointer(int $pointer): void
    {
        $this->pointer = $pointer;
    }

    public function setNextPointer(): void
    {
        $targetId = $this->getPointer();
        foreach ($this->getAllPlayers() as $id => $player) {
            if ($player === null) {
                $targetId = $id;
                break;
            }
        }

        $this->pointer = $targetId;
    }

    public function getAllPlayers(): array
    {
        if (!$this->isSoloMode()) {
            return [$this->players[1]?->getName() ?? null, $this->target[0] ?? null, $this->target[1] ?? null];
        }

        return [$this->target[0] ?? null];
    }

    public function onRun(): void
    {
        if ($this->currentTime === -5) {
            if ($this->isSoloMode()) {
                $player = self::toLocation(self::PLAYER_SPAWN_ARRAY[1][0]);
                $target = self::toLocation(self::PLAYER_SPAWN_ARRAY[1][1]);

                $this->players[0]->teleport($player);
                $this->target[0]->teleport($target);
            } else {
                $player1 = self::toLocation(self::PLAYER_SPAWN_ARRAY[3][0]);
                $player2 = self::toLocation(self::PLAYER_SPAWN_ARRAY[3][1]);
                $target1 = self::toLocation(self::PLAYER_SPAWN_ARRAY[3][2]);
                $target2 = self::toLocation(self::PLAYER_SPAWN_ARRAY[3][3]);

                $this->players[0]->teleport($player1);
                $this->players[1]->teleport($player2);

                $this->target[0]->teleport($target1);
                $this->target[1]->teleport($target2);
            }

            $this->broadcastMessage(TextFormat::RED . "You have been teleported to the arena, the match will begin in 5 seconds.");
        } else if ($this->currentTime < 0) {
            if (!$this->verifyEntries()) {
                $this->broadcastMessage(TextFormat::RED . "A player just left the arena, match has been ended gracefully to avoid unfair gameplay.");
                $this->endMatch();

                $this->getHandler()->cancel();
            } else {
                $this->broadcastTitle(' ', TextFormat::GRAY . 'Match begins in ' . TextFormat::RED . (-$this->currentTime) . TextFormat::GRAY . ' seconds.', 0, 60, 20);
            }
        } else {
            if ($this->currentTime === 0) {
                $this->broadcastTitle(TextFormat::RED . 'Fight!', TextFormat::GRAY . 'The match has begun!', 0, 60, 20);
            } else if (!$this->verifyEntries() && !$this->isSoloMode() && !$this->isSafeMode()) {
                $this->safeMode = true;

                $this->broadcastMessage(TextFormat::RED . "A player just left the arena, safe mode has been enabled to avoid unfair gameplay.");
            }

            if ($this->currentTime >= ($this->timer * 60)) {
                $this->broadcastMessage(TextFormat::RED . "Match has ended, there is no winners this time.");

                $this->endMatch();
            }
        }

        $this->currentTime++;
    }

    private static function toLocation(array $spawnData): Location
    {
        $defaultWorld = Server::getInstance()->getWorldManager()->getDefaultWorld();

        return new Location($spawnData[0] + .5, $spawnData[1], $spawnData[2] + .5, $defaultWorld, $spawnData[3], 0.0);
    }

    public function broadcastMessage(string $message): void
    {
        foreach ($this->getAllPlayers() as $player) {
            $player->sendMessage(MMOPlugin::getPrefix() . $message);
        }
    }

    public function verifyEntries(): bool
    {
        $numValidPlayers = 0;
        foreach (array_merge($this->players, $this->target) as $player) {
            if ($player->isOnline()) {
                $numValidPlayers++;
            }
        }

        return $this->isSoloMode() ? $numValidPlayers === 2 : $numValidPlayers === 4;
    }

    private function endMatch(): void
    {
        foreach ($this->getAllPlayers() as $player) {
            $player->teleport(Server::getInstance()->getWorldManager()->getDefaultWorld()->getSpawnLocation());
        }


    }

    public function broadcastTitle(string $title, string $subtitle = '', int $fadeIn = 0, int $stay = 40, int $fadeOut = 0): void
    {
        foreach ($this->getAllPlayers() as $player) {
            $player->sendTitle($title, $subtitle, $fadeIn, $stay, $fadeOut);
        }
    }

    public function isSafeMode(): bool
    {
        return $this->safeMode;
    }

    public function isMatchAllowed(): bool
    {
        /** @var MMOPlayer $player */
        foreach ($this->getAllPlayers() as $player) {
            if ($player->isCombatTimerActive()) {
                return false;
            }
        }

        return true;
    }
}