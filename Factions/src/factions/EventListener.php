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

namespace factions;

use Exception;
use factions\commands\PvpCommand;
use factions\entities\boss\Boss;
use factions\entities\stackable\StackableCow;
use factions\entities\stackable\StackableIronGolem;
use factions\entities\stackable\StackableSheep;
use factions\entities\stackable\StackableSpider;
use factions\entities\stackable\StackableZombie;
use factions\faction\object\Faction;
use factions\item\CustomItemManager;
use factions\item\CustomItemRegistry as FactionsItemRegistry;
use factions\item\item\GeneratorBucket;
use factions\item\item\ThrowableTNT;
use factions\player\MMOPlayer;
use factions\player\PlayerData;
use factions\player\PlayerManager;
use factions\utils\Area;
use factions\utils\BlockDurability;
use factions\utils\Database;
use factions\utils\GroupManager;
use factions\utils\Utils;
use Generator;
use GlobalLogger;
use libMMO\commands\FlyCommand;
use libMMO\item\CooldownList;
use libMMO\item\CustomItemRegistry;
use libMMO\item\ItemStorage;
use libMMO\MMOPlugin;
use libMMO\utils\AdventureSettingsObject;
use libMMO\utils\trade\TradeManager;
use libVanilla\entity\EntityBase;
use muqsit\invmenu\inventory\InvMenuInventory;
use NetherGames\NGEssentials\events\NGJoinEvent;
use NetherGames\NGEssentials\events\NGPlayerTransferEvent;
use NetherGames\NGEssentials\events\NGRestartEvent;
use NetherGames\NGEssentials\events\NGStartDrainingEvent;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\player\permissions\Permissions;
use NetherGames\NGEssentials\player\PlayerData as NGPlayerData;
use NetherGames\NGEssentials\ServerManager;
use pocketmine\block\Door;
use pocketmine\block\inventory\AnvilInventory;
use pocketmine\block\inventory\BlockInventory;
use pocketmine\block\inventory\EnchantInventory;
use pocketmine\block\MonsterSpawner;
use pocketmine\block\Transparent;
use pocketmine\block\Trapdoor;
use pocketmine\entity\Living;
use pocketmine\entity\projectile\Arrow;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockFormEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockSpreadEvent;
use pocketmine\event\block\BlockUpdateEvent;
use pocketmine\event\block\LeavesDecayEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityMotionEvent;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\EntityTrampleFarmlandEvent;
use pocketmine\event\entity\ProjectileHitBlockEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\item\Axe;
use pocketmine\item\Bucket;
use pocketmine\item\EnderPearl;
use pocketmine\item\FlintSteel;
use pocketmine\item\Hoe;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\LiquidBucket;
use pocketmine\item\MilkBucket;
use pocketmine\item\PaintingItem;
use pocketmine\item\Shovel;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\world\World;
use poggit\libasynql\result\SqlChangeResult;
use poggit\libasynql\result\SqlSelectResult;
use poggit\libasynql\SqlThread;
use SOFe\AwaitGenerator\Await;
use Throwable;
use function in_array;
use function microtime;

class EventListener extends \libMMO\EventListener
{
    /** @var int[] */
    public array $lastDamageTicks = [];

    /** @var int[] */
    public array $lastShootTicks = [];

    public function __construct(Factions $plugin)
    {
        parent::__construct($plugin);
    }

    /**
     * @param PlayerDropItemEvent $event
     *
     * @priority LOWEST
     */
    public function onPlayerDropItem(PlayerDropItemEvent $event): void
    {
        // NOOP
    }

    /**
     * @param ProjectileLaunchEvent $event
     *
     * @priority LOWEST
     */
    public function onProjectileLaunch(ProjectileLaunchEvent $event): void
    {
        // NOOP
    }

