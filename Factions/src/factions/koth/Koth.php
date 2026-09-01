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

namespace factions\koth;

use factions\Factions;
use factions\item\CustomItemManager;
use factions\koth\task\KothGameTask;
use factions\player\MMOPlayer;
use factions\utils\BaseClass;
use libminigames\Arena;
use libMMO\item\CustomItemRegistry;
use libMMO\item\ItemStorage;
use libMMO\MMOPlugin;
use libMMO\utils\Utils;
use NetherGames\NGEssentials\entity\BossBar;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\utils\ImpossibleException;
use pocketmine\block\BlockTypeIds;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Location;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\IntTag;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\Limits;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\world\World;

/**
 * King of the hill, a game where a faction member must gain dominance of a map
 * hill, the member who held the hill for a given time wins the koth.
 *
 * Significant changes compared to the of NGFactions code:
 * - There will be no kits supplied for players, they must bring their items in the arena.
 * - Arenas will be handled in this class.
 * - There will be also a Koth runner that randomly runs koth in the server.
 *
 * @package factions\koth
 */
class Koth extends BaseClass
{
    public const OBJECTIVE_CAPTURE_TIME = 25;

    /** @var World|null */
    private ?World $kothWorld = null;
    /** @var Player[] */
    private array $players = [];
    /** @var int[] */
    private array $captureProgress = [];
    /** @var BossBar[] */
    private array $bossBars = [];
    /** @var Player|null */
    private ?Player $winner = null;
    /** @var int */
    private int $status = Arena::STATUS_WAITING;

    public function __construct(MMOPlugin $instance)
    {
        parent::__construct($instance);

        $instance->getServer()->getPluginManager()->registerEvents(new KothListener($this), $instance);
    }

    public function isKothStarting(): bool
    {
        return $this->status === Arena::STATUS_STARTING;
    }

    public function isKothRunning(): bool
    {
        return $this->status === Arena::STATUS_RUNNING;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function setWinner(Player $player): void
    {
        $this->winner = $player;
    }

    public function inMatch(Player $player): bool
    {
        return isset($this->players[$player->getName()]);
    }

    public function getPlayers(): array
    {
        return $this->players;
    }

    public function addToCaptureProgress(Player $player): void
    {
        $this->captureProgress[$player->getName()]++;
    }

    /**
     * Attempts to add a player into this koth game.
     * This is based on the old factions koth, where it will give you a KOTH stick
     * and a few other effects while you were in the game.
     *
     * @param Player $player
     */
    public function addPlayer(Player $player): void
    {
        if ($this->kothWorld === null) {
            $wm = Server::getInstance()->getWorldManager();
            $wm->loadWorld('koth', true);

            $world = $wm->getWorldByName('koth');
            if ($world === null) throw new ImpossibleException("A world with the name 'koth' should have been loaded in the first place!");

            $this->kothWorld = $world;
            $this->kothWorld->setAutoSave(false);
        }

        $this->players[$player->getName()] = $player;
        $this->captureProgress[$player->getName()] = 0;

        $hungerManager = $player->getHungerManager();
        $hungerManager->addFood($hungerManager->getMaxFood());
        $hungerManager->setSaturation(20.0);
        $hungerManager->setExhaustion(0.0);

        $player->getInventory()->clearAll();
        $player->getInventory()->addItem(VanillaItems::STICK()
            ->setCustomName(TextFormat::RESET . TextFormat::AQUA . 'KOTH Stick')
            ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::KNOCKBACK(), 2)));

        $eff = $player->getEffects();
        $eff->clear();
        $eff->add(new EffectInstance(VanillaEffects::SPEED(), Limits::INT32_MAX, 2));
        $eff->add(new EffectInstance(VanillaEffects::JUMP_BOOST(), Limits::INT32_MAX, 2));

        $player->setAllowFlight(false);
        $player->setFlying(false);

        $player->teleport($this->getSpawnLocation());

        NGEssentials::getInstance()->getEntityManager()->getBossBar()->hideFrom($player);

        $bossbar = new BossBar();
        $bossbar->showTo($player);

