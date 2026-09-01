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

namespace skyblock;

use Exception;
use libMMO\item\CustomItemRegistry;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use libMMO\utils\AdventureSettingsObject;
use libMMO\utils\trade\TradeManager;
use libMMO\utils\Utils;
use NetherGames\NGEssentials\events\NGStartDrainingEvent;
use NetherGames\NGEssentials\player\NGPlayer;
use NetherGames\NGEssentials\player\permissions\Permissions;
use NetherGames\NGEssentials\player\PlayerData as NGPlayerData;
use NetherGames\NGEssentials\ServerManager;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\inventory\BlockInventory;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\LeavesDecayEvent;
use pocketmine\event\block\PressurePlateUpdateEvent;
use pocketmine\event\entity\EntityCombustByEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\inventory\CraftItemEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\LowMemoryEvent;
use pocketmine\inventory\BaseInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Bucket;
use pocketmine\item\FlintSteel;
use pocketmine\item\Hoe;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\LiquidBucket;
use pocketmine\item\MilkBucket;
use pocketmine\item\PaintingItem;
use pocketmine\item\Shovel;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use skyblock\block\SpawnerBlock;
use skyblock\challenges\SkyblockChallengeSet;
use skyblock\commands\PvPCommand;
use skyblock\islands\Island;
use skyblock\utils\Area;

class EventListener extends \libMMO\EventListener
{
    /**
     * @param LowMemoryEvent $event
     *
     * @priority LOWEST
     */
    public function onMemoryLowEvent(LowMemoryEvent $event): void
    {
        if (!ServerManager::$draining) {
            $this->getPlugin()->getEssentials()->getServerManager()->setDraining();
        }
    }

    /**
     * @param LeavesDecayEvent $event
     *
     * @priority LOWEST
     */
    public function onBlockDecay(LeavesDecayEvent $event): void
    {
        $world = $event->getBlock()->getPosition()->getWorld();

        if ($world->getFolderName() === 'pvp') {
            $event->cancel();
        }
    }

    /**
     * @param BlockBreakEvent $event
     *
     * @priority LOWEST
     */
    public function onBlockBreak(BlockBreakEvent $event): void
    {
        if ($event->getPlayer()->getWorld()->getFolderName() === 'pvp') {
            $event->cancel();
        }

        parent::onBlockBreak($event);
    }

    /**
     * @param BlockPlaceEvent $event
     *
     * @priority LOWEST
     */
    public function onBlockPlace(BlockPlaceEvent $event): void
    {
        if ($event->getPlayer()->getWorld()->getFolderName() === 'pvp') {
            $event->cancel();
        }

        parent::onBlockPlace($event);
    }

    /**
     * @param NGStartDrainingEvent $event
     *
     * @priority LOWEST
     */
    public function onServerDrainEvent(NGStartDrainingEvent $event): void
    {
        $server = Server::getInstance();
        $plugin = $this->getPlugin();
        $islandManager = $this->getPlugin()->getIslandManager();
        $tradeManager = TradeManager::getInstance();

        /** @var MMOPlayer $player */
        foreach ($server->getOnlinePlayers() as $player) {
            $player->setCombatTimer(0);

            $plugin->getEssentials()->getPlayerManager()->forceTransfer($player);
        }

        if (count($islandManager->getLoadedIslands()) > 0) {
            $event->addPromise($islandManager->getUnloadingResolver());
        }

        $pendingTrades = $tradeManager->getAllPendingTrades();
        if (count($pendingTrades) > 0) {
            foreach ($pendingTrades as $trade) {
                $event->addPromise($trade);
            }

            $tradeManager->closeAllTrades(true);
        }
    }

    /**
     * @param PlayerDropItemEvent $event
     *
     * @priority LOWEST
     */
    public function onPlayerDropItem(PlayerDropItemEvent $event): void
    {
        if ($event->getPlayer()->getWorld()->getFolderName() === 'pvp') {
            $event->cancel();
        }

        parent::onPlayerDropItem($event);
    }

