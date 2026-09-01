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

namespace skyblock\islands\listener;

use libMMO\entities\OptimizedItemEntity;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use libMMO\utils\AdventureSettingsObject;
use libMMO\utils\Permissions as MMOPermissions;
use libMMO\utils\trade\TradeManager;
use libVanilla\entity\EntityBase;
use NetherGames\NGEssentials\player\NGPlayer;
use NetherGames\NGEssentials\player\permissions\Permissions;
use NetherGames\NGEssentials\player\PlayerData;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\ItemFrame;
use pocketmine\block\Liquid;
use pocketmine\block\tile\Container;
use pocketmine\entity\Entity;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockFormEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockSpreadEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\EntityTrampleFarmlandEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\world\ChunkLoadEvent;
use pocketmine\inventory\PlayerInventory;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\sound\BlazeShootSound;
use skyblock\crates\CrateManager;
use skyblock\entities\helpers\MiniHelper;
use skyblock\islands\feature\block\BlockCounter;
use skyblock\islands\feature\IslandLevelSpec;
use skyblock\islands\Island;
use skyblock\islands\IslandManager;
use skyblock\utils\BaseListener;
use skyblock\utils\NonDespawnEntity;
use function array_filter;
use function count;

/**
 * Island listener base, unloading islands is no longer being handled
 * in this listener to provide consistence code flow.
 *
 * @package skyblock\island\listener
 */
class IslandListener extends BaseListener
{
    const MAX_ENTITIES_CHECK_THRESHOLD = 500;
    const MAX_ENTITIES_THRESHOLD = 200;

    /** @var IslandManager */
    private IslandManager $islandManager;

    public function __construct(IslandManager $manager)
    {
        parent::__construct($manager->getPlugin());

        $this->islandManager = $manager;
    }

    // Island world-level modification
    // v
    // LOWEST -> LOW -> NORMAL -> HIGH -> HIGHEST -> MONITOR
    //                                                     ^
    //                              Island monitoring events

    /**
     * @param EntitySpawnEvent $event
     * @priority HIGHEST
     */
    public function onEntitySpawnEvent(EntitySpawnEvent $event): void
    {
        $entity = $event->getEntity();
        $world = $entity->getWorld();
        $island = $this->getIslandManager()->getIslandByWorld($world);

        if ($island === null) {
            return;
        }

        if(count($entities = $world->getEntities()) >= self::MAX_ENTITIES_CHECK_THRESHOLD){
            $despawnableEntities = array_filter($entities, fn(Entity $entity) => (($entity instanceof EntityBase && !($entity instanceof NonDespawnEntity)) || $entity instanceof OptimizedItemEntity) && !$entity->isFlaggedForDespawn());
            if (count($despawnableEntities) >= self::MAX_ENTITIES_THRESHOLD) {
                foreach ($despawnableEntities as $entity) {
                    $entity->flagForDespawn();
                }

                foreach ($world->getPlayers() as $player) {
                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Your island reached the maximum entities count (>" . self::MAX_ENTITIES_CHECK_THRESHOLD . "). All entities has been cleared.");
                }
            }
        }

    }

    /**
     * @param EntityTrampleFarmlandEvent $event
     * @priority LOWEST
     */
    public function onPlayerTrampleCrops(EntityTrampleFarmlandEvent $event): void
    {
        $player = $event->getEntity();
        $island = $this->islandManager->getIslandByWorld($player->getWorld());

        if ($player instanceof MMOPlayer && $island !== null && !$island->hasPermission($player, Island::PERMISSION_BUILD)) {
            $event->cancel();
        }
    }

