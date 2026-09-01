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

namespace libMMO\utils\rollback;

use Closure;
use DateTime;
use Exception;
use Generator;
use GlobalLogger;
use Godruoyi\Snowflake\Sonyflake;
use JsonException;
use libforms\elements\Button;
use libforms\FormManager;
use libMMO\item\CustomItemRegistry;
use libMMO\MMOPlugin;
use libMMO\player\enchantment\EnchantmentManager;
use libMMO\player\Inventory;
use libMMO\player\MMOPlayer;
use libMMO\player\PlayerData;
use libMMO\utils\BaseClass;
use libMMO\utils\Database;
use libMMO\utils\InvestigationManager;
use libMMO\utils\Utils as NGUtils;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\player\social\PlayerSocialInfo;
use NetherGames\NGEssentials\player\social\SocialManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;
use SOFe\AwaitGenerator\Await;
use Throwable;
use function zstd_compress;

class RollbackEngine extends BaseClass
{
    public const SONYFLAKE_EPOCH_START = 1669138007000;
    public const CHANNEL_ROLLBACK = 'rollback';

    public const ROLLBACK_NAMED_TAG = '_c';     // Keep it "hidden"
    public const MAX_THEORETICAL_TAGS = 1000;   // Ensure that this feature is not abused.

    /** @var bool[] */
    public static array $illegalItems = []; // O(n) access time? TODO: Implement BST in the future.
    /** @var Closure[] */
    private array $listeners = [];

    private Sonyflake $sonyflake;

    public function __construct(MMOPlugin $instance)
    {
        parent::__construct($instance);

        $this->sonyflake = new Sonyflake(NGUtils::generateMachineId());
        $this->sonyflake->setStartTimeStamp(self::SONYFLAKE_EPOCH_START);

        $instance->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(function () {
            Database::executeGeneric(Database::BACKUP_DELETE_EXPIRED_DATA);
        }), 5 * 20, 30 * 60 * 20);

        Database::executeSelect(Database::BACKUP_GET_IDS, onSelect: function (array $rows): void {
            foreach ($rows as $row) {
                self::$illegalItems[$row['id']] = true;
            }
        });

        Database::getMySQLDatabase()->waitAll();

