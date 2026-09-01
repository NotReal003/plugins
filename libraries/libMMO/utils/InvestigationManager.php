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

namespace libMMO\utils;

use Closure;
use Generator;
use JsonException;
use libforms\elements\Button;
use libforms\elements\Label;
use libforms\FormManager;
use libforms\SimpleForm;
use libMMO\item\CustomItemRegistry;
use libMMO\MMOPlugin;
use libMMO\player\Inventory;
use libMMO\player\MMOPlayer;
use libMMO\player\PlayerData;
use libMMO\player\PlayerManager;
use libMMO\utils\inventory\SharedInventory;
use libMMO\utils\inventory\VaultStorage;
use libMMO\utils\Permissions as MMOPermissions;
use libMMO\utils\rollback\RollbackEngine;
use libMMO\vaults\VaultEntry;
use LogicException;
use muqsit\invmenu\inventory\InvMenuInventory;
use muqsit\invmenu\InvMenu;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\player\PlayerData as NGPlayerData;
use NetherGames\NGEssentials\player\social\PlayerSocialInfo;
use NetherGames\NGEssentials\player\social\SocialManager;
use pocketmine\block\inventory\EnderChestInventory;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\utils\ExperienceUtils;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\BaseInventory;
use pocketmine\inventory\Inventory as PMInventory;
use pocketmine\inventory\PlayerCursorInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Armor;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;
use function str_contains;

/**
 * An advanced inventory investigation that surpasses SkyBlock inventory check system.
 * This system will allow staff to modify a player inventory without any errors.
 */
class InvestigationManager extends BaseClass implements Listener
{
    public const ARMOR_INVENTORY_START_OFFSET = 45;
    public const ARMOR_INVENTORY_MENU_SLOTS = [
        ArmorInventory::SLOT_HEAD => 46,
        ArmorInventory::SLOT_CHEST => 48,
        ArmorInventory::SLOT_LEGS => 50,
        ArmorInventory::SLOT_FEET => 52
    ];

    private const INVENTORY_MODE = 0;
    private const ENDERCHEST_MODE = 1;

    /** @var VaultStorage[][] */
    private array $vaultStorage = [];

    /** @var InvMenu[] */
    private array $players = [];                // The player xuid, used for "inventory" checking
    /** @var int[] */
    private array $playersMode = [];            // The player investigate mode
    /** @var Player[]|null[] */
    private array $xuidToPlayer = [];           // The player to xuid translation
    /** @var SharedInventory[] */
    private array $sharedInventories = [];      // The player linked transactions object, PlayerInv -> InvMenu
    /** @var string[] */
    private array $staffs = [];                 // Staff that is using the inventory thing.
    /** @var string[] */
    private array $offlineObject = [];          // An array returning for god’s sake the inventory hash before player is online.

    public function __construct(MMOPlugin $instance)
    {
        parent::__construct($instance);

        $instance->getServer()->getPluginManager()->registerEvents($this, $instance);
    }

    private static function getServerType(): string
    {
        return NGEssentials::getInstance()->getServerManager()->getServerType();
    }

