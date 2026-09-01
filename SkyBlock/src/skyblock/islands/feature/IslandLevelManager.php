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

namespace skyblock\islands\feature;

use libMMO\MMOPlugin;
use libMMO\utils\BaseListener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;
use skyblock\entities\boss\Boss;
use skyblock\entities\boss\BossMinion;
use skyblock\islands\feature\boss\BossLevelup;

class IslandLevelManager extends BaseListener
{
    /** @var BossLevelup[] */
    private array $bossLevels = [];
    /** @var int[] */
    private array $watchlist = [];

    public function __construct(MMOPlugin $instance)
    {
        parent::__construct($instance);
    }

    public function getLevelupByWorld(World $world): ?BossLevelup
    {
        return $this->bossLevels[$world->getId()] ?? null;
    }

    public function removeLevelup(World $world): void
    {
        unset($this->bossLevels[$world->getId()]);
    }

    public function addLevelUp(BossLevelup $levelup): void
    {
        $this->bossLevels[$levelup->getWorld()->getId()] = $levelup;
    }

    public function getAll(): array
    {
        return $this->bossLevels;
    }

    /**
     * @param EntityDespawnEvent $event
     *
     * @priority HIGH
     */
    public function onEntityDespawn(EntityDespawnEvent $event): void
    {
        $entity = $event->getEntity();

        if ($entity instanceof Boss && !($entity instanceof BossMinion)) {
            $levelup = $this->getLevelupByWorld($entity->getWorld());
            $levelup?->handleDone();
        }
    }

    /**
     * @param EntityDamageEvent $event
     *
     * @priority HIGH
     */
    public function onEntityDamage(EntityDamageEvent $event): void
    {
        $player = $event->getEntity();

        if ($player instanceof Player && $event->getFinalDamage() >= $player->getHealth()) {
            $levelup = $this->getLevelupByWorld($world = $player->getWorld());

            if ($levelup !== null) {
                $event->cancel();

                foreach ($player->getDrops() as $item) {
                    $world->dropItem($player->getPosition()->asVector3(), $item);
                }

                $player->setGamemode(GameMode::SPECTATOR);
                $player->setHealth($player->getMaxHealth());

                $player->getHungerManager()->setFood($player->getHungerManager()->getMaxFood());
                $player->extinguish();

                $player->getEffects()->clear();
                $player->getInventory()->clearAll();
                $player->getCursorInventory()->clearAll();
                $player->getArmorInventory()->clearAll();

                $levelup->getBossEntity()?->setTargetEntity($levelup->getBossEntity()->getNearestPlayer());

                $player->sendTitle('§l§cYOU DIED!', '§7You are now a spectator.');

                $levelup->playersAlive--;
            }
        }
    }

    /**
     * @param EntityDamageByEntityEvent $event
     *
     * @priority NORMAL
     */
    public function onEntityDamageByEntity(EntityDamageByEntityEvent $event): void
    {
        $player = $event->getEntity();
        $damager = $event->getDamager();

        if ($player instanceof Player && $damager instanceof Player && $this->getLevelupByWorld($player->getWorld()) !== null) {
            $event->cancel();
        }
    }

    /**
     * @param BlockPlaceEvent $event
     *
     * @priority NORMAL
     */
    public function onBuildPlace(BlockPlaceEvent $event): void
    {
        if ($this->getLevelupByWorld($event->getPlayer()->getWorld()) !== null) {
            $event->cancel();
        }
    }

    /**
     * @param BlockBreakEvent $event
     *
     * @priority NORMAL
     */
    public function onBlockBreak(BlockBreakEvent $event): void
    {
        if ($this->getLevelupByWorld($event->getPlayer()->getWorld()) !== null) {
            $event->cancel();
        }
    }

    /**
     * @param PlayerBucketEmptyEvent $event
     *
     * @priority NORMAL
     */
    public function onBucketEmpty(PlayerBucketEmptyEvent $event): void
    {
        if ($this->getLevelupByWorld($event->getPlayer()->getWorld()) !== null) {
            $event->cancel();
        }
    }

    /**
     * @param PlayerQuitEvent $event
     *
     * @priority NORMAL
     */
    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();

        $world = $this->watchlist[$player->getId()] ?? $player->getWorld();
        if (($levelup = $this->getLevelupByWorld($world)) !== null) {
            $levelup->removeParticipant($player);

            $player->getServer()->broadcastMessage(TextFormat::GRAY . $player->getName() . ' disconnected', $levelup->getParticipants());
        }

        unset($this->watchlist[$player->getId()]);
    }

    /**
     * @param EntityTeleportEvent $event
     * @priority NORMAL
     */
    public function onPlayerTeleportEvent(EntityTeleportEvent $event): void
    {
        if (($player = $event->getEntity()) instanceof Player) {
            $from = $event->getFrom()->getWorld();
            $to = $event->getTo()->getWorld();

            // The player was teleported from a levelup world and is in the participation list.
            // add to watchlist if the world they are from is not the same as the world they are going into.
            // Reason: The player might be able to join the arena back?
            if (($levelup = $this->getLevelupByWorld($from)) !== null && in_array($player, $levelup->getParticipants(), true) && $from->getId() !== $to->getId()) {
                $this->watchlist[$player->getId()] = $from;
            }
        }
    }
}