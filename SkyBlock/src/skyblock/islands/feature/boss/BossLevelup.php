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

namespace skyblock\islands\feature\boss;

use libasyncio\FileCopyAsyncTask;
use libMMO\MMOPlugin;
use NetherGames\NGEssentials\thread\NGThreadPool;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Filesystem;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;
use RuntimeException;
use skyblock\entities\boss\Boss;
use skyblock\islands\feature\task\LevelupTick;
use skyblock\islands\Island;
use skyblock\islands\IslandManager;
use skyblock\SkyBlock;
use Symfony\Component\Filesystem\Path;
use function count;

class BossLevelup
{
    /** @var int Every x Players the Boss Level gets increased by 1 */
    public const SCALE_PLAYER = 1;
    /** @var int Every x Island Levels (+ the Level you want to reach) the Boss Level gets increased by 1 */
    public const SCALE_LEVEL = 1;

    /** @var int */
    public int $playersAlive;
    /** @var Player[] */
    private array $participants;
    /** @var Boss|null */
    private ?Boss $bossEntity = null;
    /** @var Island */
    private Island $island;
    /** @var World */
    private World $world;
    /** @var LevelupTick */
    private LevelupTick $task;

    public function __construct(Island $island)
    {
        $this->island = $island;
        $this->participants = $island->getOnlineMembers();
        $this->playersAlive = count($this->participants);

        $plugin = MMOPlugin::getInstance();
        $server = $plugin->getServer();

        $island->setUnloadLock(false);

        NGThreadPool::getInstance()->submitTask(new FileCopyAsyncTask(Path::join(SkyBlock::getInstance()->getServer()->getDataPath(), 'worlds', 'arena'), Path::join($plugin->getServer()->getDataPath(), 'worlds', 'IslandUpgrade-' . $island->getOwner()), function () use ($island, $server, $plugin) {
            $server->getWorldManager()->loadWorld('IslandUpgrade-' . $island->getOwner());

            /** @var World $world */
            $world = $server->getWorldManager()->getWorldByName('IslandUpgrade-' . $island->getOwner());
            $this->world = $world;

            $plugin->getScheduler()->scheduleRepeatingTask(($this->task = new LevelupTick($this)), 20);
        }));
    }

    /**
     * Right now for every 2 Players participating the Level is increased by 1 and
     * for every two levels you try to upgrade
     *
     * @return int
     */
    public function rateBossLevel(): int
    {
        $fightingForLevel = $this->island->getXpLevel();
        $bossLevel = 1;

        if ($fightingForLevel > self::SCALE_LEVEL) {
            $bossLevel += (int)($fightingForLevel / self::SCALE_LEVEL);
        }
        if (($count = count($this->getParticipants())) > self::SCALE_PLAYER) {
            $bossLevel += (int)($count / self::SCALE_PLAYER);
        }

        return $bossLevel;
    }

    /**
     * @return Player[]
     */
    public function getParticipants(): array
    {
        return $this->participants;
    }

    public function handleDone(): void
    {
        $island = $this->getIsland();
        $island->levelUp();

        foreach ($this->getParticipants() as $player) {
            if ($player->isConnected()) {
                $player->sendMessage(TextFormat::GREEN . 'You won the battle!');
                $player->sendTitle(TextFormat::BOLD . TextFormat::GOLD . 'VICTORY!', TextFormat::GRAY . 'You won the battle!');

                if ($island->getOwner() === $player->getName()) {
                    if (count($this->getParticipants()) > 1) {
                        $player->sendMessage(TextFormat::GREEN . "Your island has now been upgraded due to your team's win in the arena!");
                    } else {
                        $player->sendMessage(TextFormat::GREEN . 'Your island has now been upgraded due to your win in the arena!');
                    }
                }
            }
        }

        $this->finish();
    }

    /**
     * @return Island
     */
    public function getIsland(): Island
    {
        return $this->island;
    }

    public function finish(): void
    {
        if ($this->task->getHandler() === null) {
            return;
        }

        $this->task->getHandler()->cancel();

        $island = $this->island;
        $plugin = MMOPlugin::getInstance();

        foreach ($this->getParticipants() as $player) {
            if ($player->isConnected()) {
                $player->setGamemode(GameMode::SURVIVAL);

                $player->setHealth($player->getMaxHealth());
                $player->getHungerManager()->setFood($player->getHungerManager()->getMaxFood());
                $player->extinguish();

                $player->teleport($island->getSpawnPosition());
            }
        }

        IslandManager::getIslandManager()->getIslandLevelManager()->removeLevelup($world = $this->getWorld());

        $plugin->getScheduler()->scheduleTask(new ClosureTask(static function () use ($world, $plugin, $island): void {
            if ($world->isLoaded()) {
                $plugin->getServer()->getWorldManager()->unloadWorld($world);
                try {
                    Filesystem::recursiveUnlink($world->getServer()->getDataPath() . '/worlds/' . $world->getFolderName());
                } catch (RuntimeException $exception) {
                    $plugin->getLogger()->error("Boss world could not be removed");
                } finally {
                    $island->setUnloadLock(true);
                }
            }
        }));
    }

    /**
     * @return World
     */
    public function getWorld(): World
    {
        return $this->world;
    }

    public function removeParticipant(Player $player): void
    {
        if (!$player->isSpectator()) {
            $this->playersAlive--;
        }

        unset($this->participants[$player->getName()]);
    }

    /**
     * @return Boss|null
     */
    public function getBossEntity(): ?Boss
    {
        return $this->bossEntity;
    }

    /**
     * @param Boss $bossEntity
     */
    public function setBossEntity(Boss $bossEntity): void
    {
        $this->bossEntity = $bossEntity;
    }
}