    /**
     * @param MMOPlayer $player
     * @param string $playerName
     */
    public function sendInvestigationForm(MMOPlayer $player, string $playerName): void
    {
        Await::f2c(function () use ($player, $playerName): Generator {
            if (($target = $this->getPlugin()->getEssentials()->getPlayerManager()->getBestMatchingPlayer($playerName)) instanceof Player) {
                $this->sendBaseForm($player, $target->getName());

                return;
            }

            PlayerManager::getPlayerAlike($playerName, yield);

            /** @var string[] $players */
            $players = yield Await::ONCE;

            if (empty($players)) {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'There is no such player named as ' . TextFormat::YELLOW . $playerName . TextFormat::RED . ' recorded in our database, please check the name again.');
                return;
            }

            if (count($players) > 1) {
                SocialManager::requestPlayerInfos($players, yield);

                /** @var (?PlayerSocialInfo)[] $infos */
                $infos = yield Await::ONCE;

                if (($form = FormManager::createSimpleForm($player)) === null || !$player->isConnected()) {
                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Something went wrong while trying to create a form, try again later");
                    return;
                }

                $form->setTitle(MMOPlugin::getPrefix() . TextFormat::BLACK . "Select a player");
                foreach ($infos as $playerName => $info) {
                    if ($info !== null && str_contains($info->location, self::getServerType())) {
                        $form->addButton(new Button(TextFormat::DARK_GRAY . $playerName . TextFormat::EOL . TextFormat::DARK_AQUA . "[Online at $info->location]", function (Player $sender) use ($playerName): void {
                            $this->sendBaseForm($sender, $playerName);
                        }));
                    } else {
                        $form->addButton(new Button(TextFormat::DARK_GRAY . $playerName . TextFormat::EOL . TextFormat::DARK_RED . "[Currently Offline]", function (Player $sender) use ($playerName): void {
                            $this->sendBaseForm($sender, $playerName);
                        }));
                    }
                }

                $form->sendForm();
            } else {
                $this->sendBaseForm($player, $players[0]);
            }
        });
    }

    public function sendBaseForm(Player $player, string $fullPlayerName): void
    {
        if (($form = FormManager::createSimpleForm($player)) === null || !$player->isConnected()) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Something went wrong while trying to create a form, try again later");
            return;
        }

        $form->setTitle(MMOPlugin::getPrefix() . TextFormat::DARK_RED . 'Investigation Menu');
        $form->setContent(
            TextFormat::GRAY . 'Target: ' . TextFormat::YELLOW . $fullPlayerName . TextFormat::EOL . TextFormat::EOL .
            TextFormat::GRAY . 'Select an option: ');
        $form->addButton(new Button(TextFormat::DARK_GRAY . "View info" . TextFormat::EOL . TextFormat::DARK_AQUA . 'Money, levels & bounty', function (Player $player) use ($fullPlayerName): void {
            $this->openPlayerProgress($player, $fullPlayerName);
        }));
        $form->addButton(new Button(TextFormat::DARK_GRAY . "Open player inventory" . TextFormat::EOL . TextFormat::DARK_AQUA . 'Contents of inventory', function (Player $player) use ($fullPlayerName): void {
            $this->hookStaffToPlayerInventory($player, $fullPlayerName);
        }));
        $form->addButton(new Button(TextFormat::DARK_GRAY . "Open ender chest" . TextFormat::EOL . TextFormat::DARK_AQUA . 'Contents of ender chest', function (Player $player) use ($fullPlayerName): void {
            $this->hookStaffToPlayerInventory($player, $fullPlayerName, self::ENDERCHEST_MODE);
        }));
        $form->addButton(new Button(TextFormat::DARK_GRAY . "Open player vaults" . TextFormat::EOL . TextFormat::DARK_AQUA . 'Contents of private vaults', function (Player $player) use ($fullPlayerName): void {
            $this->sendVaultsBaseForm($player, $fullPlayerName);
        }));
        $this->addButtons($form, $fullPlayerName);
        $form->addButton(new Button(TextFormat::DARK_RED . "Reset progress", function (Player $player) use ($fullPlayerName): void {
            if (!MMOPermissions::hasElevatedPermission($player)) {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You do not have the permission to use this command, only a Supervisor are allowed to execute this command.");
                return;
            }

            if (($form = FormManager::createModalForm($player)) === null) {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Something went wrong while trying to create a form, try again later");
                return;
            }

            $form->setTitle(MMOPlugin::getPrefix() . TextFormat::DARK_RED . "Reset confirmation.");
            $form->setContent(TextFormat::RED . "Are you sure to reset " . TextFormat::GOLD . $fullPlayerName . TextFormat::RED . ' progress? This action is irreversible!');
            $form->setButton1(new Button(TextFormat::DARK_RED . "Reset progress", function (Player $player) use ($fullPlayerName): void {
                $this->resetProgress($player, $fullPlayerName);
            }));
            $form->setButton2(new Button("Back", function (Player $player) use ($fullPlayerName): void {
                $this->sendBaseForm($player, $fullPlayerName);
            }));
            $form->sendForm();
        }));

        $form->sendForm();
    }

    public function sendVaultsBaseForm(Player $player, string $fullPlayerName): void
    {
        if (($form = FormManager::createSimpleForm($player)) === null) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Something went wrong while trying to create a form, try again later");
            return;
        }

        $form->setTitle(MMOPlugin::getPrefix() . TextFormat::DARK_RED . 'Vaults Investigation.');
        $form->setContent(
            TextFormat::GRAY . 'Target: ' . TextFormat::YELLOW . $fullPlayerName . TextFormat::EOL . TextFormat::EOL .
            TextFormat::GRAY . 'Please select one of these vaults: ');
        for ($vaultId = 0; $vaultId < VaultEntry::MAX_PRIVATE_VAULTS; $vaultId++) {
            $form->addButton(new Button(TextFormat::DARK_GRAY . 'Open vault #' . ($vaultId + 1), function (Player $player) use ($fullPlayerName, $vaultId): void {
                $this->hookStaffToPlayerVaults($player, $fullPlayerName, $vaultId);
            }));
        }

        $form->setCloseClosure(function (Player $player) use ($fullPlayerName) {
            $this->sendBaseForm($player, $fullPlayerName);
        });

        $form->sendForm();
    }

    /**
     * Reset player progress from this entire MMO game, you can extend this function to
     * perform any other "reset" in your MMO game.
     *
     * @param Player $staff
     * @param string $playerName
     */
    protected function resetProgress(Player $staff, string $playerName): void
    {
    }

    /**
     * This method were used to add another button into investigation form menu.
     * You can use this form if there is another type of moderation that could be done
     * within this form. (i.e: home list or faction info)
     *
     * @param SimpleForm $form
     * @param string $fullPlayerName
     */
    protected function addButtons(SimpleForm $form, string $fullPlayerName): void
    {
        $form->addButton(new Button(TextFormat::DARK_GRAY . "Load death history" . TextFormat::EOL . TextFormat::DARK_AQUA . 'History of player deaths', function (Player $player) use ($fullPlayerName): void {
            RollbackEngine::loadInventoryHistory($player, $fullPlayerName);
        }));
    }

    protected function openPlayerProgress(Player $player, string $playerName): void
    {
        Await::f2c(function () use ($player, $playerName) {
            $plugin = $this->getPlugin();

            $plugin->getPlayerData()->loadValue($playerName, PlayerData::BANK_MONEY, yield);
            $plugin->getPlayerData()->loadValue($playerName, PlayerData::PLAYER_MONEY, yield);
            $plugin->getPlayerData()->loadValue($playerName, PlayerData::XP, yield);
            $plugin->getPlayerData()->loadValue($playerName, PlayerData::BOUNTY, yield);

            $results = yield Await::ALL;

            $form = FormManager::createCustomForm($player, function (Player $player) use ($playerName) {
                $this->sendBaseForm($player, $playerName);
            });

            if ($form !== null && $player->isConnected()) {
                $form->setTitle('View balance: ' . $playerName);
                $form->addElement(new Label(TextFormat::GREEN . 'Bank: ' . TextFormat::RESET . '$' . number_format($results[0])));
                $form->addElement(new Label(TextFormat::GREEN . 'Purse: ' . TextFormat::RESET . '$' . number_format($results[1])));
                $form->addElement(new Label(TextFormat::GREEN . 'Level: ' . TextFormat::RESET . round(ExperienceUtils::getLevelFromXp($results[2]))));
                $form->addElement(new Label(TextFormat::GREEN . 'XP: ' . TextFormat::RESET . $results[2]));
                $form->addElement(new Label(TextFormat::GREEN . 'Bounty: ' . TextFormat::RESET . '$' . number_format($results[3])));

                $form->sendForm();
            }
        });
    }

    private function getOnlineContents(Player $player, int $mode = self::INVENTORY_MODE): array
    {
        if ($mode === self::INVENTORY_MODE) {
            $contents = $player->getInventory()->getContents();
            foreach ($player->getArmorInventory()->getContents() as $slot => $item) {
                $contents[self::ARMOR_INVENTORY_MENU_SLOTS[$slot]] = $item;
            }

            $cursor = $player->getCursorInventory()->getItem(0);
            if ($cursor->isNull()) {
                $cursorItem = clone self::getSeparatorItem()->setCustomName(TextFormat::RESET . 'Cursor');
            } else {
                $cursorItem = $cursor;
            }

            return $contents + [
                    36 => self::getBarrierItem(),
                    37 => self::getBarrierItem(),
                    38 => self::getBarrierItem(),
                    39 => self::getBarrierItem(),
                    41 => self::getBarrierItem(),
                    42 => self::getBarrierItem(),
                    43 => self::getBarrierItem(),
                    44 => self::getBarrierItem(),

                    40 => $cursorItem,
                    45 => clone self::getSeparatorItem()->setCustomName(TextFormat::RESET . 'Helmet'),
                    47 => clone self::getSeparatorItem()->setCustomName(TextFormat::RESET . 'Chestplate'),
                    49 => clone self::getSeparatorItem()->setCustomName(TextFormat::RESET . 'Leggings'),
                    51 => clone self::getSeparatorItem()->setCustomName(TextFormat::RESET . 'Boots'),
                    53 => VanillaItems::SLIMEBALL()->setCustomName(TextFormat::RESET . TextFormat::GREEN . 'Online')
                ];
        }
        $contents = $player->getEnderInventory()->getContents();

        $unusedSlot = [];
        for ($i = 27; $i < 53; $i++) {
            $unusedSlot[$i] = clone self::getSeparatorItem()->setCustomName(TextFormat::RESET . TextFormat::RED . 'Unused slot');
        }
        $unusedSlot[53] = VanillaItems::SLIMEBALL()->setCustomName(TextFormat::RESET . TextFormat::GREEN . 'Online');

        return $contents + $unusedSlot;
    }

    private function getOfflineContents(array $inventoryData, int $mode = self::INVENTORY_MODE): array
    {
        if ($mode === self::INVENTORY_MODE) {
            $contents = Inventory::convertJsonToContents($inventoryData[Inventory::INVENTORY_TAG]);
            foreach (Inventory::convertJsonToContents($inventoryData[Inventory::INVENTORY_ARMOR_TAG]) as $slot => $item) {
                $contents[self::ARMOR_INVENTORY_MENU_SLOTS[$slot]] = $item;
            }

            return $contents + [
                    36 => self::getBarrierItem(),
                    37 => self::getBarrierItem(),
                    38 => self::getBarrierItem(),
                    39 => self::getBarrierItem(),
                    41 => self::getBarrierItem(),
                    42 => self::getBarrierItem(),
                    43 => self::getBarrierItem(),
                    44 => self::getBarrierItem(),

                    40 => clone self::getSeparatorItem()->setCustomName(TextFormat::RESET . 'Cursor'),
                    45 => clone self::getSeparatorItem()->setCustomName(TextFormat::RESET . 'Helmet'),
                    47 => clone self::getSeparatorItem()->setCustomName(TextFormat::RESET . 'Chestplate'),
                    49 => clone self::getSeparatorItem()->setCustomName(TextFormat::RESET . 'Leggings'),
                    51 => clone self::getSeparatorItem()->setCustomName(TextFormat::RESET . 'Boots'),
                    53 => VanillaItems::MAGMA_CREAM()->setCustomName(TextFormat::RESET . TextFormat::RED . 'Offline')
                ];
        }
        $contents = Inventory::convertJsonToContents($inventoryData[Inventory::INVENTORY_ENDER_CHEST_TAG]);

        $unusedSlot = [];
        for ($i = 27; $i < 53; $i++) {
            $unusedSlot[$i] = clone self::getSeparatorItem()->setCustomName(TextFormat::RESET . TextFormat::RED . 'Unused slot');
        }
        $unusedSlot[53] = VanillaItems::MAGMA_CREAM()->setCustomName(TextFormat::RESET . TextFormat::RED . 'Offline');

        return $contents + $unusedSlot;
    }

    /**
     * @param Player $staff The staff that is handling the situation.
     * @param string $target The target player the server needs to investigate.
     * @param int $vaultId The inventory number from 0-4
     */
    protected function hookStaffToPlayerVaults(Player $staff, string $target, int $vaultId = 0): void
    {
        if ($staff->getName() === $target) {
            $staff->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'You cannot investigate your own vaults.');
            return;
        }

        Await::f2c(function () use ($staff, $target, $vaultId): Generator {
            $vaultContents = null;

            $vs = new VaultStorage();
            $ess = $this->getPlugin()->getEssentials();
            if (!($player = $ess->getPlayerManager()->getBestMatchingPlayer($target)) instanceof Player) {
                Database::executeSelectRaw("SELECT player, xuid, vaults FROM player_data WHERE player = ?", [
                    $target
                ], yield, yield Await::REJECT);

                $results = yield Await::ONCE;

                if (empty($results)) {
                    $staff->sendMessage(MMOPlugin::getPrefix() . "That player do not exists in the database.");
                } else {
                    $xuid = $results[0]['xuid'];
                    $playerName = $results[0]['player'];

                    if ($playerName === $staff->getName()) {
                        $staff->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'You cannot investigate your own vaults.');
                    } else if (isset($this->players[$xuid])) {
                        $staff->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Cannot investigate that player, another staff is currently investigating the player.');
                    } else {
                        try {
                            $vaults = json_decode($results[0]['vaults'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
                        } catch (JsonException) {
                            $vaults = [];
                        }

                        if (!isset($vaults[$vaultId])) {
                            $staff->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'The player has no contents stored in vault #' . ($vaultId + 1));

                            return false;
                        }

                        [$content, $rawInventory] = Inventory::convertStringToInventoryJSON(base64_decode($vaults[$vaultId]), decodedData: true);
                        $vaultContents = Inventory::convertJsonToContents($content);

                        $vs->playerName = $playerName;
                        $vs->xuidToPlayer = $xuid;
                        $vs->offlineObject = md5($rawInventory);
                        $vs->vaultModified = $vaultId;
                    }
                }
            } else {
                if ($player->getName() === $staff->getName()) {
                    $staff->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'You cannot investigate your own vaults.');
                } else if (isset($this->players[$player->getXuid()])) {
                    $staff->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Cannot investigate that player, another staff is currently investigating the player.');
                } else {
                    /** @var VaultEntry[] $menu */
                    $menu = MMOPlugin::getInstance()->getPlayerData()->getValue($player, PlayerData::RUNTIME_PRIVATE_VAULTS);
                    $vaultContents = $menu[$vaultId]->getInvMenu()->getInventory()->getContents();

                    $vs->xuidToPlayer = $player->getXuid();
                    $vs->playerName = $player->getName();
                    $vs->vaultModified = $vaultId;
                }
            }

            if ($vaultContents === null) return false;
            if (isset($this->vaultStorage[$vs->playerName][$vaultId])) {
                $staff->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Cannot investigate that player, another staff is currently investigating the player.');

                return false;
            }

            $invMenu = InvMenu::create(MMOPlugin::MENU_CHEST_DOUBLE);
            $invMenu->setName(MMOPlugin::getPrefix() . TextFormat::DARK_GRAY . $vs->playerName . "'s #" . ($vaultId + 1) . " Vaults");

            /** @var InvMenuInventory $inv */
            $inv = $invMenu->getInventory();
            $inv->setContents($vaultContents);

            $linker = new SharedInventory($inv);
            $staffLinkers = new SharedInventory(null);

            // Preventing deadlocks.
            $linker->setLinkedInventory($staffLinkers);
            $staffLinkers->setLinkedInventory($linker);

            if ($player instanceof Player && $player->isConnected() && $this->getPlugin()->getPlayerData()->getBool($player, PlayerData::DATA_LOADED)) {
                $menu = MMOPlugin::getInstance()->getPlayerData()->getValue($player, PlayerData::RUNTIME_PRIVATE_VAULTS);
                $inventory = $menu[$vaultId]->getInvMenu()->getInventory();

                $staffLinkers->setInventory($inventory);
                $inventory->getListeners()->add($linker);
            }

            $inv->getListeners()->add($staffLinkers);

            if (!Permissions::hasElevatedPermission($staff)) {
                $invMenu->setListener(InvMenu::readonly(function (): void {
                }));
            } else {
                $invMenu->setInventoryCloseListener(function (Player $staff, PMInventory $inventory) use ($vs): void {
                    unset($this->vaultStorage[$vs->playerName][$vs->vaultModified]);

                    $player = Server::getInstance()->getPlayerExact($vs->playerName);
                    if ($player === null || !$player->isConnected()) {
                        Await::f2c(function () use ($staff, $inventory, $vs) {
                            SocialManager::requestPlayerInfo($vs->playerName, yield);

                            /** @var ?PlayerSocialInfo $info */
                            $info = yield Await::ONCE;

                            $onlineAt = null;
                            $isOnline = false;
                            if ($info !== null && str_contains($serverUniqueId = $info->location, self::getServerType())) {
                                $isOnline = true;
                                $onlineAt = $serverUniqueId;
                            }

                            $playerData = $this->getPlugin()->getPlayerData();

                            $playerData->loadValue($vs->playerName, PlayerData::PRIVATE_VAULTS, yield);
                            $vaults = yield Await::ONCE;

                            if (!$staff->isConnected()) {
                                return;
                            }

                            $data = $vaults[$vs->vaultModified] ?? '';

                            if (empty($data)) {
                                $staff->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Something went wrong, please try again later.');
                                return;
                            }

                            $rawString = zstd_uncompress(base64_decode($data));
                            if (md5($rawString) !== $vs->offlineObject) {
                                $staff->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Unable to change player inventory, the inventory was changed before!');
                                return;
                            }

                            if (!$isOnline) {
                                $vaults[$vs->vaultModified] = base64_encode(zstd_compress(json_encode(Inventory::convertInventoryToJson($inventory))));

                                $playerData->setValue($vs->playerName, PlayerData::PRIVATE_VAULTS, $vaults, true);
                                if (!$playerData->getBool($vs->playerName, PlayerData::DATA_LOADED)) {
                                    $playerData->saveValue($vs->playerName, PlayerData::PRIVATE_VAULTS, yield);
                                    yield Await::ONCE;
                                }

                                $staff->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . 'Successfully saved ' . $vs->playerName . '\'s private vaults #' . ($vs->vaultModified + 1));
                                $this->getPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($staff, $vs): void {
                                    if ($staff->isConnected()) {
                                        $this->sendVaultsBaseForm($staff, $vs->playerName);
                                    }
                                }), 20);
                            } else {
                                $staff->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Cannot modify ' . $vs->playerName . '\'s private vaults, player is online at ' . $onlineAt);
                            }
                        }, catches: Database::getFailClosure());
                    } else {
                        $this->getPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($staff, $vs): void {
                            if ($staff->isConnected()) {
                                $this->sendVaultsBaseForm($staff, $vs->playerName);
                            }
                        }), 20);

                        $menu = $this->getPlugin()->getPlayerData()->getValue($player, PlayerData::RUNTIME_PRIVATE_VAULTS);

                        /** @var BaseInventory $inventory */
                        $inventory = $menu[$vs->vaultModified]->getInvMenu()->getInventory();
                        $inventory->getListeners()->remove($vs->player);

                        $staff->sendMessage(MMOPlugin::getPrefix() . TextFormat::GRAY . 'Nothing to change, the player is online in this server.');
                    }
                });
            }

            $this->vaultStorage[$vs->playerName][$vs->vaultModified] = $vs;

            $vs->player = $linker;
            $vs->viewer = $staffLinkers;

            $invMenu->send($staff, callback: yield);

            // Reset player vault entry state if the inventory send was failed.
            if (!yield Await::ONCE) {
                $vs = $this->vaultStorage[$vs->playerName][$vs->vaultModified];

                if ($player instanceof Player && $player->isConnected() && $this->getPlugin()->getPlayerData()->getBool($player, PlayerData::DATA_LOADED)) {
                    $menu = MMOPlugin::getInstance()->getPlayerData()->getValue($player, PlayerData::RUNTIME_PRIVATE_VAULTS);

                    /** @var VaultEntry|null $vault */
                    $vault = $menu[$vaultId] ?? null;

                    if ($vault === null) {
                        throw new LogicException("Player runtime vaults should have been defined");
                    }

                    $inventory = $vault->getInvMenu()->getInventory();
                    $inventory->getListeners()->remove($vs->player);

                    $vs->offlineObject = md5($vault->fastSerialize());
                    $vs->viewer->setInventory(null);
                }

                unset($this->vaultStorage[$vs->playerName][$vs->vaultModified]);
            }

            return true;
        }, function (bool $result) use ($staff, $target): void {
            if (!$result) {
                $this->getPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($staff, $target): void {
                    if ($staff->isConnected()) {
                        $this->sendVaultsBaseForm($staff, $target);
                    }
                }), 20);
            }
        }, Database::getFailClosure());
    }

    protected function hookStaffToPlayerInventory(Player $staff, string $target, int $mode = self::INVENTORY_MODE): void
    {
        if (strtolower($staff->getName()) === strtolower($target)) {
            $staff->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'You cannot investigate your own inventory.');
            return;
        }

        Await::f2c(function () use ($staff, $target, $mode): Generator {
            $ess = $this->getPlugin()->getEssentials();
            if (!($player = $ess->getPlayerManager()->getBestMatchingPlayer($target)) instanceof Player) {
                Database::executeSelectRaw("SELECT player, xuid, inventory FROM player_data WHERE player = ?", [
                    $target
                ], yield, yield Await::REJECT);

                $results = yield Await::ONCE;

                if (empty($results)) {
                    $staff->sendMessage(MMOPlugin::getPrefix() . "That player do not exists in the database.");
                    return;
                } else {
                    $xuid = $results[0]['xuid'];
                    $playerName = $results[0]['player'];

                    if ($playerName === $staff->getName()) {
                        $staff->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'You cannot investigate your own inventory.');
                        return;
                    } else if (isset($this->players[$xuid])) {
                        $staff->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Cannot investigate that player, another staff is currently investigating the player.');
                        return;
                    } else {
                        $inventoryData = Inventory::convertStringToInventoryJSON($rawString = $results[0]['inventory']);
                        $this->offlineObject[$xuid] = md5($rawString);

                        $contents = $this->getOfflineContents($inventoryData, $mode);

                        $this->xuidToPlayer[$xuid] = null;
                    }
                }
            } else {
                $xuid = $player->getXuid();
                if ($player->getName() === $staff->getName()) {
                    $staff->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'You cannot investigate your own inventory.');
                    return;
                } else if (isset($this->players[$xuid])) {
                    $staff->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Cannot investigate that player, another staff is currently investigating the player.');
                    return;
                } else {
                    $contents = $this->getOnlineContents($player, $mode);

                    $this->xuidToPlayer[$xuid] = $player;

                    $playerName = $player->getName();
                }
            }

            $inventory = $this->players[$xuid] = InvMenu::create(MMOPlugin::MENU_CHEST_DOUBLE);
            $this->staffs[$staff->getXuid()] = $xuid;

            if ($mode === self::INVENTORY_MODE) {
                $inventory->setName($playerName . '\'s Inventory');
            } else {
                $inventory->setName($playerName . '\'s Ender Chest');
            }

            $inventory->getInventory()->setContents($contents);
            $inventory->setInventoryCloseListener($this->inventoryCloseListener());

            /** @var InvMenuInventory $inv */
            $inv = $inventory->getInventory();

            $linkedInventory = new SharedInventory($inv);
            if ($player instanceof Player && $player->isConnected()) {
                if ($mode === self::INVENTORY_MODE) {
                    $player->getInventory()->getListeners()->add($linkedInventory);
                    $player->getArmorInventory()->getListeners()->add($linkedInventory);
                } else {
                    $player->getEnderInventory()->getListeners()->add($linkedInventory);
                }
            }

            $this->playersMode[$xuid] = $mode;
            $this->sharedInventories[$xuid] = $linkedInventory;

            $inventory->send($staff, callback: yield);

            if (!(yield Await::ONCE)) {
                unset($this->players[$xuid]);
                unset($this->staffs[$staff->getXuid()]);
                unset($this->sharedInventories[$xuid]);
            }
        }, Database::getFailClosure());
    }

    /**
     * Return a segment of code that is responsible for inventory cleanup, this will save
     * player's inventory if the target is offline AND is not online in any servers. If they are online,
     * we will notify staff member that the changes made are not saved.
     * <p>
     * This function will be called if the player left the server
     *
     * @return Closure
     */
    private function inventoryCloseListener(): Closure
    {
        static $function = null;
        if ($function === null) {
            $function = function (Player $player, InvMenuInventory $inventory) {
                if (!isset($this->staffs[$player->getXuid()])) {
                    $this->getPlugin()->getLogger()->error("Staff " . $player->getName() . " has no linked players.");
                    return;
                } else if (!MMOPermissions::hasElevatedPermission($player)) {
                    $offset = $this->staffs[$player->getXuid()];

                    unset($this->staffs[$player->getXuid()], $this->sharedInventories[$offset], $this->offlineObject[$offset], $this->players[$offset], $this->xuidToPlayer[$offset], $this->playersMode[$offset]);
                    return;
                }

                $target = $this->xuidToPlayer[$offset = $this->staffs[$player->getXuid()]];
                $mode = $this->playersMode[$offset];

                unset($this->staffs[$player->getXuid()], $this->playersMode[$offset], $this->sharedInventories[$offset]);

                if ($target === null || !$target->isConnected()) {
                    Await::f2c(function () use ($player, $offset, $inventory, $mode) {
                        Database::executeSelectRaw('SELECT player, inventory FROM player_data WHERE xuid = ?', [
                            $offset
                        ], yield, yield Await::REJECT);

                        $results = yield Await::ONCE;

                        if (empty($results)) {
                            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Something went wrong, player could not be found in database.');
                            return;
                        }

                        unset($this->players[$offset]);
                        unset($this->xuidToPlayer[$offset]);

                        $inventoryRaw = Inventory::convertStringToInventoryJSON($rawString = $results[0]['inventory']);
                        if (isset($this->offlineObject[$offset]) && md5($rawString) !== $this->offlineObject[$offset]) {
                            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Unable to change player inventory, the inventory was changed before!');
                            unset($this->offlineObject[$offset]);

                            return;
                        }
                        unset($this->offlineObject[$offset]);

                        SocialManager::requestPlayerInfo($targetName = $results[0]['player'], yield);

                        /** @var ?PlayerSocialInfo $info */
                        $info = yield Await::ONCE;

                        if (!$player->isConnected()) {
                            return;
                        }

                        $onlineAt = null;
                        $isOnline = false;
                        if ($info !== null && str_contains($serverUniqueId = $info->location, self::getServerType())) {
                            $isOnline = true;
                            $onlineAt = $serverUniqueId;
                        }

                        if (!$isOnline) {
                            $contents = $inventory->getContents(true);

                            if ($mode === self::INVENTORY_MODE) {
                                $inventoryRaw[Inventory::INVENTORY_TAG] = Inventory::convertItemsToJson(array_slice($contents, 0, $player->getInventory()->getSize(), true));
                                $inventoryRaw[Inventory::INVENTORY_ARMOR_TAG] = Inventory::convertItemsToJson([
                                    0 => $contents[self::ARMOR_INVENTORY_MENU_SLOTS[0]],
                                    1 => $contents[self::ARMOR_INVENTORY_MENU_SLOTS[1]],
                                    2 => $contents[self::ARMOR_INVENTORY_MENU_SLOTS[2]],
                                    3 => $contents[self::ARMOR_INVENTORY_MENU_SLOTS[3]],
                                ]);
                            } else {
                                $inventoryRaw[Inventory::INVENTORY_ENDER_CHEST_TAG] = Inventory::convertItemsToJson(array_slice($contents, 0, $player->getEnderInventory()->getSize(), true));
                            }

                            Database::executeInsertRaw('UPDATE player_data SET inventory = ? WHERE xuid = ?', [
                                Inventory::convertInventoryJSONToString($inventoryRaw[0], $inventoryRaw[1], $inventoryRaw[2]),
                                $offset
                            ], yield, yield Await::REJECT);

                            yield Await::ONCE;

                            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . 'Successfully saved ' . $targetName . '\'s inventory');

                            $this->getPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player, $targetName): void {
                                if (!$player->isConnected()) {
                                    return;
                                }

                                $this->sendBaseForm($player, $targetName);
                            }), 20);
                        } else {
                            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Cannot modify ' . $targetName . '\'s inventory, player is online at ' . $onlineAt);
                        }
                    }, catches: Database::getFailClosure());
                } else {
                    unset($this->players[$target->getXuid()]);
                    unset($this->xuidToPlayer[$target->getXuid()]);

                    $this->getPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player, $target): void {
                        if (!$player->isConnected()) {
                            return;
                        }

                        $this->sendBaseForm($player, $target->getName());
                    }), 20);

                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::GRAY . 'Nothing to change, the player is online in this server.');
                }
            };
        }

        return $function;
    }

    /**
     * Process player inventory from this server, in case a staff is modifying the inventory
     * in this server, we can reset the target inventory and set the current modified inventory.
     *
     * @param Player $player
     */
    public function processInventory(Player $player): void
    {
        if (isset($this->vaultStorage[$player->getName()])) {
            $menu = $this->getPlugin()->getPlayerData()->getValue($player, PlayerData::RUNTIME_PRIVATE_VAULTS);

            foreach ($this->vaultStorage[$player->getName()] as $vaultId => $vs) {
                /** @var VaultEntry|null $vault */
                $vault = $menu[$vaultId] ?? null;

                if ($vault === null) {
                    throw new LogicException("Player runtime vaults should have been defined");
                }

                $inventory = $vault->getInvMenu()->getInventory();
                $staffInventory = $vs->player->getInventory();

                // Check if there was a change in player vaults.
                if (md5($vault->fastSerialize()) !== $vs->offlineObject) {
                    $staffInventory->setContents($inventory->getContents());
                } else {
                    $inventory->setContents($staffInventory->getContents());
                    $inventory->getListeners()->add($vs->player);
                }

                $vs->viewer->setInventory($inventory);
            }
        }

        if (($invMenu = $this->players[$player->getXuid()] ?? null) !== null) {
            $mode = $this->playersMode[$player->getXuid()];
            $isTransferMode = !empty(NGEssentials::getInstance()->getPlayerData()->getArray($player, NGPlayerData::FORWARD));

            if ($isTransferMode) {
                $invMenu->getInventory()->setContents($this->getOnlineContents($player, $mode));
            }

            if ($mode === self::INVENTORY_MODE) {
                if (!$isTransferMode) {
                    // At this point, staff can only modify the player's inventory contents, not the cursor inventory at any
                    // circumstances. Well, they can modify the player armour contents without problems.
                    $inventory = $player->getInventory();
                    $armorInventory = $player->getArmorInventory();
                    foreach ($invMenu->getInventory()->getContents(true) as $slot => $item) {
                        if ($slot < $inventory->getSize()) {
                            if (!$item->equalsExact($inventory->getItem($slot))) {
                                $inventory->setItem($slot, $item);
                            }
                        } else if (in_array($slot, self::ARMOR_INVENTORY_MENU_SLOTS)) {
                            $armorSlot = array_search($slot, self::ARMOR_INVENTORY_MENU_SLOTS);

                            if (!$item->equalsExact($armorInventory->getItem($armorSlot))) {
                                $armorInventory->setItem($armorSlot, $item);
                            }
                        }
                    }

                    $invMenu->getInventory()->setItem(53, VanillaItems::SLIMEBALL()->setCustomName(TextFormat::RESET . TextFormat::GREEN . 'Online'));
                }

                $player->getInventory()->getListeners()->add($this->sharedInventories[$player->getXuid()]);
                $player->getArmorInventory()->getListeners()->add($this->sharedInventories[$player->getXuid()]);
            } else {
                // If the player was not in transfer mode, we set the contents immediately.
                if (!$isTransferMode) {
                    $inventory = $player->getEnderInventory();

                    foreach ($invMenu->getInventory()->getContents(true) as $slot => $item) {
                        if ($slot < $inventory->getSize()) {
                            if (!$item->equalsExact($inventory->getItem($slot))) {
                                $inventory->setItem($slot, $item);
                            }
                        }
                    }

                    $invMenu->getInventory()->setItem(53, VanillaItems::SLIMEBALL()->setCustomName(TextFormat::RESET . TextFormat::GREEN . 'Online'));
                }

                $player->getEnderInventory()->getListeners()->add($this->sharedInventories[$player->getXuid()]);
            }

            $this->xuidToPlayer[$player->getXuid()] = $player;

            unset($this->offlineObject[$player->getXuid()]);
        }
    }

    /**
     * @param PlayerQuitEvent $event
     * @priority LOWEST
     */
    public function onPlayerQuitEvent(PlayerQuitEvent $event): void
    {
        /** @var MMOPlayer $player */
        $player = $event->getPlayer();

        if (isset($this->vaultStorage[$player->getName()])) {
            $menu = $this->getPlugin()->getPlayerData()->getValue($player, PlayerData::RUNTIME_PRIVATE_VAULTS);

            foreach ($this->vaultStorage[$player->getName()] as $vaultId => $vs) {
                /** @var VaultEntry|null $vault */
                $vault = $menu[$vaultId] ?? null;

                if ($vault === null) {
                    throw new LogicException("Player runtime vaults should have been defined");
                }

                $inventory = $vault->getInvMenu()->getInventory();
                $inventory->getListeners()->remove($vs->player);

                $vs->offlineObject = md5($vault->fastSerialize());
                $vs->viewer->setInventory(null);
            }
        }

        $invMenu = $this->players[$player->getXuid()] ?? null;
        if ($invMenu === null) {
            return;
        }

        $this->xuidToPlayer[$player->getXuid()] = null;
        $this->offlineObject[$player->getXuid()] = md5($player->saveInventory());


        $inventory = $invMenu->getInventory();
        $inventory->setItem(53, VanillaItems::MAGMA_CREAM()->setCustomName(TextFormat::RESET . TextFormat::RED . 'Offline'));
    }

    /**
     * @param InventoryTransactionEvent $event
     * @priority MONITOR
     * @handleCancelled
     */
    public function onInventoryTransaction(InventoryTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();
        $player = $transaction->getSource();

        $isTarget = isset($this->players[$player->getXuid()]);
        $isStaff = isset($this->staffs[$player->getXuid()]);

        // Target is modifying the inventory, lets change the items that was set in the InvMenu.
        if ($isTarget && !$event->isCancelled()) {
            $invMenu = $this->players[$player->getXuid()];
            $inventory = $invMenu->getInventory();
            $mode = $this->playersMode[$player->getXuid()];

            $actionInventory = [];
            $armorInventory = [];
            $enderchestInventory = [];

            // The player cursor, this only applies to Windows 10 inventories.
            $cursorItem = VanillaItems::AIR();
            $oldCursorItem = VanillaItems::AIR();
            foreach ($transaction->getActions() as $action) {
                if (!($action instanceof SlotChangeAction)) {
                    continue;
                }

                if ($action->getInventory() instanceof PlayerCursorInventory) {
                    $oldCursorItem = $action->getSourceItem();
                    $cursorItem = $action->getTargetItem();
                } else if ($action->getInventory() instanceof PlayerInventory) {
                    $actionInventory[] = $action;
                } else if ($action->getInventory() instanceof ArmorInventory) {
                    $armorInventory[] = $action;
                } else if ($action->getInventory() instanceof EnderChestInventory) {
                    $enderchestInventory[] = $action;
                }
            }

            $eventHandler = $this->sharedInventories[$player->getXuid()];
            $eventHandler->startModification();

            if ($mode === self::INVENTORY_MODE) {
                $cursorCopy = $inventory->getItem(40);
                $cursorSeparator = clone self::getSeparatorItem()->setCustomName(TextFormat::RESET . 'Cursor');
                if (!$oldCursorItem->equalsExact($cursorItem)) {
                    if ($cursorItem->isNull() && !$cursorCopy->equalsExact($cursorSeparator)) {
                        $inventory->setItem(40, $cursorSeparator);
                    } else {
                        $inventory->setItem(40, $cursorItem);
                    }
                }

                // The player inventory transactions, this can be applied to both windows and android users.
                if (!empty($actionInventory)) {
                    foreach ($actionInventory as $action) {
                        $inventory->setItem($action->getSlot(), $action->getTargetItem());
                    }
                }

                // The holy armor inventory.
                if (!empty($armorInventory)) {
                    foreach ($armorInventory as $action) {
                        $inventory->setItem(self::ARMOR_INVENTORY_MENU_SLOTS[$action->getSlot()], $action->getTargetItem());
                    }
                }
            } else {
                if (!empty($enderchestInventory)) {
                    foreach ($enderchestInventory as $action) {
                        $inventory->setItem($action->getSlot(), $action->getTargetItem());
                    }
                }
            }

            $eventHandler->stopModification();
        } else if ($isStaff) {
            $event->uncancel();

            $invMenuContents = [];
            foreach ($transaction->getActions() as $action) {
                if (!($action instanceof SlotChangeAction)) {
                    continue;
                }

                if ($action->getInventory() instanceof InvMenuInventory) {
                    $invMenuContents[] = $action;
                }
            }

            // Cancel all related InvMenu handling, but do not cancel the player inventory
            // transaction, which may well be legitimate.
            if (!empty($invMenuContents) && !MMOPermissions::hasElevatedPermission($player)) {
                $event->cancel();
                return;
            }

            // First iteration, check if the staff is doing the right thing here.
            foreach ($invMenuContents as $action) {
                $source = $action->getSourceItem();
                if ($source->equals(self::getBarrierItem(), true, false) || $source->equals(self::getSeparatorItem(), true, false) || $action->getSlot() === 53) {
                    $event->cancel();
                    return;
                }
            }

            // Second iteration, modify the target inventory and check if the transaction is valid
            $targetPlayer = $this->xuidToPlayer[$targetXuid = $this->staffs[$player->getXuid()]];
            $isConnected = $targetPlayer !== null && $targetPlayer->isConnected();
            $mode = $this->playersMode[$targetXuid];

            $eventHandler = $this->sharedInventories[$targetXuid];
            $eventHandler->startModification();

            foreach ($invMenuContents as $action) {
                $target = $action->getTargetItem();
                $slot = $action->getSlot();

                if ($mode === self::INVENTORY_MODE) {
                    if (in_array($slot, self::ARMOR_INVENTORY_MENU_SLOTS)) {
                        $slot = array_search($slot, self::ARMOR_INVENTORY_MENU_SLOTS);

                        if (!($target instanceof Armor)) {
                            if ($target->isNull() && $isConnected) {
                                $targetPlayer->getArmorInventory()->setItem($slot, VanillaItems::AIR());
                            } else if (!$target->isNull()) {
                                $event->cancel();
                            }

                            break;
                        }

                        if ($target->getArmorSlot() !== $slot) {
                            $event->cancel();
                            break;
                        }

                        // If player is connected, we will sync the inventory with the player, and in case if the player is
                        // not connected, we will try to save their inventory later after the staff made the changes.
                        if ($isConnected) {
                            $targetPlayer->getArmorInventory()->setItem($slot, $target);
                        }
                    } else if ($isConnected) {
                        if ($slot < $targetPlayer->getInventory()->getSize()) {
                            $targetPlayer->getInventory()->setItem($slot, $target);
                        } else {
                            $event->cancel();
                            break;
                        }
                    }
                } else {
                    if ($isConnected) {
                        $targetPlayer->getEnderInventory()->setItem($slot, $target);
                    }
                }
            }

            $eventHandler->stopModification();
        }
    }

    public static function getSeparatorItem(): Item
    {
        static $item = null;
        if ($item === null) {
            $item = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::RED)->asItem();
        }

        return $item;
    }

    public static function getBarrierItem(): Item
    {
        static $barrier = null;
        if ($barrier === null) {
            $barrier = CustomItemRegistry::TRADE_SEPARATOR()->setCustomName(TextFormat::RESET . TextFormat::RED . 'Empty Slot');
        }

        return $barrier;
    }
}