    /**
     * @param InventoryTransactionEvent $event
     *
     * @priority LOWEST
     * @handleCancelled
     */
    public function onInventoryTransaction(InventoryTransactionEvent $event): void
    {
        $player = $event->getTransaction()->getSource();
        $ess = $this->getPlugin()->getEssentials();

        if ($ess !== null && $ess->getPlayerData()->getBool($player, NGPlayerData::TRANSFER)) {
            $event->cancel();
            return;
        }

        foreach ($event->getTransaction()->getInventories() as $inventory) {
            if ($inventory instanceof BlockInventory || $inventory instanceof PlayerInventory) {
                foreach ($event->getTransaction()->getActions() as $action) {
                    $item = $action->getSourceItem();

                    if ($action instanceof SlotChangeAction && ($item->getBlock() instanceof SpawnerBlock || $item->getTypeId() === CustomItemRegistry::MONEY_POUCH()->getTypeId()) && $item->getCount() >= 10) {
                        if ($inventory instanceof BlockInventory) {
                            $holder = $inventory->getHolder();
                            if ($holder->isValid()) {
                                $island = $this->getPlugin()->getIslandManager()->getIslandByWorld($holder->getWorld());

                                $worldName = $holder->getWorld()->getFolderName();
                                if ($island !== null) {
                                    $worldName = $island->getOwner() . ':' . $island->getOwnerXuid();
                                }

                                $this->getPlugin()->getLoggerStream()->add(
                                    $player->getName() . ' tried to select ' . $item->getCount() . 'x ' . TextFormat::clean($item->getCustomName()) . "s in a container (chest, furnace, etc.) inventory\n" .
                                    "Extra: world=" . $worldName . " x=" . $holder->getX() . " y=" . $holder->getX() . " z=" . $holder->getX());
                            } else {
                                $this->getPlugin()->getLoggerStream()->add(
                                    $player->getName() . ' tried to select ' . $item->getCount() . 'x ' . TextFormat::clean($item->getCustomName()) . "s in a container (chest, furnace, etc.) inventory\n" .
                                    "Extra: world=" . $player->getWorld()->getFolderName() . " position=undefined");
                            }
                        } else {
                            $this->getPlugin()->getLoggerStream()->add(
                                $player->getName() . ' tried to select ' . $item->getCount() . 'x ' . TextFormat::clean($item->getCustomName()) . " in their own inventory.\n" .
                                "Extra: world=" . $player->getWorld()->getFolderName() . " position=undefined");
                        }
                    }
                }
            }
        }
    }

    /**
     * @param InventoryOpenEvent $event
     *
     * @priority MONITOR
     * @handleCancelled
     */
    public function onContainerOpenEvent(InventoryOpenEvent $event): void
    {
        $inv = $event->getInventory();
        $player = $event->getPlayer();

        if ($inv instanceof BaseInventory && $inv instanceof BlockInventory) {
            $holder = $inv->getHolder();

            [$durabilityVL, $loreTileVL, $maxLore, $curseVL] = Utils::doInventoryCheck($inv->getContents());

            if ($durabilityVL > 1 || $loreTileVL > 2 || $curseVL > 8) {
                $worldName = $player->getWorld()->getFolderName();
                $v = $holder->asVector3();

                if ($holder->isValid()) {
                    $island = $this->getPlugin()->getIslandManager()->getIslandByWorld($inv->getHolder()->getWorld());

                    if ($island !== null) {
                        $worldName = $island->getOwner() . ':' . $island->getOwnerXuid();
                    }
                }

                $this->getPlugin()->getLoggerStream()->add("{$player->getName()}: {$v->getX()} {$v->getY()} {$v->getZ()}, is=$worldName, durVL=$durabilityVL, loreVL=$loreTileVL, maxLore=$maxLore, curseVL=$curseVL");
            }
        }
    }

    /**
     * @param PlayerInteractEvent $event
     *
     * @priority LOWEST
     */
    public function onPlayerInteract(PlayerInteractEvent $event): void
    {
        if ($event->getPlayer()->getWorld()->getFolderName() === 'pvp') {
            $item = $event->getItem();

            if ($item instanceof PaintingItem || $item instanceof Hoe || $item instanceof Bucket || $item instanceof LiquidBucket || $item instanceof MilkBucket || $item instanceof Shovel || $item instanceof FlintSteel) {
                $event->cancel();
            }
        }

        parent::onPlayerInteract($event);
    }