    /**
     * @param PlayerInteractEvent $event
     * @priority LOWEST
     */
    public function onPlayerInteract(PlayerInteractEvent $event): void
    {
        $player = $event->getPlayer();

        if (($leftClick = $event->getAction() === PlayerInteractEvent::LEFT_CLICK_BLOCK) || $event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
            $block = $event->getBlock();
            $island = $this->getIslandManager()->getIslandByWorld($block->getPosition()->getWorld());

            if ($island !== null) {
                if ($leftClick && $block instanceof ItemFrame){
                    if (!$island->hasPermission($player, Island::PERMISSION_INVENTORY) && !$player->hasPermission(Permissions::RANK_OWNER)) {
                        $event->cancel();
                        $player->sendPopup(TextFormat::RED . 'You do not have permission to take items from item frames on this island.');
                    }
                } else if (!$island->hasPermission($player, Island::PERMISSION_INTERACT) && !MMOPermissions::hasPermission($player)){
                    $event->cancel();
                    $player->sendPopup(TextFormat::RED . 'You do not have permission to interact on this island.');
                }
            }
        }
    }

    /**
     * @param EntityTeleportEvent $event
     * @priority LOWEST
     */
    public function onEntityTeleport(EntityTeleportEvent $event): void
    {
        $player = $event->getEntity();
        $target = $event->getTo()->getWorld();
        $origin = $event->getFrom()->getWorld();

        if ($origin->getId() !== $target->getId()
            && $player instanceof NGPlayer
            && !$this->getPlugin()->getEssentials()->getPlayerData()->getBool($player, PlayerData::TRACK)) {

            $targetIsland = $this->getIslandManager()->getIslandByWorld($target);
            if ($targetIsland !== null && $targetIsland->hasPermission($player, Island::PERMISSION_BUILD)) {
                AdventureSettingsObject::getInstance()->setBuildingPermission($player, true);
            } else {
                AdventureSettingsObject::getInstance()->setBuildingPermission($player, false);
            }
        }
    }

    /**
     * @param InventoryOpenEvent $event
     * @priority LOWEST
     */
    public function onInventoryOpen(InventoryOpenEvent $event): void
    {
        $player = $event->getPlayer();
        $world = $player->getWorld();

        $island = $this->getIslandManager()->getIslandByWorld($world);
        if ($island !== null
            && !$island->hasPermission($player, Island::PERMISSION_INVENTORY)
            && !MMOPermissions::hasPermission($player)
            && !TradeManager::getInstance()->isTradeInProgress($player)) {

            $event->cancel();
            $player->sendPopup(TextFormat::RED . 'You do not have permission to open inventories on this island.');
        }
    }

    /**
     * @param InventoryTransactionEvent $event
     * @priority LOWEST
     */
    public function onInventoryTransaction(InventoryTransactionEvent $event): void
    {
        $player = $event->getTransaction()->getSource();
        $world = $player->getWorld();

        $island = $this->getIslandManager()->getIslandByWorld($world);
        if ($island !== null
            && !$island->hasPermission($player, Island::PERMISSION_INVENTORY)
            && !$player->hasPermission(Permissions::RANK_OWNER)
            && !TradeManager::getInstance()->isTradeInProgress($player)) {

            $event->cancel();

            $cursorInventory = $player->getCursorInventory();
            $cursorInventory->setItem(0, $cursorInventory->getItem(0));
        }
    }

    /**
     * @param EntityShootBowEvent $event
     * @priority LOWEST
     */
    public function onEntityShootBow(EntityShootBowEvent $event): void
    {
        $entity = $event->getEntity();
        $island = $this->getIslandManager()->getIslandByWorld($entity->getWorld());

        if ($island !== null && $entity instanceof Player && !$island->isMember($entity)) {
            $event->cancel();
        }
    }