        $instance->getEventEmitter()->addListener(function (int $notificationId, string $channel, string $payload) {
            if ($channel !== self::CHANNEL_ROLLBACK) {
                return;
            }

            if (!isset(self::$illegalItems[$payload])) {
                self::$illegalItems[$payload] = true;
            }
        });
        $this->addRollbackListener(function (string $player, string $targetXuid, array $inventoryData) use ($instance): void {
            self::$illegalItems[$inventoryData['rollbackId']] = true;

            $instance->getEventEmitter()->publishEvent($inventoryData['rollbackId'], 0, self::CHANNEL_ROLLBACK);
        });
    }

    /**
     * Listen to rollback changes done in this server.
     *
     * @param Closure $consumer
     */
    public function addRollbackListener(Closure $consumer): void
    {
        Utils::validateCallableSignature($consumer, function (string $player, string $targetXuid, array $inventoryData): void {});

        $this->listeners[] = $consumer;
    }

    public static function isIllegalItem(Item $item): bool
    {
        $tag = $item->getNamedTag()->getCompoundTag(RollbackEngine::ROLLBACK_NAMED_TAG);
        $nbt = $tag ?? new CompoundTag();
        $rollbackTags = $nbt->getListTag(RollbackEngine::ROLLBACK_NAMED_TAG) ?? new ListTag();

        /** @var StringTag $stringTag */
        foreach ($rollbackTags as $stringTag) {
            if (isset(self::$illegalItems[$stringTag->getValue()])) {
                return true;
            }
        }

        // Remove items that are not in the MMO server.
        foreach ($item->getEnchantments() as $instance) {
            $enchantment = $instance->getType();

            // Purposefully removed the knockback condition, because the item will be removed anyway if this first
            // condition is met.
            if (EnchantmentManager::isEnchantExcluded($enchantment)){
                return true;
            }
        }

        return false;
    }

    /**
     * Handle player's death event and store them into database.
     *
     * @throws Exception
     */
    public function handleListener(PlayerDeathEvent $event, array $extraData = []): void
    {
        /** @var MMOPlayer $player */
        $player = $event->getPlayer();

        $cause = $player->getLastDamageCause();
        $causeId = $cause === null ? -1 : $cause->getCause();
        if ($cause instanceof EntityDamageByEntityEvent) {
            $damager = $cause->getDamager();
        } else {
            $damager = NGEssentials::getInstance()->getCombatLogger()->getLatestHit($player);
        }

        // Do not save anything if the player inventory was empty.
        $inventory = $player->getInventory();
        $armorInventory = $player->getArmorInventory();
        if (empty($inventory->getContents()) && empty($armorInventory->getContents())) {
            return;
        }

        $inv = array_filter($inventory->getContents(), self::onlyOneStack());
        $armorInv = array_filter($armorInventory->getContents(), self::onlyOneStack());

        $contentUniqueId = $this->sonyflake->id();
        Await::f2c(function () use ($player, $inv, $armorInv, $contentUniqueId, $damager, $causeId, $extraData): Generator {
            $compressedItems = zstd_compress(json_encode([Inventory::convertItemsToJson($inv), Inventory::convertItemsToJson($armorInv)]));
            $combatLogger = false;
            if (!$player->isConnected()) {
                $combatLogger = true;
            }

            Database::executeInsert(Database::BACKUP_ADD_INVENTORY_DATA, [
                'playerName' => $player->getName(),
                'inventory' => $compressedItems,
                'deathCause' => json_encode(array_merge([
                    'rollbackId' => $contentUniqueId,
                    'isCombatLogger' => $combatLogger,
                    'deathCause' => $causeId,
                    'deathCauseBy' => ($damager instanceof Player ? $damager->getName() : 'Self'),
                    'deathCauseXuid' => ($damager instanceof Player ? $damager->getXuid() : -1),
                    'deathCauseLocation' => $this->getPlugin()->getEssentials()->getServerManager()->getUniqueId(),
                ], $extraData)),
                'itemCount' => count($inv) + count($armorInv)
            ], yield, yield Await::REJECT);

            yield Await::ONCE;
        }, catches: function (Throwable $error) {
            $this->getPlugin()->getLogger()->logException($error);
        });

        $processedDrops = [];
        foreach ($event->getDrops() as $drop) {
            // Do not add this to the ids to item with stack size more than 1.
            // Usually item stack size more than 1 is typically an armor, swords, tools.
            if ($drop->getMaxStackSize() > 1) {
                $processedDrops[] = $drop;
                continue;
            }

            $tag = $drop->getNamedTag()->getCompoundTag(self::ROLLBACK_NAMED_TAG);
            $nbt = $tag ?? new CompoundTag();
            $rollbackTags = $nbt->getListTag(RollbackEngine::ROLLBACK_NAMED_TAG) ?? new ListTag();
            $rollbackTags->push(new StringTag($contentUniqueId));

            $nbt->setTag(RollbackEngine::ROLLBACK_NAMED_TAG, $rollbackTags);

            if (count($rollbackTags->getAllValues()) > RollbackEngine::MAX_THEORETICAL_TAGS) {
                $this->getPlugin()->getLogger()->warning("Maximum theoretical tags reached for item " . $drop->getName() . " | " . $drop->getCustomName());
            } else {
                $drop->getNamedTag()->setTag(self::ROLLBACK_NAMED_TAG, $nbt);

                $processedDrops[] = $drop;
            }
        }

        $event->setDrops($processedDrops);
    }

    /**
     * Restore given inventory.
     */
    private function rollbackInventory(Player $player, array $inventory, array $armorInventory, array $extraData, array $inventoryEntry): void
    {
        Await::f2c(function () use ($player, $inventory, $armorInventory, $extraData, $inventoryEntry) {
            $serverManager = NGEssentials::getInstance()->getServerManager();
            $targetName = $inventoryEntry['player_name'];
            $targetXuid = $inventoryEntry['xuid'];

//            if ($player->getName() === $targetName) {
//                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You cannot revert your own inventory!");
//                return;
//            }

            Database::executeSelect(Database::BACKUP_GET_PLAYER_INVENTORY, [
                'xuid' => $targetXuid
            ], yield, yield Await::REJECT);

            $results = yield Await::ONCE;

            if (empty($results)) {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Something went wrong, player could not be found in database.');
                return;
            }

            $inventoryRaw = Inventory::convertStringToInventoryJSON($results[0]['inventory']);

            SocialManager::requestPlayerInfo($targetName, yield);

            /** @var ?PlayerSocialInfo $info */
            $info = yield Await::ONCE;

            $isOnline = false;
            $onlineAt = null;
            if ($info !== null && str_contains($serverUniqueId = $info->location, $serverManager->getServerType()) && $serverManager->getUniqueId() !== $serverUniqueId) {
                $isOnline = true;
                $onlineAt = $serverUniqueId;
            }

            if (!$isOnline) {
                if (($target = Server::getInstance()->getPlayerExact($targetName)) !== null) {
                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "The player is currently online in this server, clearing the player's inventory to securely rollback the items.");

                    $invItems = Inventory::convertJsonToContents($inventory);
                    $armorItems = Inventory::convertJsonToContents($armorInventory);

                    $target->getInventory()->setContents($invItems);
                    $target->getArmorInventory()->setContents($armorItems);
                } else {
                    $inventoryRaw[0] = $inventory;
                    $inventoryRaw[1] = $armorInventory;

                    Database::executeInsert(Database::BACKUP_UPDATE_PLAYER_INVENTORY, [
                        'xuid' => $targetXuid,
                        'inventory' => Inventory::convertInventoryJSONToString($inventoryRaw[0], $inventoryRaw[1], $inventoryRaw[2]),
                    ], yield, yield Await::REJECT);

                    yield Await::ONCE;
                }

                Database::executeInsert(Database::BACKUP_INSERT_IDS, ['id' => $extraData['rollbackId']], yield, yield Await::REJECT);
                Database::executeInsert(Database::BACKUP_UPDATE_STATUS, ['id' => $inventoryEntry['inventory_id']], yield, yield Await::REJECT);
                yield Await::ALL;

                foreach ($this->listeners as $listener) {
                    $listener($targetName, $targetXuid, $extraData);
                }

                GlobalLogger::get()->info("Staff {$player->getName()} executed rollback for $targetName entry " . $inventoryEntry['inventory_id']);

                $player->sendMessage(MMOPlugin::getPrefix() . 'Rollback action successful for player ' . $targetName . ', reverted inventory id #' . $inventoryEntry['inventory_id']);
            } else {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Cannot rollback $targetName's inventory, player is currently online at $onlineAt");
            }
        }, catches: function (Throwable $error) use ($inventoryEntry, $player): void {
            GlobalLogger::get()->error("RollbackEngine: Failed to execute rollback for {$inventoryEntry['player_name']}");
            GlobalLogger::get()->logException($error);

            if ($player->isConnected()) {
                $player->sendMessage(TextFormat::RED . MMOPlugin::getPrefix() . "Failed to execute rollback for {$inventoryEntry['player_name']}, please contact an administrator.");
            }
        });
    }

    /**
     * Attempt to list all inventory of the given {@code $playerName} and send them
     * to the sender, this will return the list in simple form format, the list then be
     * converted into inventory visual representation of that rollback.
     *
     * @param Player $sender The player requested to view player's history
     * @param string $playerName The target player.
     */
    public static function loadInventoryHistory(Player $sender, string $playerName): void
    {
        Await::f2c(function () use ($sender, $playerName) {
            Database::executeSelect(Database::BACKUP_GET_PLAYER_ENTRIES, [
                'playerName' => $playerName
            ], yield, yield Await::REJECT);

            $results = yield Await::ONCE;

            if (!$sender->isConnected()) {
                return;
            }

            if (empty($results)) {
                $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'The player with that name doesn\'t appear to have any saved backups, check the name again.');
                return;
            }

            if (($form = FormManager::createSimpleForm($sender)) === null) {
                $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Something went wrong while trying to create a form, try again later");
                return;
            }

            $form->setTitle(MMOPlugin::getPrefix() . TextFormat::BLACK . 'Select backup entry');
            $form->setContent(TextFormat::GRAY . 'You are now currently selecting inventory backup points for the player ' . TextFormat::AQUA . $playerName . TextFormat::GRAY . '.' . TextFormat::EOL);
            foreach ($results as $row) {
                $dateTime = new DateTime();
                $dateTime->setTimestamp($row['death_time']);

                try {
                    $extraData = json_decode($row['death_cause'], true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    continue;
                }

                $inventoryId = $row['inventory_id'];
                $form->addButton(new Button(TextFormat::RED . TextFormat::BOLD . $extraData['deathCauseBy'] . TextFormat::EOL . TextFormat::RESET . TextFormat::GRAY . $dateTime->format('Y-m-d H:i:s'), function (Player $sender) use ($inventoryId, $extraData): void {
                    Await::f2c(function () use ($sender, $extraData, $inventoryId): Generator {
                        Database::executeSelect(Database::BACKUP_GET_INVENTORY_ID, [
                            'inventory_id' => $inventoryId
                        ], yield, yield Await::REJECT);

                        $rows = yield Await::ONCE;

                        if (!$sender->isConnected()) {
                            return;
                        }

                        if (count($rows) > 0) {
                            $entry = $rows[0];

                            try {
                                [$inventory, $armorInventory] = json_decode(zstd_uncompress($entry['inventory']), true, 512, JSON_THROW_ON_ERROR);
                            } catch (JsonException) {
                                $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Something went wrong while attempting to execute the rollback action.');
                                return;
                            }

                            $playerName = $entry['player_name'];

                            $dateTime = new DateTime();
                            $dateTime->setTimestamp($entry['death_time']);

                            $isClosed = false;

                            $invMenu = InvMenu::create(MMOPlugin::MENU_CHEST_DOUBLE);
                            $invMenu->setListener(InvMenu::readonly(static function (DeterministicInvMenuTransaction $transaction) use (&$isClosed, $entry, $sender, $playerName, $inventory, $extraData, $armorInventory): void {
                                $statusHolder = $transaction->getItemClicked();
                                $blockData = $statusHolder->getCustomBlockData();

                                if ($blockData !== null && $blockData->getTag(self::ROLLBACK_NAMED_TAG) instanceof IntTag) {
                                    $isClosed = true;

                                    $sender->removeCurrentWindow();

                                    if ($blockData->getInt(self::ROLLBACK_NAMED_TAG) === 1) {
                                        MMOPlugin::getInstance()->getRollbackEngine()->rollbackInventory($sender, $inventory, $armorInventory, $extraData, $entry);
                                    } else {
                                        self::loadInventoryHistory($sender, $playerName);
                                    }
                                }
                            }));
                            $invMenu->setInventoryCloseListener(static function (Player $player) use (&$isClosed, $playerName): void {
                                if (!$isClosed) {
                                    self::loadInventoryHistory($player, $playerName);
                                }
                            });

                            $contents = Inventory::convertJsonToContents($inventory);
                            foreach (Inventory::convertJsonToContents($armorInventory) as $slot => $item) {
                                $contents[InvestigationManager::ARMOR_INVENTORY_START_OFFSET + $slot] = $item;
                            }

                            for ($i = 36; $i <= 44 || ($i < 49 && $i = 49); $i++) {
                                $contents[$i] = InvestigationManager::getBarrierItem();
                            }

                            $lore = [
                                TextFormat::RESET,
                                TextFormat::RESET . TextFormat::GOLD . 'Killed by: ' . TextFormat::GRAY . $extraData['deathCauseBy'],
                                TextFormat::RESET . TextFormat::GOLD . 'Death cause: ' . TextFormat::GRAY . self::getDeathCause($extraData['deathCause'] ?? -1),
                                TextFormat::RESET . TextFormat::GOLD . 'Combat logged: ' . ($extraData['isCombatLogger'] ? TextFormat::RED . "true" : TextFormat::GRAY . "false"),
                                TextFormat::RESET . TextFormat::GOLD . 'Kill streak: ' . TextFormat::GRAY . ($extraData['streak'] ?? TextFormat::RED . 'NA'),
                                TextFormat::RESET . TextFormat::GOLD . 'Timestamp: ' . TextFormat::GRAY . $dateTime->format('Y-m-d H:i:s')
                            ];

                            if ($entry['has_executed']) {
                                $lore = array_merge($lore, [TextFormat::RESET . TextFormat::RED . "Contents has been rolled back."]);
                            }

                            $lore = array_merge($lore, [TextFormat::RESET, TextFormat::RESET . TextFormat::GRAY . 'Rollback #' . $entry['inventory_id']]);
                            $contents[50] = CustomItemRegistry::TRADE_ICON_INFORMATION()
                                ->setCustomName(TextFormat::RESET . TextFormat::RED . TextFormat::BOLD . $playerName)
                                ->setLore($lore);

                            $contents[52] = CustomItemRegistry::TRADE_BUTTON_DENY()
                                ->setCustomName(TextFormat::RESET . TextFormat::RED . TextFormat::BOLD . 'Cancel rollback')
                                ->setCustomBlockData(CompoundTag::create()
                                    ->setInt(self::ROLLBACK_NAMED_TAG, 0));

                            $contents[53] = CustomItemRegistry::TRADE_BUTTON_ACCEPT()
                                ->setCustomName(TextFormat::RESET . TextFormat::GREEN . TextFormat::BOLD . 'Accept rollback')
                                ->setCustomBlockData(CompoundTag::create()
                                    ->setInt(self::ROLLBACK_NAMED_TAG, 1));

                            $inventory = $invMenu->getInventory();
                            $inventory->setContents($contents);

                            $invMenu->send($sender);
                        }
                    }, catches: Database::getFailClosure());
                }));
            }

            $form->sendForm();
        }, catches: function (Throwable $error) use ($sender, $playerName): void {
            GlobalLogger::get()->error("RollbackEngine: Failed to load rollback history for $playerName");
            GlobalLogger::get()->logException($error);

            if ($sender->isConnected()) {
                $sender->sendMessage(TextFormat::RED . MMOPlugin::getPrefix() . "Failed to execute rollback for $playerName, please contact an administrator.");
            }
        });
    }

    public function claimRollbackItems(Player $player): void
    {
        $content = $this->getPlugin()->getPlayerData()->getString($player, PlayerData::ROLLBACK_INVENTORY);
        if (empty($content)) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "There are no pending items to restore.");
            return;
        }

        try {
            $rollbackItems = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            $items = []; // Actual rollback
            foreach ($rollbackItems as $itemRaw) {
                $item = NGUtils::decodeItem($itemRaw);

                if (!NGUtils::isReadOnlyItem($item)) {
                    $items[] = $item;
                }
            }

            $rawItem = []; // Residue items
            foreach ($player->getInventory()->addItem(...$items) as $item) {
                $rawItem[] = NGUtils::zlibEncodeItem($item);
            }

            if (!empty($rawItem)) {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Your inventory is full, certain items cannot be added to your inventory.");
            } else {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . "Your rollback items has been added into your inventory.");
            }

            $this->getPlugin()->getPlayerData()->setValue($player, PlayerData::ROLLBACK_INVENTORY, json_encode($rawItem, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "An error has occurred while trying to restore your items, contact an administrator.");
        }
    }

    public function openRollbackStorage(MMOPlayer $player): void
    {
        $content = $this->getPlugin()->getPlayerData()->getString($player, PlayerData::ROLLBACK_INVENTORY);
        if (empty($content)) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "There are no pending items to restore.");
            return;
        }

        $invMenu = InvMenu::create(MMOPlugin::MENU_CHEST_DOUBLE);
        $invMenu->setListener(InvMenu::readonly());
        $invMenu->setName($player->getName() . "'s Rollback Storage");

        try {
            $rollbackItems = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            $items = []; // Actual rollback
            foreach ($rollbackItems as $itemRaw) {
                $item = NGUtils::decodeItem($itemRaw);

                if (!NGUtils::isReadOnlyItem($item)) {
                    $items[] = $item;
                }
            }

            $invMenu->getInventory()->addItem(...$items);
        } catch (JsonException) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "An error has occurred while trying to restore your items, contact an administrator.");
        }

        $invMenu->send($player);
    }

    private static function onlyOneStack(): Closure
    {
        return function (Item $item): bool {
            return $item->getMaxStackSize() === 1;
        };
    }

    private static function getDeathCause(int $cause): string
    {
        return match ($cause) {
            EntityDamageEvent::CAUSE_SUICIDE, -1 => 'Suicide',
            EntityDamageEvent::CAUSE_CONTACT => 'Contact',
            EntityDamageEvent::CAUSE_ENTITY_ATTACK => 'Entity attack',
            EntityDamageEvent::CAUSE_PROJECTILE => 'Projectile entity',
            EntityDamageEvent::CAUSE_SUFFOCATION => 'Suffocation',
            EntityDamageEvent::CAUSE_FIRE, EntityDamageEvent::CAUSE_FIRE_TICK, EntityDamageEvent::CAUSE_LAVA => 'Burned to death',
            EntityDamageEvent::CAUSE_DROWNING => 'Drowned',
            EntityDamageEvent::CAUSE_BLOCK_EXPLOSION, EntityDamageEvent::CAUSE_ENTITY_EXPLOSION => 'Explosion',
            EntityDamageEvent::CAUSE_VOID => 'Void',
            EntityDamageEvent::CAUSE_MAGIC => 'Magic',
            EntityDamageEvent::CAUSE_CUSTOM => 'Custom modifiers',
            EntityDamageEvent::CAUSE_STARVATION => 'Starvation',
            default => 'Unknown',
        };
    }
}