    /**
     * @param EntityTeleportEvent $event
     *
     * @priority LOW
     */
    public function onEntityTeleport(EntityTeleportEvent $event): void
    {
        $player = $event->getEntity();
        $target = $event->getTo()->getWorld();
        $origin = $event->getFrom()->getWorld();

        $islandTo = $this->getPlugin()->getIslandManager()->getIslandByWorld($target);

        if ($origin->getId() !== $target->getId() && $player instanceof NGPlayer && $player->isConnected() && !$this->getPlugin()->getEssentials()->getPlayerData()->getBool($player, NGPlayerData::TRACK)) {
            if ($islandTo !== null) {
                if ($islandTo->snooper !== $player) {
                    if ($player->isInvisible()) {
                        $player->setInvisible(false);
                    }
                    if (!$player->hasBlockCollision()) {
                        $player->setHasBlockCollision(true);
                    }
                    if ($player->getAllowFlight()) {
                        $player->setAllowFlight(false);
                    }
                    if ($player->isFlying()) {
                        $player->setFlying(false);
                    }
                } else {
                    AdventureSettingsObject::getInstance()->setBuildingPermission($player, !$player->hasPermission(Permissions::RANK_ADMIN));
                }
            }

            if ($target->getFolderName() === 'pvp') {
                PvPCommand::checkPvPAllowed($player, static function (?string $reason) use ($player, $event): void {
                    if ($player->isConnected() && $reason !== null) {
                        $player->sendMessage(TextFormat::RED . $reason);
                        $event->cancel();

                        // event might not be cancellable due to async
                        $player->teleport($event->getFrom());
                    }
                });

                AdventureSettingsObject::getInstance()->setBuildingPermission($player, false);
                $player->setHealthTag();
            } elseif ($target->getServer()->getWorldManager()->getDefaultWorld() === $target) {
                AdventureSettingsObject::getInstance()->setBuildingPermission($player, false);
            }

            if ($event->getFrom()->getWorld()->getFolderName() === 'pvp') {
                $player->setHealthTag(false);
            }
        }

        parent::onEntityTeleport($event);
    }

    public function onPlayerPressurePlateTrigger(PressurePlateUpdateEvent $event): void
    {
        $pos = $event->getBlock()->getPosition();

        if ($this->getPlugin()->isAgora() && $pos->getWorld() === $pos->getWorld()->getServer()->getWorldManager()->getDefaultWorld()) {
            foreach ($event->getActivatingEntities() as $player) {
                if (!$player instanceof Player) {
                    return;
                }

                if ($player->getLocation()->distance($player->getWorld()->getSpawnLocation()) < 30) {
                    $yaw = $player->getLocation()->getYaw();

                    if ($yaw > 291 && $yaw < 352) {
                        $motFlat = $player->getDirectionPlane()->normalize()->multiply(10 * 3.75 / 20);//Seems to work almost perfectly
                        $mot = new Vector3($motFlat->x, 0.5, $motFlat->y);

                        $player->setMotion($mot);
                    }
                }
            }
        }
    }

    /**
     * @return SkyBlock
     */
    public function getPlugin(): MMOPlugin
    {
        /** @var SkyBlock $plugin */
        $plugin = parent::getPlugin();

        return $plugin;
    }

    /**
     * @param CraftItemEvent $event
     *
     * @priority LOWEST
     */
    public function onCraftItem(CraftItemEvent $event): void
    {
        $player = $event->getPlayer();

        foreach ($event->getRecipe()->getResultsFor($player->getCraftingGrid()) as $result) {
            if ($result->getTypeId() === ItemTypeIds::fromBlockTypeId(BlockTypeIds::HOPPER)) {
                $player->sendMessage(TextFormat::RED . "You can't craft this item!");
                $event->cancel();
            }
        }
    }

