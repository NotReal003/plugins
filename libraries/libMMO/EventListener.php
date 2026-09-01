<?php
/**
 *   _ _ _     __  __ __  __  ____
 *  | (_) |   |  \/  |  \/  |/ __ \
 *  | |_| |__ | \  / | \  / | |  | |
 *  | | | '_ \| |\/| | |\/| | |  | |
 *  | | | |_) | |  | | |  | | |__| |
 *  |_|_|_.__/|_|  |_|_|  |_|\____/
 *
 * Copyright (C) 2016-2024 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder, Studgi
 */
declare(strict_types=1);

namespace libMMO;

use libMMO\commands\FlyCommand;
use libMMO\economy\shop\Shop;
use libMMO\entities\PlayerHead;
use libMMO\entities\projectile\TrackableArrow;
use libMMO\event\ChallengeUpdatedEvent;
use libMMO\event\PlayerDataSaveEvent;
use libMMO\forms\TpaForm;
use libMMO\item\CooldownList;
use libMMO\player\MMOPlayer;
use libMMO\player\PlayerData;
use libMMO\utils\BaseListener;
use libMMO\utils\Database;
use libMMO\utils\trade\TradeManager;
use libMMO\utils\Utils;
use muqsit\invmenu\inventory\InvMenuInventory;
use muqsit\invmenu\InvMenu;
use NetherGames\NGEssentials\events\NGJoinEvent;
use NetherGames\NGEssentials\events\NGPlayerTransferEvent;
use NetherGames\NGEssentials\events\NGRestartEvent;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\player\NGPlayer;
use NetherGames\NGEssentials\player\permissions\Permissions;
use NetherGames\NGEssentials\player\PlayerData as NGPlayerData;
use pocketmine\block\Anvil;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\inventory\BlockInventory;
use pocketmine\block\inventory\ChestInventory;
use pocketmine\block\inventory\DoubleChestInventory;
use pocketmine\block\inventory\EnderChestInventory;
use pocketmine\block\tile\Container;
use pocketmine\command\utils\CommandStringHelper;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Arrow as ArrowEntity;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockUpdateEvent;
use pocketmine\event\block\LeavesDecayEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\ProjectileHitBlockEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\inventory\BaseInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\EnderPearl;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function array_shift;
use function in_array;
use function str_contains;

abstract class EventListener extends BaseListener
{
    public const METADATA_SERVER_OBJECT = "key-server";
    public const COMBAT_BLOCKED_COMMANDS = [
        "friends",
        'gamemode',
        'give',
        'tp',
        'track',
        'effect'
    ];

    /** @var true[] */
    public static array $sellObjects;
    /** @var bool */
    public static bool $restarting = false;

    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct($plugin);

