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
use factions\player\MMOPlayer;
use libMMO\MMOPlugin;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\utils\TextFormat;

class KothListener implements Listener
{
    public const KOTH_FOLDER_NAME = "koth";

    /** @var Koth */
    private Koth $koth;

    public function __construct(Koth $koth)
    {
        $this->koth = $koth;
    }

    /**
     * @param EntityPreExplodeEvent $event
     * @priority HIGHEST
     */
    public function onExplode(EntityPreExplodeEvent $event): void
    {
        $entity = $event->getEntity();

        if ($entity->getWorld()->getFolderName() === self::KOTH_FOLDER_NAME) {
            $event->cancel();
        }
    }

    /**
     * @param EntityTeleportEvent $event
     * @priority HIGHEST
     */
    public function onEntityTeleport(EntityTeleportEvent $event): void
    {
        $player = $event->getEntity();

        if ($event->getTo()->getWorld()->getFolderName() === self::KOTH_FOLDER_NAME && $player instanceof MMOPlayer && !$this->koth->inMatch($player)) {
            $player->sendPopup(Factions::getPrefix() . TextFormat::RED . "Unable to teleport player.");
        }
    }

    /**
     * @param EntityDamageByEntityEvent $event
     * @priority LOW
     */
    public function onEntityDamageByEntityEvent(EntityDamageByEntityEvent $event): void
    {
        $damager = $event->getDamager();

        if ($damager->getWorld()->getFolderName() === self::KOTH_FOLDER_NAME && $damager instanceof MMOPlayer) {
            if ($this->koth->isKothStarting()) {
                $damager->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . 'PvP is disabled while waiting for players.');
            } else if (!$this->koth->inMatch($damager)) {
                $damager->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . 'You cannot hit a player in a KOTH game!');
            } else if (!$this->koth->isKothRunning()) {
                $damager->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . 'PvP is disabled when koth is not running.');
            } else {
                return;
            }

            $event->cancel();
        }
    }

    /**
     * @param BlockBreakEvent $event
     * @priority NORMAL
     */
    public function onBlockBreakEvent(BlockBreakEvent $event): void
    {
        $player = $event->getPlayer();

        if ($player->getWorld()->getFolderName() === self::KOTH_FOLDER_NAME) {
            $event->cancel();
        }
    }

    /**
     * @param BlockPlaceEvent $event
     * @priority NORMAL
     */
    public function onBlockPlaceEvent(BlockPlaceEvent $event): void
    {
        $player = $event->getPlayer();

        if ($player->getWorld()->getFolderName() === self::KOTH_FOLDER_NAME) {
            $event->cancel();
        }
    }

    /**
     * @param PlayerQuitEvent $event
     * @priority LOWEST
     */
    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();

        if ($player instanceof MMOPlayer && $player->getWorld()->getFolderName() === self::KOTH_FOLDER_NAME) {
            $this->koth->removePlayer($player);
        }
    }

    /**
     * @param PlayerExhaustEvent $event
     * @priority NORMAL
     */
    public function onPlayerExhaust(PlayerExhaustEvent $event): void
    {
        $player = $event->getPlayer();

        if ($player instanceof MMOPlayer && $player->getWorld()->getFolderName() === self::KOTH_FOLDER_NAME) {
            $event->cancel();
        }
    }

    /**
     * @param PlayerDropItemEvent $event
     * @priority NORMAL
     */
    public function onPlayerDropItem(PlayerDropItemEvent $event): void
    {
        $player = $event->getPlayer();

        if ($player instanceof MMOPlayer && $player->getWorld()->getFolderName() === self::KOTH_FOLDER_NAME) {
            $event->cancel();
        }
    }

    /**
     * @param EntityItemPickupEvent $event
     * @priority NORMAL
     */
    public function onInventoryPickupItem(EntityItemPickupEvent $event): void
    {
        $entity = $event->getEntity();

        if ($entity instanceof MMOPlayer && $entity->getWorld()->getFolderName() === self::KOTH_FOLDER_NAME) {
            $event->cancel();
        }
    }

    /**
     * @param InventoryTransactionEvent $event
     * @priority NORMAL
     */
    public function onInventoryTransactionEvent(InventoryTransactionEvent $event): void{
        $entity = $event->getTransaction()->getSource();

        if ($entity instanceof MMOPlayer && $entity->getWorld()->getFolderName() === self::KOTH_FOLDER_NAME) {
            $event->cancel();
        }
    }

    /**
     * @param PlayerItemUseEvent $event
     * @priority NORMAL
     */
    public function onItemUseEvent(PlayerItemUseEvent $event): void
    {
        $player = $event->getPlayer();

        if ($player instanceof MMOPlayer && $player->getWorld()->getFolderName() === self::KOTH_FOLDER_NAME) {
            $event->cancel();
        }
    }
}