    /**
     * @param EntityShootBowEvent $event
     *
     * @priority LOWEST
     */
    public function onEntityShootBow(EntityShootBowEvent $event): void
    {
        $player = $event->getEntity();

        if ($player instanceof Player) {
            if (!Area::inPvpArea($player)) {
                $event->cancel();
            }
        }
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
        $tradeManager = TradeManager::getInstance();

        $pendingTrades = $tradeManager->getAllPendingTrades();
        if (count($pendingTrades) > 0) {
            foreach ($pendingTrades as $trade) {
                $event->addPromise($trade);
            }

            $tradeManager->closeAllTrades(true);
        }

        $total = 0;

        /** @var MMOPlayer $player */
        foreach ($server->getOnlinePlayers() as $player) {
            $total++;

            Server::getInstance()->getLogger()->info("Unloading player: " . $player->getName());

            $player->setCombatTimer(0);

            Server::getInstance()->getLogger()->info("Unloading player (Teleport): " . $player->getName());

            $plugin->getEssentials()->getPlayerManager()->forceTransfer($player);

            Server::getInstance()->getLogger()->info("Unloading player: " . $player->getName() . ' successful.');
        }

        Server::getInstance()->getLogger()->info("Unloading player successful. Total: $total");

        $plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($server) {
            $serverManager = $this->getPlugin()->getEssentials();
            if ($serverManager->getServerManager()->getGameType() === ServerManager::GAME_TYPE_FARLANDS) {
                // Unload wilderness world as possible, we do not want data corruption :(
                $wild = $server->getWorldManager()->getWorldByName('wild');
                $server->getWorldManager()->unloadWorld($wild, true);
            }

            $server->shutdown();
        }), 5 * 20);
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
        $wild = $this->getPlugin()->getServer()->getWorldManager()->getWorldByName('wild');

        if ($player instanceof MMOPlayer) {
            $isTracking = NGEssentials::getInstance()->getPlayerData()->getBool($player, NGPlayerData::TRACK);
            $adventureAck = AdventureSettingsObject::getInstance();

            if ($player->isCombatTimerActive()) {
                $player->sendPopup(TextFormat::RED . "You cannot teleport anywhere, there is no point of escaping anymore.");

                $event->cancel();
            } else if (!$isTracking) {
                if (!Factions::isBadlands() && $wild !== null) {
                    $isTargetWild = $target->getId() === $wild->getId();
                    $currentPermission = $adventureAck->getBuildingPermission($player);

                    if ($isTargetWild && !$currentPermission) {
                        $adventureAck->setBuildingPermission($player, true);
                    } elseif (!$isTargetWild && $currentPermission) {
                        $adventureAck->setBuildingPermission($player, false);
                    }
                }
            }
        }

        parent::onEntityTeleport($event);
    }

    /**
     * @param EntityPreExplodeEvent $event
     *
     * @priority LOWEST
     */
    public function onExplode(EntityPreExplodeEvent $event): void
    {
        $entity = $event->getEntity();
        $wm = $entity->getWorld()->getServer()->getWorldManager();

        if (Factions::isBadlands() || ($entity->getWorld() === $wm->getDefaultWorld() && Area::isAreaInside($entity->getPosition()))) {
            $event->setBlockBreaking(false);
        }
    }

    /**
     * @param PlayerItemUseEvent $event
     *
     * @priority LOWEST
     */
    public function onPlayerItemUse(PlayerItemUseEvent $event): void
    {
        /** @var MMOPlayer $player */
        $player = $event->getPlayer();
        $item = $event->getItem();

        $ess = $this->getPlugin()->getEssentials();

        if ($ess !== null && $ess->getPlayerData()->getBool($player, NGPlayerData::TRANSFER)) {
            $event->cancel();
            return;
        }

        if ($player->isCombatTimerActive()) {
            $error = match (true) {
                $item instanceof EnderPearl => MMOPlugin::getPrefix() . TextFormat::RED . "You can't use an enderpearl while being combat tagged.",
                $item->getTypeId() === ItemTypeIds::FISHING_ROD => MMOPlugin::getPrefix() . TextFormat::RED . "You can't use a fishing rod while being combat tagged.",
                default => null
            };

            if ($error === null) {
                return;
            }

            $event->cancel();

            $player->sendMessage($error);
        } else if ($ess->getPlayerData()->getBool($player, NGPlayerData::TRACK)) {
            $event->cancel();
        } else if (Factions::isBadlands()) {
            if ($item instanceof ThrowableTNT) {
                $event->cancel();
            } else if ($item instanceof EnderPearl && !Area::inPvpArea($player)) {
                $player->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . "You can only use an enderpearl in the arena.");

                $event->cancel();
            }
        }
    }

    /**
     * @param PlayerRespawnEvent $event
     *
     * @priority LOW
     */
    public function onPlayerRespawnEvent(PlayerRespawnEvent $event): void
    {
        $worldManager = $this->getPlugin()->getServer()->getWorldManager();

        $event->setRespawnPosition($worldManager->getDefaultWorld()->getSpawnLocation());
    }

    /**
     * @param EntityMotionEvent $event
     *
     * @priority HIGHEST
     */
    public function onPlayerMotion(EntityMotionEvent $event): void
    {
        $entity = $event->getEntity();

        if ($entity instanceof Player && $entity->getWorld()->getFolderName() === "wild" && !Area::inPvpArea($entity)) {
            $event->cancel();
        }
    }

    /**
     * @param BlockPlaceEvent $event
     *
     * @priority LOWEST
     */
    public function onBlockPlace(BlockPlaceEvent $event): void
    {
        $plugin = $this->getPlugin();
        $player = $event->getPlayer();

        foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
            if (!$this->isPosAccessible($player, $block->getPosition())) {
                $event->cancel();
                return;
            }
        }

        if (Factions::isBadlands()) {
            $event->cancel();
        } else {
            $factionId = $this->getPlugin()->getPlayerData()->getInt($player, PlayerData::FACTION_ID);

            $claim = $plugin->getClaimManager()->getClaimInPosition($player->getPosition());
            $faction = $plugin->getFactionManager()->getFaction($factionId);

            // Check if the player is in a claim.
            if ($claim !== null && ($faction === null || $faction->getFactionId() !== $claim->getFactionId() && !$faction->isFactionAlly($claim->getFactionId()) && !$faction->hasPermission($player, Faction::ALLOW_BASE_BUILD) && !$player->hasPermission(Permissions::RANK_ADMIN))) {
                $player->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . "You can't place any blocks in {$claim->getFactionName()}'s claim.");

                $event->cancel();
            }
        }
    }

    /**
     * @param BlockBreakEvent $event
     *
     * @priority LOWEST
     */
    public function onBlockBreak(BlockBreakEvent $event): void
    {
        $player = $event->getPlayer();
        $blockPos = $event->getBlock()->getPosition();

        $factionManager = $this->getPlugin()->getFactionManager();

        if (!$this->isPosAccessible($player, $blockPos)) {
            $player->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . "You don't have permission to break blocks in this area.");

            $event->cancel();
        } else if (Factions::isBadlands()) {
            $event->cancel();
        } else {
            $factionId = $this->getPlugin()->getPlayerData()->getInt($player, PlayerData::FACTION_ID);

            $claimManager = $this->getPlugin()->getClaimManager();

            $claim = $claimManager->getClaimInPosition($player->getPosition());
            $faction = $factionManager->getFaction($factionId);

            // Check if the player is in a claim.
            if ($claim !== null && ($faction === null || $faction->getFactionId() !== $claim->getFactionId() && !$faction->isFactionAlly($claim->getFactionId()) && !$player->hasPermission(Permissions::RANK_ADMIN))) {
                $player->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . "You can't break any blocks in {$claim->getFactionName()}'s claim.");

                $event->cancel();
                return;
            }

            foreach ($event->getDrops() as $drop) {
                if (!$player->getInventory()->canAddItem($drop)) {
                    $player->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . "Your inventory is full, clear some items first before breaking this.");

                    $event->cancel();
                    return;
                }
            }

            foreach ($event->getDrops() as $drop) {
                $event->getPlayer()->getInventory()->addItem($drop);
            }

            if (!($event->getBlock() instanceof MonsterSpawner) && $event->getXpDropAmount() > 0) {
                $player->getXpManager()->onPickupXp($event->getXpDropAmount());
            }

            $event->setDrops([]);
            $event->setXpDropAmount(0);
        }
    }

    /**
     * @param BlockSpreadEvent $event
     *
     * @priority LOWEST
     */
    public function onBlockSpreadEvent(BlockSpreadEvent $event): void
    {
        $position = $event->getBlock()->getPosition();

        if (Area::isAreaInside($position)) {
            $event->cancel();
        }
    }

    /**
     * @param BlockFormEvent $event
     *
     * @priority LOWEST
     */
    public function onBlockFormEvent(BlockFormEvent $event): void
    {
        $position = $event->getBlock()->getPosition();

        if (Area::isAreaInside($position)) {
            $event->cancel();
        }
    }

    /**
     * @param NGJoinEvent $event
     *
     * @priority LOWEST
     */
    public function onNGJoin(NGJoinEvent $event): void
    {
        if (Factions::isBadlands()) {
            $player = $event->getPlayer();

            PvpCommand::checkPvPAllowed($player, function (?string $reason) use ($player, $event): void {
                if (!$player->isConnected()) {
                    return;
                }

                if ($reason === null) {
                    parent::onNGJoin($event);
                } else {
                    $player->sendMessage(MMOPlugin::getPrefix() . $reason);

                    $plugin = NGEssentials::getInstance();
                    $plugin->getPlayerManager()->forceTransfer($player);
                }
            });
        } else {
            parent::onNGJoin($event);
        }
    }

    public function isPosAccessible(Player $player, Position $position): bool
    {
        $isTracking = NGEssentials::getInstance()->getPlayerData()->getBool($player, NGPlayerData::TRACK);
        if ($isTracking || Factions::isBadlands()) {
            return $player->hasPermission(Permissions::RANK_ADMIN);
        } else if (($claim = $this->getPlugin()->getClaimManager()->getClaimInPosition($position)) !== null) {
            return $claim->canAccess($player) || $player->hasPermission(Permissions::RANK_ADMIN);
        } else if (Area::isAreaInside($position)) {
            return $player->hasPermission(PlayerManager::PERMISSION_MODIFY);
        }

        return true;
    }

    /**
     * @return Factions
     */
    public function getPlugin(): MMOPlugin
    {
        /** @var Factions $plugin */
        $plugin = parent::getPlugin();

        return $plugin;
    }

    public function onInventoryOpen(InventoryOpenEvent $event): void
    {
        /** @var MMOPlayer $player */
        $player = $event->getPlayer();

        if ($player->isCombatTimerActive()) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't do that while combat tagged.");

            $event->cancel();
        }
    }

    /**
     * @param NGPlayerTransferEvent $event
     * @priority HIGHEST
     */
    public function onNGPlayerTransfer(NGPlayerTransferEvent $event): void
    {
        parent::onNGPlayerTransfer($event);

        if (!$event->isCancelled()) {
            /** @var MMOPlayer $player */
            $player = $event->getPlayer();

            $player->showCoordinates(false);
        }
    }

    /**
     * @param PlayerQuitEvent $event
     * @priority MONITOR
     */
    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();

        $factionManager = $this->getPlugin()->getFactionManager();
        $playerData = $this->getPlugin()->getPlayerData();
        $factionManager->removeInvites($player->getName());

        $faction = $factionManager->getFaction($playerData->getInt($player, PlayerData::FACTION_ID));

        if ($faction !== null) {
            $factionManager->collectGarbage($player, $faction);
        }

        unset($this->lastDamageTicks[$player->getXuid()]);
        unset($this->lastShootTicks[$player->getXuid()]);

        parent::onPlayerQuit($event);
    }

    /**
     * @param LeavesDecayEvent $event
     * @priority LOWEST
     */
    public function onLeaveDecay(LeavesDecayEvent $event): void
    {
        $block = $event->getBlock();

        if (!Factions::isBadlands() && Area::isAreaInside($block->getPosition(), 'safezone')) {
            $event->cancel();
        }
    }

    /**
     * @param EntityDamageEvent $event
     * @priority LOWEST
     */
    public function onEntityDamageEvent(EntityDamageEvent $event): void
    {
        $entity = $event->getEntity();

        if ($entity instanceof MMOPlayer) {
            $ess = $this->getPlugin()->getEssentials();

            if ($ess !== null && (($playerData = $ess->getPlayerData())->getBool($entity, NGPlayerData::TRANSFER) || $playerData->getBool($entity, NGPlayerData::TRACK))) {
                $event->cancel();
            } else if ($entity->getFireTicks() > 1000) {
                $entity->setFireTicks(100);
            } else if ($event->getCause() === EntityDamageEvent::CAUSE_VOID) {
                $event->cancel();

                $worldManager = $this->getPlugin()->getServer()->getWorldManager();
                if (Factions::isBadlands()) {
                    /** @var World $pvp */
                    $pvp = $worldManager->getWorldByName('FactionsPvP');

                    $entity->teleport($pvp->getSpawnLocation());
                } else {
                    $event->uncancel();
                }
            } else if ($event->getCause() === EntityDamageEvent::CAUSE_FALL) {
                $event->cancel();
            } else if (!Area::inPvpArea($entity)) {
                $event->cancel();
            } else if ($event instanceof EntityDamageByChildEntityEvent) {
                $from = $event->getDamager();

                if ($from instanceof Player && !Area::inPvpArea($from)) {
                    $event->cancel();
                }
            }
        } else if ($event instanceof EntityDamageByEntityEvent && $entity instanceof Living) {
            $damager = $event->getDamager();

            if ($damager instanceof MMOPlayer) {
                $isTracking = NGEssentials::getInstance()->getPlayerData()->getBool($damager, NGPlayerData::TRACK);

                if ($isTracking || !Area::inPvpArea($damager)) {
                    $event->cancel();
                }
            }
        }

        if (!($entity instanceof MMOPlayer) && ($event->getCause() === EntityDamageEvent::CAUSE_LAVA || $event->getCause() === EntityDamageEvent::CAUSE_FIRE)) {
            $damage = match (get_class($entity)) {
                StackableCow::class => 3.3,
                StackableIronGolem::class, StackableZombie::class, StackableSpider::class, StackableSheep::class => 4,
                default => -1
            };

            if ($damage !== -1) {
                $event->setBaseDamage($damage);
                $event->setAttackCooldown(2);
            }
        }
    }

    /**
     * @param NGRestartEvent $event
     * @priority MONITOR
     */
    public function onNGRestartEvent(NGRestartEvent $event): void
    {
        $koth = $this->getPlugin()->getKoth();
        if ($koth === null) {
            return;
        }

        foreach ($koth->getPlayers() as $player) {
            $koth->removePlayer($player);
        }
    }

    /**
     * @param EntityDamageByEntityEvent $event
     * @priority LOWEST
     */
    public function onMobDamageEvent(EntityDamageByEntityEvent $event): void
    {
        $entity = $event->getEntity();
        $damager = $event->getDamager();

        if ($entity instanceof EntityBase && $damager instanceof MMOPlayer) {
            $currentTick = $damager->getServer()->getTick();
            $lastDamageTick = $this->lastDamageTicks[$damager->getXuid()] ?? 0;

            if (($currentTick - $lastDamageTick) < 2) {
                $event->cancel();
            } else {
                $this->lastDamageTicks[$damager->getXuid()] = $currentTick;
            }
        }
    }

    /**
     * @param EntityDamageByEntityEvent $event
     * @priority HIGH
     */
    public function onEntityDamageByEntity(EntityDamageByEntityEvent $event): void
    {
        if (ServerManager::$draining) {
            $event->cancel();
            return;
        }

        $entity = $event->getEntity();
        $damager = $event->getDamager();

        $playerData = $this->getPlugin()->getPlayerData();
        if ($entity instanceof MMOPlayer && $damager instanceof MMOPlayer) {
            $entity->setAttackedEntity($damager);
            $damager->setAttackedEntity($entity);

            $faction1 = $playerData->getFaction($damager);
            $faction2 = $playerData->getFaction($entity);

            // Check if the associated faction is the same member, this shouldn't happen actually yes?
            if ($faction1 !== null && $faction2 !== null) {
                if ($faction1->getFactionId() === $faction2->getFactionId()) {
                    $damager->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . "You can't damage your own faction members.");
                    $event->cancel();

                    return;
                } else if ($faction1->isFactionAlly($faction2->getFactionId()) || $faction2->isFactionAlly($faction1->getFactionId())) {
                    $damager->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . "You can't damage your own faction allies.");
                    $event->cancel();

                    return;
                }
            } else if ($entity->getName() === $damager->getName()) {
                $event->cancel();

                return;
            }
        } else if ($entity instanceof Boss && $event->getCause() !== EntityDamageEvent::CAUSE_ENTITY_ATTACK) {
            if ($event->getCause() === EntityDamageEvent::CAUSE_PROJECTILE) {
                $event->setBaseDamage($event->getBaseDamage() / 2);
            } else {
                $event->cancel();

                return;
            }
        }

        $event->setKnockBack($event->getKnockBack() * 0.97);

        parent::onEntityDamageByEntity($event);
    }

    /**
     * @param EntityDeathEvent $event
     * @priority LOWEST
     */
    public function onEntityDeath(EntityDeathEvent $event): void
    {
        $entity = $event->getEntity();

        $lastDamager = $event->getEntity()->getLastDamageCause();
        if ($lastDamager instanceof EntityDamageByEntityEvent) {
            $damager = $lastDamager->getDamager();

            if (!($entity instanceof MMOPlayer) && $damager instanceof MMOPlayer) {
                $damager->getXpManager()->onPickupXp($entity->getXpDropAmount());

                $event->setDrops($damager->getInventory()->addItem(...$event->getDrops()));
                $event->setXpDropAmount(0);
            }
        }
    }

    /**
     * @param PlayerDeathEvent $event
     * @priority HIGHEST
     */
    public function onPlayerDeath(PlayerDeathEvent $event): void
    {
        /** @var MMOPlayer $player */
        $player = $event->getPlayer();
        $playerData = $this->getPlugin()->getPlayerData();
        $playerManager = $this->getPlugin()->getPlayerManager();
        $onlinePlayers = Server::getInstance()->getOnlinePlayers();

        $cause = $player->getLastDamageCause();

        $this->getPlugin()->getItemManager()->removeCooldown($player);
        $currentStreak = null;
        if (($cause instanceof EntityDamageByEntityEvent && ($damager = $cause->getDamager()) instanceof MMOPlayer) || (($damager = NGEssentials::getInstance()->getCombatLogger()->getLatestHit($player)) !== null && $damager instanceof MMOPlayer)) {
            $playerData->addKills($damager);

            $player->setCombatTimer(0);
            $damager->setCombatTimer(0);

            $faction1 = $playerData->getFaction($damager);
            $faction2 = $playerData->getFaction($player);

            // Check if the associated faction is the same member, this shouldn't happen actually yes?
            if ($faction1 !== null && $faction2 !== null && ($faction1->getFactionId() === $faction2->getFactionId() || $faction1->isFactionAlly($faction2->getFactionId()))) {
                return;
            }

            Server::getInstance()->broadcastMessage(MMOPlugin::getPrefix() . ($message = str_replace(['{PLAYER}', '{DAMAGER}'], ['§6' . NGEssentials::getInstance()->getPlayerManager()->getPlayerName($player), '§e' . NGEssentials::getInstance()->getPlayerManager()->getPlayerName($damager)], Utils::getRandomKillMessage($cause->getCause(), true))), $onlinePlayers);

            GlobalLogger::get()->info($message);

            // Both faction members do have the permission to drain/gain strength.
            Await::f2c(function () use ($faction1, $faction2, $damager, $player) {
                // Increase the damager factions strength and reduce the player that is killed strength.
                if ($faction2 !== null && $faction2->hasPermission($player, Faction::ALLOW_STRENGTH_MODIFIER)) {
                    $faction2->subtractFromStrength(15);

                    Database::executeSelect(Database::TRACK_FACTION_DEATHS, [
                        "faction_id" => $faction2->getFactionId(),
                        "player_name" => $player->getName(),
                        "current_epoch" => time()
                    ], yield);

                    $rows = yield Await::ONCE;

                    // Check if the system flagged the player as kicked
                    if (count($rows) > 0 && $rows[0]['status'] > 0) {
                        $player->sendMessage(Factions::getPrefix() . TextFormat::RED . "You have been automatically kicked for reaching {$faction2->getAutoKickDeaths()} of deaths/day. This restriction is set by the faction leader.");

                        $faction2->removeMember($player, true, true, false);
                    }
                }

                if ($faction1 !== null && $faction1->hasPermission($damager, Faction::ALLOW_STRENGTH_MODIFIER)) {
                    Database::executeChange(Database::TRACK_FACTION_KILLS, [
                        'faction_id' => $faction1->getFactionId(),
                        'player_name' => $damager->getName(),
                    ]);

                    Database::executeInsert(Database::UPDATE_FACTION_DEATHS, [
                        'faction_id' => $faction1->getFactionId(),
                        'player_name' => $player->getName(),
                    ], yield Await::RESOLVE_MULTI);

                    [1 => $affectedRows] = yield Await::ONCE;

                    if ($affectedRows > 0) {
                        $faction1->addStrength(5);
                    }
                }
            });

            // Kill streak purposes, broadcast message etc etc.
            if (($currentStreak = $streak = $playerData->getKillStreak($player)) > 0) {
                Server::getInstance()->broadcastMessage(MMOPlugin::getPrefix() . NGEssentials::getInstance()->getPlayerManager()->getPlayerName($player) . " lost their kill streak of $streak!", $onlinePlayers);

                $playerData->setKillStreak($player, 0);
            }

            $playerData->setKillStreak($damager, $streak = ($playerData->getKillStreak($damager) + 1));
            if ($playerData->getBestStreak($damager) < $streak) {
                $playerData->setBestStreak($damager, $streak);

                $damager->sendMessage(MMOPlugin::getPrefix() . "You've achieved a new best streak of $streak kills!");

                if ($streak % 10 === 0) {
                    Server::getInstance()->broadcastMessage(MMOPlugin::getPrefix() . NGEssentials::getInstance()->getPlayerManager()->getPlayerName($damager) . " has achieved their new best streak of $streak kills!", $onlinePlayers);
                }
            }

            // Claim the bounty from a player.
            Await::f2c(static function () use ($player, $damager, $playerData): Generator {
                $victim = $player->getXuid();
                $target = $damager->getXuid();

                Database::getMySQLDatabase()->executeImplRaw([
                    "UPDATE player_data SET bounty = (@bounty := bounty), bounty = 0 WHERE xuid = ?",
                    "UPDATE player_data SET coins = coins + @bounty WHERE xuid = ?",
                    "SELECT @bounty AS bounty"
                ], [[$victim], [$target], []], [
                    SqlThread::MODE_CHANGE,
                    SqlThread::MODE_CHANGE,
                    SqlThread::MODE_SELECT
                ], yield, yield Await::REJECT);

                /**
                 * @var SqlChangeResult $changeResult1
                 * @var SqlChangeResult $changeResult2
                 * @var SqlSelectResult $selectResult
                 */
                [$changeResult1, $changeResult2, $selectResult] = yield Await::ONCE;

                $bounty = $selectResult->getRows()[0]['bounty'] ?? 0;
                $actualResult = $changeResult1->getAffectedRows() + $changeResult2->getAffectedRows();
                if ($bounty > 0 && $actualResult > 0) {
                    $playerData->loadMoneyBalance($damager->getName());

                    Factions::getInstance()->getPlayerManager()->updateBountyScoreboard($player->getName(), 0);

                    $damager->sendMessage(MMOPlugin::getPrefix() . "You claimed {$player->getName()}'s " . ((int)$bounty) . " coins bounty!");
                }
            }, catches: function (Throwable $error): void {
                GlobalLogger::get()->logException($error);
            });

            // Claim the coins balance from a player.
            Await::f2c(static function () use ($player, $damager, $event, $playerData): Generator {
                if ($playerData->isAutoClaimEnabled($damager)) {
                    Database::getMySQLDatabase()->executeImplRaw([
                        "UPDATE player_data SET coins = coins - (@bounty := IF(coins < 1000, 0, (15 * coins / 100))) WHERE xuid = ?",
                        "UPDATE player_data SET coins = coins + @bounty WHERE xuid = ?",
                        "SELECT @bounty AS bounty"
                    ], [[$player->getXuid()], [$damager->getXuid()], []], [
                        SqlThread::MODE_CHANGE,
                        SqlThread::MODE_CHANGE,
                        SqlThread::MODE_SELECT,
                    ], yield, yield Await::REJECT);

                    /**
                     * @var SqlChangeResult $changeResult1
                     * @var SqlChangeResult $changeResult2
                     * @var SqlSelectResult $selectResult
                     */
                    [$changeResult1, $changeResult2, $selectResult] = yield Await::ONCE;

                    $bounty = $selectResult->getRows()[0]['bounty'] ?? 0;
                    $actualResult = $changeResult1->getAffectedRows() + $changeResult2->getAffectedRows();
                    if ($bounty > 0 && $actualResult > 0) {
                        $playerData->loadMoneyBalance($player->getName());
                        $playerData->loadMoneyBalance($damager->getName());

                        if ($damager->isConnected()) {
                            $damager->sendMessage(MMOPlugin::getPrefix() . "You claimed {$player->getName()}'s head. " . ((int)$bounty) . " coins has been added to your balance.");
                        }

                        if ($player->isConnected()) {
                            $player->sendMessage(MMOPlugin::getPrefix() . "Your head was collected.");
                            $player->sendMessage(MMOPlugin::getPrefix() . ((int)$bounty) . " coins from your balance was claimed with your head.");
                        }
                    } else if ($damager->isConnected()) {
                        $damager->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'The player is low in balance, therefore the player\'s head is not collected.');
                    }
                } else {
                    ItemStorage::createValidationId(CustomItemManager::getPlayerHead($player), 'kill-' . $damager->getName(), yield);
                    $head = yield Await::ONCE;

                    if ($damager->getInventory()->canAddItem($head)) {
                        $damager->getInventory()->addItem($head);

                        $damager->sendMessage(MMOPlugin::getPrefix() . "You collected {$player->getName()}'s head!");
                    } else {
                        $event->setDrops(array_merge($event->getDrops(), [$head]));

                        $damager->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Your inventory is full, so you couldn't collect {$player->getName()}'s head. It has been dropped for others to collect.");
                    }

                    $player->sendMessage(MMOPlugin::getPrefix() . 'Your head was collected.');
                }
            }, catches: function (Throwable $error): void {
                GlobalLogger::get()->logException($error);
            });

            $playerManager->updateKillsScoreboard($player);
            $playerManager->updateKillsScoreboard($damager);

            GroupManager::updateNameTag($damager);
        } else {
            $causeId = -1;
            if ($cause?->getCause() === EntityDamageEvent::CAUSE_ENTITY_ATTACK || $cause?->getCause() === EntityDamageEvent::CAUSE_PROJECTILE) {
                $causeId = EntityDamageEvent::CAUSE_CUSTOM;
            }

            Server::getInstance()->broadcastMessage(MMOPlugin::getPrefix() . str_replace('{PLAYER}', '§6' . NGEssentials::getInstance()->getPlayerManager()->getPlayerName($player), Utils::getRandomKillMessage($causeId)), $onlinePlayers);
        }

        try {
            $this->getPlugin()->getRollbackEngine()->handleListener($event, [
                'streak' => $currentStreak
            ]);
        } catch (Exception $error) {
            GlobalLogger::get()->error("RollbackEngine: Unable to handle listener for {$player->getName()}");
            GlobalLogger::get()->logException($error);
        }
    }

    /**
     * @param PlayerCreationEvent $event
     *
     * @priority HIGHEST
     */
    public function onPlayerCreation(PlayerCreationEvent $event): void
    {
        $event->setPlayerClass(MMOPlayer::class);
    }

    /**
     * @param EntityTrampleFarmlandEvent $event
     * @priority LOWEST
     */
    public function onTrampleCrop(EntityTrampleFarmlandEvent $event): void
    {
        $block = $event->getBlock();

        if (!Factions::isBadlands() && Area::isAreaInside($block->getPosition(), 'safezone')) {
            $event->cancel();
        }
    }

    /**
     * @param BlockUpdateEvent $event
     * @priority LOWEST
     */
    public function onBlockUpdate(BlockUpdateEvent $event): void
    {
        $block = $event->getBlock();

        if (!Factions::isBadlands() && Area::isAreaInside($block->getPosition(), 'safezone')) {
            $event->cancel();
        }
    }

    /**
     * @param PlayerInteractEvent $event
     * @priority LOW
     */
    public function onPlayerInteract(PlayerInteractEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $block = $event->getBlock();

        $ess = $this->getPlugin()->getEssentials();
        $itemManager = $this->getPlugin()->getItemManager();
        if ($ess !== null && $ess->getPlayerData()->getBool($player, NGPlayerData::TRANSFER)) {
            $event->cancel();
            return;
        }

        $cooldown = CooldownList::$interactable[$item->getTypeId()] ?? null;

        // Do not set cooldown for this item
        if ($cooldown !== null && $itemManager->hasCooldown($player, $item)) {
            $timeLeft = $itemManager->getCooldown($player, $item);

            $player->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . "You can't use this item right now. Time left: " . TextFormat::WHITE . date('i:s', $timeLeft));
        }

        if ($item->getTypeId() === VanillaItems::POTATO()->getTypeId() && !$itemManager->hasCooldown($player, $item) && $player->getWorld() === $player->getServer()->getWorldManager()->getDefaultWorld()) {
            $durability = BlockDurability::getDurability($block);

            if ($durability[0] > 0) {
                $player->sendMessage(MMOPlugin::getPrefix() . 'Block durability: (' . ($durability[0] - $durability[1]) . TextFormat::YELLOW . '/' . TextFormat::GRAY . $durability[0] . ')');
            }

            $itemManager->addCooldown($player, $item, 20);
        }

        if (!Factions::isBadlands() && Area::isAreaInside($block->getPosition()) && !$player->hasPermission(PlayerManager::PERMISSION_MODIFY)) {
            if ($event->getAction() === PlayerInteractEvent::LEFT_CLICK_BLOCK) {
                $event->cancel();
            } else {
                $notAllowed = $item instanceof ThrowableTNT
                    || $item instanceof GeneratorBucket;

                $books = [
                    FactionsItemRegistry::PLAYER_HEAD()->getTypeId(),
                    CustomItemRegistry::ENCHANTED_BOOK_COMMON()->getTypeId(),
                    CustomItemRegistry::ENCHANTED_BOOK_MYTHICAL()->getTypeId(),
                    CustomItemRegistry::ENCHANTED_BOOK_RARE()->getTypeId(),
                    CustomItemRegistry::ENCHANTED_BOOK_UNCOMMON()->getTypeId(),
                    CustomItemRegistry::ORB_OF_FLIGHT()->getTypeId(),
                    VanillaItems::POTION()->getTypeId()
                ];

                $allowedInteract = in_array($item->getTypeId(), $books) && !($block instanceof Transparent);

                if (!$allowedInteract || $notAllowed) {
                    $event->cancel();
                }
            }
        } else if (Factions::isBadlands()) {
            $notAllowed = $item instanceof ThrowableTNT
                || $item instanceof GeneratorBucket
                || $item instanceof PaintingItem
                || $item instanceof Hoe
                || $item instanceof Axe
                || $item instanceof Bucket
                || $item instanceof LiquidBucket
                || $item instanceof MilkBucket
                || $item instanceof Shovel
                || $item instanceof FlintSteel
                || $block instanceof Door
                || $block instanceof Trapdoor;

            if ($notAllowed) {
                $event->cancel();
            }
        }
    }

    /**
     * @param InventoryOpenEvent $event
     * @priority LOW
     */
    public function onInventoryOpenEvent(InventoryOpenEvent $event): void
    {
        $inventory = $event->getInventory();
        $player = $event->getPlayer();

        if ($inventory instanceof BlockInventory && !($inventory instanceof InvMenuInventory)) {
            if ($inventory instanceof EnchantInventory || $inventory instanceof AnvilInventory) {
                $event->cancel();
                return;
            }

            $factionId = $this->getPlugin()->getPlayerData()->getInt($player, PlayerData::FACTION_ID);

            $claim = $this->getPlugin()->getClaimManager()->getClaimInPosition($inventory->getHolder());
            $faction = $this->getPlugin()->getFactionManager()->getFaction($factionId);

            if ($claim !== null && ($faction === null || $faction->getFactionId() !== $claim->getFactionId() && !$faction->isFactionAlly($claim->getFactionId()) && !$faction->hasPermission($player, Faction::ALLOW_BASE_INTERACTION) && $faction->getStrength() < $claim->getStrength()) && !$player->hasPermission(Permissions::RANK_ADMIN)) {
                $player->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . "You can't open any containers in {$claim->getFactionName()}'s claim.");

                $event->cancel();
            }
        }
    }

    /**
     * @param PlayerMoveEvent $event
     * @priority LOWEST
     */
    public function onPlayerMoveEvent(PlayerMoveEvent $event): void
    {
        // Only do something if there is a definite x, y or z movement
        if (($event->getTo()->getFloorX() - $event->getFrom()->getFloorX()) === 0
            && ($event->getTo()->getFloorY() - $event->getFrom()->getFloorY()) === 0
            && ($event->getTo()->getFloorZ() - $event->getFrom()->getFloorZ()) === 0) {
            return;
        }

        $player = $event->getPlayer();
        $isTracking = NGEssentials::getInstance()->getPlayerData()->getBool($player, NGPlayerData::TRACK);

        if (!$player instanceof MMOPlayer || $isTracking) {
            return;
        }

        $world = $player->getWorld();
        $wild = $player->getServer()->getWorldManager()->getWorldByName('wild');

        /*
         * Only says something if there is a change in islands
         *
         * Situations:
         * islandTo == null && islandFrom != null - exit
         * islandTo == null && islandFrom == null - nothing
         * islandTo != null && islandFrom == null - enter
         * islandTo != null && islandFrom != null - same PlayerIsland or teleport?
         * islandTo == islandFrom
         */

        if (!Factions::isBadlands() && $wild !== null && $wild->getId() === $world->getId()) {
            $factionTo = $this->getPlugin()->getClaimManager()->getClaimInPosition($event->getTo());
            $factionFrom = $this->getPlugin()->getClaimManager()->getClaimInPosition($event->getFrom());

            $warzoneTo = Area::isAreaInside($event->getTo());
            $warzoneFrom = Area::isAreaInside($event->getFrom());

            $safeTo = Area::isAreaInside($event->getTo(), 'safezone');
            $safeFrom = Area::isAreaInside($event->getFrom(), 'safezone');

            if ($factionTo !== null && $factionFrom === null) {
                $player->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . "You are now entering " . $factionTo->getFactionName() . "'s claim.");
            } else if ($factionTo === null && $factionFrom !== null) {
                $player->sendPopup(MMOPlugin::getPrefix() . TextFormat::GREEN . "You are now leaving " . $factionFrom->getFactionName() . "'s claim.");

            } else if (!$warzoneTo && $warzoneFrom) {
                $player->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . 'You are currently leaving warzone');
            } else if ($warzoneTo && !$warzoneFrom) {
                $player->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . 'You are currently entering warzone');
            }

            if (!$safeTo && $safeFrom) {
                $player->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . 'Combat is enabled in this area.');
            } else if ($safeTo && !$safeFrom) {
                $player->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . 'Combat is disabled in this area.');
            }
        }

        if ($player->getAllowFlight() && !$this->getPlugin()->getPlayerManager()->canFly($player)) {
            FlyCommand::setFlying($player, false, false);

            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You cannot fly in the pvp arena.");
        }
    }

    /**
     * @param ProjectileHitBlockEvent $event
     * @priority MONITOR
     */
    public function onProjectileHitEvent(ProjectileHitBlockEvent $event): void
    {
        $entity = $event->getEntity();

        if ($entity instanceof Arrow && !$entity->isFlaggedForDespawn()) {
            (function () {
                $this->collideTicks = 1160;
            })->call($entity);
        }
    }
}