        $this->showKothBossbar($player, $bossbar);
        $this->bossBars[$player->getName()] = $bossbar;
    }

    private function getSpawnLocation(): Position
    {
        return new Location(1, 67, 0, $this->getWorld(), 180, 0);
    }

    public function getWorld(): ?World
    {
        return $this->kothWorld;
    }

    public function showKothBossbar(Player $player, ?BossBar $bossbar = null): void
    {
        if ($bossbar === null) {
            $bossbar = $this->bossBars[$player->getName()];
        }

        $progress = $this->getCaptureProgress($player);
        $percent = floor(($progress / self::OBJECTIVE_CAPTURE_TIME) * 100);

        $bossbar->setTitle(TextFormat::YELLOW . 'Your current progress: ' . TextFormat::GOLD . $percent . '%');
        $bossbar->setHealthPercent((float)($progress / self::OBJECTIVE_CAPTURE_TIME));
    }

    public function getCaptureProgress(Player $player): int
    {
        return $this->captureProgress[$player->getName()]; // Never null condition
    }

    public function endMatch(): void
    {
        foreach ($this->players as $player) {
            $this->removePlayer($player);
        }

        $message = MMOPlugin::getPrefix() . 'KOTH game has ended. There were no winners.';
        if ($this->winner !== null) {
            $this->addParticipation($this->winner);
        } else {
            Server::getInstance()->broadcastMessage($message);
        }

        $this->winner = null;
        $this->status = Arena::STATUS_WAITING;

        $this->getPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(function (): void {
            $wm = Server::getInstance()->getWorldManager();
            if ($this->kothWorld !== null) {
                $wm->unloadWorld($this->kothWorld, true);

                $this->kothWorld = null;
            }
        }), 5 * 20);
    }

    /**
     * Remove the player from this koth game entirely.
     *
     * @param Player $player
     */
    public function removePlayer(Player $player): void
    {
        unset($this->players[$player->getName()]);
        unset($this->captureProgress[$player->getName()]);

        /** @var MMOPlayer $player */
        if ($player->isConnected()) {
            $player->setCombatTimer(0);

            $player->getInventory()->clearAll();
            $player->getOffhandInventory()->clearAll();
            $player->getEffects()->clear();
            $player->teleport(NGEssentials::getInstance()->getServerManager()->getSpawn());

            $this->bossBars[$player->getName()]->hideFrom($player);

            NGEssentials::getInstance()->getEntityManager()->getBossBar()->showTo($player);
        }
    }

    /**
     * @param Player $winner
     */
    private function addParticipation(Player $winner): void
    {
        $lootTable = Factions::getInstance()->getCrateManager()->getLootTable("Koth");
        $rewards = [];
        for ($i = 0; $i < 5; $i++) {
            $entry = $lootTable->randomEntry();
            if ($entry !== null) {
                $rewards[] = $entry->getItem();
            }
        }

        $rewardNames = [];
        foreach ($rewards as $reward) {
            $rewardNames[] = TextFormat::GOLD . TextFormat::clean($reward->getName());
            $nbt = $reward->getCustomBlockData();

            if ($nbt !== null) {
                if (ItemTypeIds::toBlockTypeId($reward->getTypeId()) === BlockTypeIds::TRIPWIRE_HOOK && Utils::hasTag($nbt, 'KeyDataType', IntTag::class)) {
                    $this->getPlugin()->getPlayerData()->increaseKey($winner, $nbt->getInt('KeyDataType'), $reward->getCount());
                    continue;
                } elseif ($reward->getTypeId() === CustomItemRegistry::MONEY_POUCH()->getTypeId() && Utils::hasTag($nbt, 'Min', IntTag::class) && Utils::hasTag($nbt, 'Max', IntTag::class)) {
                    $reward = CustomItemManager::getMoneyPouch(mt_rand($nbt->getInt('Min'), $nbt->getInt('Max')));
                    ItemStorage::createValidationId($reward, 'crate-' . $winner->getName(), static function (Item $reward) use ($winner) {
                        if ($winner->isConnected()) {
                            $winner->getInventory()->addItem($reward);
                        }
                    });
                    continue;
                }
            }

            $winner->getInventory()->addItem($reward);
        }
        $winner->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . 'You won ' . implode(TextFormat::GREEN . ', ', $rewardNames) . TextFormat::GREEN . ' for capturing the platform in Koth!');

        foreach ($this->players as $player) {
            if ($player->getXuid() !== $winner->getXuid() && $player->isConnected()) {
                $participationReward = CustomItemManager::getMoneyPouch(10000);
                ItemStorage::createValidationId($participationReward, 'participation-' . $player->getName(), static function (Item $participationReward) use ($player) {
                    /** @phpstan-ignore-next-line */
                    if ($player->isConnected()) {
                        $player->getInventory()->addItem($participationReward);
                    }
                });
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::YELLOW . 'You received a participation reward for Koth!');
            }
        }

        $faction = $this->getPlugin()->getPlayerData()->getFaction($winner);
        if ($faction === null) {
            Server::getInstance()->broadcastMessage(MMOPlugin::getPrefix() . TextFormat::GOLD . $winner->getName() . TextFormat::GRAY . ' was the first to capture the point!');
        } else {
            Server::getInstance()->broadcastMessage(MMOPlugin::getPrefix() . "Faction " . TextFormat::GOLD . $faction->getFactionName() . TextFormat::GRAY . " is the King of The Hill, Koth event is now ended!");
        }
    }

    public function startMatch(): void
    {
        $this->status = Arena::STATUS_RUNNING;

        $this->getPlugin()->getScheduler()->scheduleRepeatingTask(new KothGameTask($this), 20);
    }
}