    /**
     * @param EntityDamageEvent $event
     * @priority LOWEST
     */
    public function onEntityDamage(EntityDamageEvent $event): void
    {
        $entity = $event->getEntity();
        $island = $this->getIslandManager()->getIslandByWorld($entity->getWorld());

        if ($island === null) {
            return;
        }

        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();

            if ($island->isPvPEnabled()) {
                if ($entity instanceof Player && !$island->isMember($entity)) {
                    $event->cancel();
                } elseif ($damager instanceof Player && !$island->isMember($damager)) {
                    if ($entity instanceof MiniHelper && MMOPermissions::hasPermission($damager)) {
                        return;
                    }
                    $event->cancel();
                }
            } elseif ($entity instanceof Player && !$damager instanceof EntityBase) {
                $event->cancel();

                if ($damager instanceof Player) {
                    $damager->sendPopup(TextFormat::RED . 'PvP is disabled on this island.');
                }
            }
        } elseif ($entity instanceof Player && $event->getCause() === EntityDamageEvent::CAUSE_VOID) {
            $event->cancel();
            $entity->teleport($island->getSpawnPosition());
        }

        if ($entity instanceof Player && !$island->isMember($entity)) {
            $event->cancel();
        }
    }

    /**
     * @param EntityItemPickupEvent $event
     * @priority LOWEST
     */
    public function onInventoryPickupItem(EntityItemPickupEvent $event): void
    {
        $inventory = $event->getInventory();

        if (($inventory instanceof PlayerInventory)
            && ($player = $inventory->getHolder()) instanceof Player
            && ($island = $this->getIslandManager()->getIslandByWorld($player->getWorld())) !== null
            && !$island->isMember($player)) {

            $event->cancel();
        }
    }

    /**
     * @param PlayerDropItemEvent $event
     * @priority LOWEST
     */
    public function onPlayerDropItem(PlayerDropItemEvent $event): void
    {
        $player = $event->getPlayer();

        if (($island = $this->getIslandManager()->getIslandByWorld($player->getWorld())) !== null && !$island->isMember($player)) {
            $event->cancel();
        }
    }

    /**
     * @param BlockPlaceEvent $event
     * @priority HIGHEST
     */
    public function onBlockPlace(BlockPlaceEvent $event): void
    {
        $player = $event->getPlayer();
        $world = $player->getWorld();

        $island = $this->getIslandManager()->getIslandByWorld($world);

        if ($island === null) {
            return;
        }

        foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
            $vec3 = new Vector3($x, $y, $z);

            if ($island->hasPermission($player, Island::PERMISSION_BUILD)) {
                $this->preventBuilding($event, $event->getPlayer(), $island->getXpLevelSpec(), $world->getSpawnLocation(), $vec3);
            } else {
                $event->cancel();
                $player->sendPopup(TextFormat::RED . 'You do not have permission to place blocks on this island.');
            }

            if ($event->isCancelled()) {
                return;
            }

            if (BlockCounter::needLogging($block) && !$island->getBlockCounter()->addBlock($block, $island->hasBlockExpansion())) {
                $player->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . "You have reached maximum number of " . $block->getName() . " block in this island.");

                $event->cancel();
            } else {
                $blockReplaced = $world->getBlock($vec3);
                if (BlockCounter::needLogging($blockReplaced)) {
                    $island->getBlockCounter()->removeBlock($blockReplaced);
                }
            }
        }
    }

    /**
     * @param BlockSpreadEvent $event
     * @priority MONITOR
     */
    public function onBlockSpread(BlockSpreadEvent $event): void
    {
        $block = $event->getBlock();

        if (BlockCounter::needLogging($block) && ($island = $this->getIslandManager()->getIslandByWorld($block->getPosition()->getWorld())) !== null) {
            $island->getBlockCounter()->removeBlock($block);
        }
    }

    /**
     * @param EntityExplodeEvent $event
     * @priority MONITOR
     */
    public function onEntityExplode(EntityExplodeEvent $event): void
    {
        $island = $this->getIslandManager()->getIslandByWorld($event->getEntity()->getWorld());

        if ($island !== null) {
            foreach ($event->getBlockList() as $block) {
                if (BlockCounter::needLogging($block)) {
                    $island->getBlockCounter()->removeBlock($block);
                }
            }
        }
    }

    /**
     * @param BlockBreakEvent $event
     * @priority HIGHEST
     */
    public function onBlockBreak(BlockBreakEvent $event): void
    {
        $world = $event->getBlock()->getPosition()->getWorld();
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $island = $this->getIslandManager()->getIslandByWorld($world);

        if ($island === null) {
            return;
        }

        if ($island->hasPermission($player, Island::PERMISSION_BUILD)) {
            if (!$island->hasPermission($player, Island::PERMISSION_INVENTORY) && $world->getTile($block->getPosition()) instanceof Container) {
                $event->cancel();
                $player->sendPopup(TextFormat::RED . 'You do not have permission to break this block.');

                return;
            }

            $this->preventBuilding($event, $event->getPlayer(), $island->getXpLevelSpec(), $world->getSpawnLocation(), $block->getPosition()->asVector3());

            if (!$event->isCancelled() && random_int(0, 250) === 1) {
                $player->getWorld()->addSound($player->getLocation()->asVector3(), new BlazeShootSound());

                $random = random_int(0, 100);

                if ($random < 50) {
                    // Common
                    $this->getPlugin()->getPlayerData()->increaseKey($player, CrateManager::COMMON);
                    $player->sendTitle(' ', MMOPlugin::getPrefix() . TextFormat::GRAY . 'You found a ' . TextFormat::YELLOW . 'Common Crate Key' . TextFormat::GRAY . ' while mining!', 0, 60, 20);
                } elseif ($random < 85) {
                    // Rare
                    $this->getPlugin()->getPlayerData()->increaseKey($player, CrateManager::RARE);
                    $player->sendTitle(' ', MMOPlugin::getPrefix() . TextFormat::GRAY . 'You found a ' . TextFormat::GOLD . 'Rare Crate Key' . TextFormat::GRAY . ' while mining!', 0, 60, 20);
                } else {
                    // Mythic
                    $this->getPlugin()->getPlayerData()->increaseKey($player, CrateManager::MYTHIC);
                    $player->sendTitle(' ', MMOPlugin::getPrefix() . TextFormat::GRAY . 'You found a ' . TextFormat::RED . 'Mythic Crate Key' . TextFormat::GRAY . ' while mining!', 0, 60, 20);
                }
            }
        } else {
            $event->cancel();
            $player->sendPopup(TextFormat::RED . 'You do not have permission to break blocks on this island.');
        }

        if (!$event->isCancelled() && BlockCounter::needLogging($block)) {
            $island->getBlockCounter()->removeBlock($event->getBlock());
        }
    }

    /**
     * @param BlockFormEvent $event
     * @priority HIGHEST
     */
    public function onBlockForm(BlockFormEvent $event): void
    {
        $world = $event->getBlock()->getPosition()->getWorld();
        $island = $this->getIslandManager()->getIslandByWorld($world);

        if ($island !== null && $event->getBlock() instanceof Liquid && $event->getNewState()->getTypeId() === BlockTypeIds::COBBLESTONE) {
            $world->setBlock($event->getBlock()->getPosition()->asVector3(), $island->getXpLevelSpec()->getCobbleWeightTable()->pickBlock());

            $event->cancel();
        }
    }

    private function preventBuilding(BlockPlaceEvent|BlockBreakEvent $event, Player $player, IslandLevelSpec $islandLevelSpec, Vector3 $spawn, Vector3 $target): void
    {
        if (!$islandLevelSpec->isAllowedArea($spawn, $target)) {
            $event->cancel();

            $areaLW = $islandLevelSpec->getAreaLengthWidth();
            $player->sendPopup(TextFormat::RED . "You are restricted to a $areaLW x $areaLW area on your island. Upgrade your island to get more space!");
        }
    }

    /**
     * @param ChunkLoadEvent $event
     * @priority LOWEST
     */
    public function onChunkLoad(ChunkLoadEvent $event): void
    {
        $world = $event->getWorld();

        if (($island = $this->getIslandManager()->getIslandByWorld($world)) !== null) {
            $island->onChunkLoad($world, $event->getChunkX(), $event->getChunkZ());
        }
    }

    private function getIslandManager(): IslandManager
    {
        return $this->islandManager;
    }
}