    /**
     * @param EntityDamageEvent $event
     *
     * @priority NORMAL
     */
    public function onEntityDamage(EntityDamageEvent $event): void
    {
        $entity = $event->getEntity();
        $world = $entity->getWorld();

        if ($world === $entity->getWorld()->getServer()->getWorldManager()->getDefaultWorld()) {
            if ($event->getCause() === EntityDamageEvent::CAUSE_VOID) {
                $entity->teleport($this->getPlugin()->getEssentials()->getServerManager()->getSpawn());
            }

            $event->cancel();
        } else if ($world->getFolderName() === 'pvp' && $event->getCause() === EntityDamageEvent::CAUSE_FALL) {
            $event->cancel();
        } elseif ($entity instanceof Player && ($ess = $this->getPlugin()->getEssentials()) !== null && (($playerData = $ess->getPlayerData())->getBool($entity, NGPlayerData::TRANSFER) || $playerData->getBool($entity, NGPlayerData::TRACK))) {
            $event->cancel();
        }
    }

    /**
     * @param EntityDamageByEntityEvent $event
     *
     * @priority HIGH
     */
    public function onEntityDamageByEntity(EntityDamageByEntityEvent $event): void
    {
        $entity = $event->getEntity();
        $world = $entity->getWorld();

        if ($world->getFolderName() === 'pvp' && (Area::isInPvPSafeZone($entity->getPosition()->asVector3()) || Area::isInPvPSafeZone($event->getDamager()->getPosition()->asVector3()))) {
            $event->cancel();
        } else {
            parent::onEntityDamageByEntity($event);
        }
    }

    /**
     * @param EntityShootBowEvent $event
     *
     * @priority LOWEST
     */
    public function onEntityShootBow(EntityShootBowEvent $event): void
    {
        $entity = $event->getEntity();
        $world = $entity->getWorld();

        if ($world->getFolderName() === 'pvp' && Area::isInPvPSafeZone($entity->getPosition()->asVector3())) {
            $event->cancel();
        }
    }

    /**
     * @param EntityCombustByEntityEvent $event
     *
     * @priority LOWEST
     */
    public function onEntityCombust(EntityCombustByEntityEvent $event): void
    {
        $entity = $event->getEntity();
        $world = $entity->getWorld();

        if ($world->getFolderName() === 'pvp' && (Area::isInPvPSafeZone($entity->getPosition()->asVector3()) || Area::isInPvPSafeZone($event->getCombuster()->getPosition()->asVector3()))) {
            $event->cancel();
        }
    }

    /**
     * @param PlayerRespawnEvent $event
     *
     * @priority LOWEST
     */
    public function onPlayerRespawn(PlayerRespawnEvent $event): void
    {
        $plugin = $this->getPlugin();

        if (!$this->getPlugin()->isAgora()) {
            $player = $event->getPlayer();

            $plugin->getScheduler()->scheduleTask(new ClosureTask(static function () use ($player, $plugin): void {
                if ($player->isConnected()) {
                    $plugin->getPlayerManager()->transferPlayer($player, ServerManager::GAME_TYPE_AGORA);
                }
            }));
        }

        if (($ess = $this->getPlugin()->getEssentials()) !== null) {
            $event->setRespawnPosition($ess->getServerManager()->getSpawn());
        }
    }

    /**
     * @param InventoryOpenEvent $event
     *
     * @priority HIGHEST
     */
    public function onShopTransaction(InventoryOpenEvent $event): void
    {
        $player = $event->getPlayer();
        $world = $player->getWorld();

        $island = $this->getPlugin()->getIslandManager()->getIslandByWorld($world);
        if ($island !== null && $island->hasPermission($player, Island::PERMISSION_INVENTORY)) {
            parent::onShopTransaction($event);
        }
    }

    /**
     * @param PlayerDeathEvent $event
     *
     * @priority NORMAL
     */
    public function onPlayerDeath(PlayerDeathEvent $event): void
    {
        $player = $event->getPlayer();

        foreach ($this->getPlugin()->getPlayerChallengeManager()->getActiveChallenges($player) as $challenge) {
            $challenge->increaseProgress($player, SkyblockChallengeSet::KILL_STREAK, $event);
        }

        try {
            $this->getPlugin()->getRollbackEngine()->handleListener($event);
        } catch (Exception $error) {
            $this->getPlugin()->getLogger()->error("RollbackEngine: Unable to handle listener for {$player->getName()}");
            $this->getPlugin()->getLogger()->logException($error);
        }

        parent::onPlayerDeath($event);
    }
}