        self::$sellObjects = [];
    }

    /**
     * @param NGRestartEvent $restartEvent
     *
     * @priority LOWEST
     */
    public function onNGRestart(NGRestartEvent $restartEvent): void
    {
        self::$restarting = true;
        TradeManager::$tradesEnabled = false;
    }

    /**
     * @param NGJoinEvent $event
     *
     * @priority LOWEST
     */
    public function onNGJoin(NGJoinEvent $event): void
    {
        /** @var NGEssentials $ess */
        $ess = $this->getPlugin()->getEssentials();
        $player = $event->getPlayer();

        if (Database::isDatabaseOnline() && ($event->isPreLoaded() || NGEssentials::isInDevelopmentMode())) {
            $ess = $this->getPlugin()->getEssentials();
            $playerData = $ess->getPlayerData();
            $data = $playerData->getArray($player, NGPlayerData::FORWARD);

            if (!isset($data[self::METADATA_SERVER_OBJECT]) || $data[self::METADATA_SERVER_OBJECT] !== $this->getPlugin()->getEssentials()->getServerManager()->getServerType()) {
                $data = [];
            }

            unset($data[self::METADATA_SERVER_OBJECT]);

            $this->getPlugin()->getPlayerData()->loadData($player, $data);

            $playerData->unsetValue($player, NGPlayerData::FORWARD);
        } else {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'An unexpected error occurred while loading your data. Please try again later.');

            $ess->getPlayerManager()->forceTransfer($player);
        }
    }

    public function onChallengeUpdate(ChallengeUpdatedEvent $event): void
    {
        $this->getPlugin()->getPlayerManager()->updateChallengeScoreboard($event->getPlayer());
    }

    /**
     * @param InventoryOpenEvent $event
     *
     * @priority HIGHEST
     */
    public function onShopTransaction(InventoryOpenEvent $event): void
    {
        $player = $event->getPlayer();
        $container = $event->getInventory();

        if (!isset(self::$sellObjects[$player->getName()])) {
            return;
        }

        unset(self::$sellObjects[$player->getName()]);

        if ($container instanceof ChestInventory || $container instanceof DoubleChestInventory) {
            $sold = $total = 0;

            $items = [];
            foreach ($container->getContents() as $slot => $item) {
                $items[$slot] = $item;
                $price = Shop::getSellPrice($item) * $item->getCount();
                if ($price !== 0) {
                    $sold += $item->getCount();
                    $total += $price;
                    $items[$slot] = VanillaItems::AIR();
                }
            }

            $container->setContents($items);
            if ($total === 0) {
                $player->sendMessage(TextFormat::RED . "The chest don't have any items that can be sold.");
                return;
            }

            $this->getPlugin()->getEconomyManager()->increasePlayerMoney($player->getName(), $total, function () use ($player, $sold, $total) {
                if ($player->isConnected()) {
                    $player->sendMessage(TextFormat::GREEN . 'You sold ' . TextFormat::GOLD . number_format($sold) . TextFormat::GREEN . ' items for ' . TextFormat::GOLD . '$' . number_format($total));
                }
            });
        } else {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Invalid action, you can only sell items in a chest.");
        }

        $event->cancel();
    }

    /**
     * @param NGPlayerTransferEvent $event
     *
     * @priority HIGHEST
     */
    public function onNGPlayerTransfer(NGPlayerTransferEvent $event): void
    {
        /** @var MMOPlayer $player */
        $player = $event->getPlayer();
        $server = $event->getServer();

        if ($player->isCombatTimerActive()) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't transfer to another server while combat tagged.");
            $event->cancel();
            return;
        }

        if (!$player->isAlive()) {
            $event->cancel();
            return;
        }

        // Send Forward packet once, do not spam the socket.
        if (($ess = $this->getPlugin()->getEssentials()) !== null && $server->getCluster()->getServerType() === $ess->getServerManager()->getServerType() && !$ess->getPlayerData()->getBool($player, NGPlayerData::TRANSFER)) {
            $data = $this->getPlugin()->getPlayerData()->getData($player);
            $data[self::METADATA_SERVER_OBJECT] = $this->getPlugin()->getEssentials()->getServerManager()->getServerType();

            $ess->getPlayerData()->setValue($player, NGPlayerData::FORWARD, $data);
        }

        if (($defaultWorld = $player->getServer()->getWorldManager()->getDefaultWorld()) !== null) {
            $player->teleport($defaultWorld->getSpawnLocation());
        }

        $ess->getServerData()->getScoreBoard()->removePlayer($event->getPlayer());
    }

    /**
     * @param PlayerItemConsumeEvent $event
     *
     * @priority LOWEST
     */
    public function onPlayerItemConsume(PlayerItemConsumeEvent $event): void
    {
        $item = $event->getItem();
        /** @var MMOPlayer $player */
        $player = $event->getPlayer();

        $cooldown = CooldownList::$consumable[$typeId = $item->getTypeId()] ?? null;

        if ($cooldown !== null) {
            $itemManager = $this->getPlugin()->getItemManager();
            if ($itemManager->hasCooldown($player, $item)) {
                $timeLeft = $itemManager->getCooldown($player, $item);

                $player->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . "You can't use this item right now. Time left: " . TextFormat::WHITE . date('i:s', $timeLeft));

                $event->cancel();
                return;
            } else {
                $itemManager->addCooldown($player, $item, $cooldown);
            }
        }

        if ($typeId === ItemTypeIds::ENCHANTED_GOLDEN_APPLE) {
            $player->getEffects()->add(new EffectInstance(VanillaEffects::REGENERATION(), 600, 4));
        }
    }

    /**
     * @param BlockUpdateEvent $event
     *
     * @priority LOWEST
     */
    public function onBlockUpdate(BlockUpdateEvent $event): void
    {
        $block = $event->getBlock();

        if ($block->getPosition()->getWorld() === $block->getPosition()->getWorld()->getServer()->getWorldManager()->getDefaultWorld()) {
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
        $player = $event->getPlayer();

        if ($player->getWorld() === $player->getServer()->getWorldManager()->getDefaultWorld()) {
            $event->cancel();
        }
    }

    /**
     * @param PlayerDropItemEvent $event
     *
     * @priority LOWEST
     */
    public function onPlayerDropItem(PlayerDropItemEvent $event): void
    {
        $player = $event->getPlayer();

        if ($player->getWorld() === $player->getServer()->getWorldManager()->getDefaultWorld()) {
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
        $player = $event->getPlayer();

        if ($player->getWorld() === $player->getServer()->getWorldManager()->getDefaultWorld()) {
            $event->cancel();
        }
    }

    /**
     * @param ProjectileLaunchEvent $event
     *
     * @priority LOWEST
     */
    public function onProjectileLaunch(ProjectileLaunchEvent $event): void
    {
        $entity = $event->getEntity();

        if ($entity->getWorld() === $entity->getWorld()->getServer()->getWorldManager()->getDefaultWorld()) {
            $event->cancel();
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

        if ($entity->getWorld() === $entity->getWorld()->getServer()->getWorldManager()->getDefaultWorld()) {
            $event->cancel();
        }
    }

    /**
     * @priority LOWEST
     *
     * @param PlayerKickEvent $event
     */
    public function onPlayerKickEvent(PlayerKickEvent $event): void
    {
        $player = $event->getPlayer();

        if ($player instanceof MMOPlayer && $player->isCombatTimerActive() && (
                str_contains($reason = $event->getDisconnectReason(), 'Motion-A') ||
                str_contains($reason, 'Motion-B') ||
                str_contains($reason, 'Motion-C') ||
                str_contains($reason, 'Motion-D') ||
                str_contains($reason, 'Motion-E') ||
                str_contains($reason, 'Speed-A') ||
                str_contains($reason, 'Timer-A') ||
                str_contains($reason, 'AttackReach-A') ||
                str_contains($reason, 'Velocity-A') ||
                str_contains($reason, 'Velocity-B')
            )) {
            $player->setCombatTimer(0);
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
        $event->setKeepInventory(false);

        if ($player->getAllowFlight()) {
            FlyCommand::setFlying($player, false, false);
        }

        if ($player instanceof MMOPlayer) {
            $player->setCombatTimer(0);

            $cause = $player->getLastDamageCause();
            if ($cause instanceof EntityDamageByEntityEvent) {
                $damager = $cause->getDamager();

                if ($damager instanceof MMOPlayer) {
                    $damager->setCombatTimer(0);

                    $ess = $this->getPlugin()->getEssentials();
                    if ($ess === null || !$ess->getPlayerData()->getBool($player, NGPlayerData::TRANSFER)) {
                        $playerHead = new PlayerHead(Location::fromObject($player->getLocation()->add(0, 2, 0), $player->getWorld(), $player->getLocation()->getYaw()), null, $player, $event->getDrops(), true);
                        $playerHead->spawnToAll();
                    }

                    $event->setDrops([]);
                    return;
                }
            }

            $ess = $this->getPlugin()->getEssentials();
            if ($ess === null || !$ess->getPlayerData()->getBool($player, NGPlayerData::TRANSFER)) {
                $playerHead = new PlayerHead(Location::fromObject($player->getLocation()->add(0, 2, 0), $player->getWorld(), $player->getLocation()->getYaw()), null, $player, $event->getDrops());
                $playerHead->spawnToAll();
            }
        }

        $event->setDrops([]);
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
     * @param PlayerItemUseEvent $event
     *
     * @priority HIGHEST
     */
    public function onItemUseEvent(PlayerItemUseEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $ess = $this->getPlugin()->getEssentials();

        if ($ess !== null && $ess->getPlayerData()->getBool($player, NGPlayerData::TRANSFER)) {
            $event->cancel();
        }

        $cooldown = CooldownList::$usable[$item->getTypeId()] ?? null;

        if ($cooldown !== null) {
            if ($this->getPlugin()->getItemManager()->hasCooldown($player, $item)) {
                $timeLeft = $this->getPlugin()->getItemManager()->getCooldown($player, $item);

                $player->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . "You can't use this item right now. Time left: " . TextFormat::WHITE . date('i:s', $timeLeft));
                $event->cancel();
            } else {
                $this->getPlugin()->getItemManager()->addCooldown($player, $item, $cooldown);
            }
        }
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
                    if ($action instanceof SlotChangeAction && in_array(ItemTypeIds::toBlockTypeId(($item = $action->getSourceItem())->getTypeId()), [BlockTypeIds::MONSTER_SPAWNER, BlockTypeIds::MOB_HEAD], true) && $item->getCount() >= 10) {
                        if ($inventory instanceof BlockInventory) {
                            $holder = $inventory->getHolder();
                            if ($holder->isValid()) {
                                $this->getPlugin()->getLoggerStream()->add(
                                    $player->getName() . ' tried to select ' . $item->getCount() . 'x ' . TextFormat::clean($item->getCustomName()) . "s in a container (chest, furnace, etc.) inventory\n" .
                                    "Extra: world=" . $holder->getWorld()->getFolderName() . " x=" . $holder->getX() . " y=" . $holder->getY() . " z=" . $holder->getZ());
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
     * @priority MONITOR
     * @handleCancelled
     */
    public function onContainerOpenEvent(InventoryOpenEvent $event): void
    {
        $inv = $event->getInventory();
        $player = $event->getPlayer();

        if ($inv instanceof BaseInventory && $inv instanceof BlockInventory) {
            [$durabilityVL, $loreTileVL, $maxLore, $curseVL] = Utils::doInventoryCheck($inv->getContents());

            if (($holder = $inv->getHolder())->isValid() && ($durabilityVL > 0 || $loreTileVL > 1 || $curseVL > 8)) {
                $world = $holder->getWorld();
                $v = $holder->asVector3();

                $this->getPlugin()->getLoggerStream()->add("loreVL=$loreTileVL, maxLore=$maxLore, durabilityVL=$durabilityVL, curseVL=$curseVL, position={$v->getX()} {$v->getY()} {$v->getZ()}, player={$player->getName()}, {$world->getFolderName()}");
            }
        }
    }

    /**
     * @param PlayerInteractEvent $event
     *
     * @handleCancelled
     * @priority LOWEST
     */
    public function onPlayerTrackInteract(PlayerInteractEvent $event): void
    {
        $player = $event->getPlayer();
        $world = $event->getBlock()->getPosition()->getWorld();
        $tile = $world->getTile($event->getBlock()->getPosition());

        $isTracking = $this->getPlugin()->getEssentials()->getPlayerData()->getBool($player, NGPlayerData::TRACK);

        if ($tile instanceof Container && $isTracking && $player->hasPermission(Permissions::RANK_TRAINEE)) {
            $inventory = $tile->getInventory();

            $invMenu = InvMenu::create($inventory instanceof DoubleChestInventory ? MMOPlugin::MENU_CHEST_DOUBLE : MMOPlugin::MENU_CHEST_SINGLE);
            $invMenu->setListener(InvMenu::readonly());
            $invMenu->getInventory()->setContents($inventory->getContents());
            $invMenu->send($player);
        }
    }

    /**
     * @param PlayerInteractEvent $event
     *
     * @priority LOWEST
     */
    public function onPlayerInteract(PlayerInteractEvent $event): void
    {
        /** @var MMOPlayer $player */
        $player = $event->getPlayer();
        $item = $event->getItem();

        $ess = $this->getPlugin()->getEssentials();

        if ($ess !== null && $ess->getPlayerData()->getBool($player, NGPlayerData::TRANSFER)) {
            $event->cancel();
            return;
        }

        if ($event->getBlock() instanceof Anvil) {
            $event->cancel();
            return;
        }

        $cooldown = CooldownList::$interactable[$item->getTypeId()] ?? null;

        // Do not set cooldown for this item
        if ($cooldown !== null && $this->getPlugin()->getItemManager()->hasCooldown($player, $item)) {
            $timeLeft = $this->getPlugin()->getItemManager()->getCooldown($player, $item);

            $player->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . "You can't use this item right now. Time left: " . TextFormat::WHITE . date('i:s', $timeLeft));
        } elseif ($player->getWorld() === $player->getServer()->getWorldManager()->getDefaultWorld()) {
            $event->cancel();
        }
    }

    /**
     * @param ProjectileHitBlockEvent $event
     *
     * @priority NORMAL
     */
    public function onProjectileHitBlock(ProjectileHitBlockEvent $event): void
    {
        $entity = $event->getEntity();

        if ($entity instanceof TrackableArrow && $entity->getPickupMode() !== ArrowEntity::PICKUP_ANY) {
            $entity->flagForDespawn();
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

        if ($player->getWorld() === $player->getWorld()->getServer()->getWorldManager()->getDefaultWorld()) {
            $event->cancel();
        } elseif ($player->isCombatTimerActive()) {
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
        }
    }

    /**
     * @param CommandEvent $event
     *
     * @priority MONITOR
     */
    public function onCommand(CommandEvent $event): void
    {
        $player = $event->getSender();

        if (!($player instanceof MMOPlayer) || !$player->isCombatTimerActive()) {
            return;
        }

        if (empty($event->getCommand())) {
            return;
        }

        $args = CommandStringHelper::parseQuoteAware($event->getCommand());
        $commandMap = $player->getServer()->getCommandMap();
        $command = $commandMap->getCommand(array_shift($args) ?? "");

        if ($command !== null) {
            foreach (self::COMBAT_BLOCKED_COMMANDS as $blockedCommandName) {
                if ($command === $commandMap->getCommand($blockedCommandName)) {
                    $event->cancel();

                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't use this command while being combat tagged.");
                }
            }
        }
    }

    /**
     * @param PlayerQuitEvent $event
     *
     * @priority MONITOR
     */
    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        /** @var MMOPlayer $player */
        $player = $event->getPlayer();

        if ($player->isCombatTimerActive() && $this->getPlugin()->getServer()->isRunning()) {
            $player->kill();
        }

        $plugin = $this->getPlugin();
        $plugin->getItemManager()->removeCooldown($player);

        $plugin->getEssentials()->getServerData()->getScoreBoard()->removePlayer($event->getPlayer());

        $plugin->getPlayerData()->saveData($player, true);
        $plugin->getPlayerData()->addCallbackToPlayer($player->getName(), static function () use ($player): void {
            $server = NGEssentials::getInstance()->getServerManager()->getUniqueId();

            Database::executeChange(Database::PLAYER_UNLOCK_LOCATION, ['xuid' => $player->getXuid(), 'server' => $server]);
        }, true);
    }

    /**
     * @param EntityDamageByEntityEvent $event
     *
     * @priority HIGH
     */
    public function onEntityDamageByEntity(EntityDamageByEntityEvent $event): void
    {
        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if (
            $damager instanceof MMOPlayer &&
            ($victim instanceof MMOPlayer && $victim->getName() !== $damager->getName())
        ) {

            if (isset(TpaForm::$teleportCooldown[$victim->getName()])) {
                $event->cancel();

                $damager->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . "You cannot hurt this player after they've teleported.");
                $victim->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . 'The player is trying to attack you.');

                return;
            } else if (isset(TpaForm::$teleportCooldown[$damager->getName()])) {
                unset(TpaForm::$teleportCooldown[$damager->getName()]);
            }

            $damager->setCombatTimer(15);
            $victim->setCombatTimer(15);

            if ($damager->getAllowFlight()) {
                FlyCommand::setFlying($damager, false, false);
            }

            if ($victim->getAllowFlight()) {
                FlyCommand::setFlying($victim, false, false);
            }

            $serverManager = $this->getPlugin()->getEssentials()->getServerManager();

            if (($otherCluster = $serverManager->getQueuedCluster($damager)) !== null) {
                $otherCluster->removeFromQueue($damager);
            }

            if (($otherCluster = $serverManager->getQueuedCluster($victim)) !== null) {
                $otherCluster->removeFromQueue($victim);
            }
        }
    }

    /**
     * @param InventoryOpenEvent $event
     *
     * @priority LOWEST
     */
    public function onInventoryOpen(InventoryOpenEvent $event): void
    {
        $player = $event->getPlayer();

        if (!$event->getInventory() instanceof EnderChestInventory && $event->getInventory() instanceof BlockInventory && !$event->getInventory() instanceof InvMenuInventory && $player->getWorld() === $player->getServer()->getWorldManager()->getDefaultWorld()) {
            $event->cancel();
        }
    }

    /**
     * @param EntityShootBowEvent $event
     *
     * @priority NORMAL
     * @handleCancelled
     */
    public function onBowShoot(EntityShootBowEvent $event): void
    {
        $player = $event->getEntity();
        $bow = $event->getBow();
        $projectile = $event->getProjectile();

        if ($projectile instanceof ArrowEntity && $player instanceof Player) {
            $entity = new TrackableArrow($projectile->getLocation(), $projectile->getOwningEntity(), $projectile->isCritical());

            $entity->setMotion($player->getDirectionVector()->multiply(1.5));

            $infinity = $bow->hasEnchantment(VanillaEnchantments::INFINITY());
            if ($infinity) {
                $entity->setPickupMode(ArrowEntity::PICKUP_CREATIVE);
            }
            if (($punchLevel = $bow->getEnchantmentLevel(VanillaEnchantments::PUNCH())) > 0) {
                $entity->setPunchKnockback($punchLevel);
            }
            if (($powerLevel = $bow->getEnchantmentLevel(VanillaEnchantments::POWER())) > 0) {
                $entity->setBaseDamage($entity->getBaseDamage() + (($powerLevel + 1) / 2));
            }
            if ($bow->hasEnchantment(VanillaEnchantments::FLAME())) {
                $entity->setOnFire(intdiv($entity->getFireTicks(), 20) + 100);
            }

            $event->setProjectile($entity);
        }
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

        if ($origin->getId() !== $target->getId() && $player instanceof NGPlayer && $player->isConnected() && $player->getAllowFlight() && !$this->getPlugin()->getEssentials()->getPlayerData()->getBool($player, NGPlayerData::TRACK) && !$this->getPlugin()->getPlayerManager()->canFly($player, $target)) {
            FlyCommand::setFlying($player, false, false);
        }
    }

    /**
     * @param LeavesDecayEvent $event
     *
     * @priority LOWEST
     */
    public function onLeaveDecay(LeavesDecayEvent $event): void
    {
        $block = $event->getBlock();

        if ($block->getPosition()->getWorld() === $block->getPosition()->getWorld()->getServer()->getWorldManager()->getDefaultWorld()) {
            $event->cancel();
        }
    }

    /**
     * @param PlayerDataSaveEvent $event
     *
     * @priority LOWEST
     */
    public function onPlayerDataSave(PlayerDataSaveEvent $event): void
    {
        $player = $event->getPlayer();

        $this->getPlugin()->getPlayerData()->setValue($player, PlayerData::PROGRESS, $this->getPlugin()->getPlayerChallengeManager()->getPlayersChallengesAsArray($player));
    }
}
