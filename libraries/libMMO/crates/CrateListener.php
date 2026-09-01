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

namespace libMMO\crates;

use libMMO\challenges\ChallengeSet;
use libMMO\crates\loottables\CrateLootTable;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use libMMO\utils\BaseListener;
use libMMO\utils\Utils;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function array_fill;
use function array_merge;

class CrateListener extends BaseListener
{
    public static Item $glassBlocks;
    public static Item $glassIndicator;

    public static Item $blockClose;
    public static Item $blockOpen;
    public static Item $blockOpen5;
    public static Item $blockOpen10;

    /** @var bool[] */
    public static array $pendingTasks = [];

    /** @var int */
    public static int $crateRotations = 40; // Plugins can modify the crates rotations

    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct($plugin);

        self::$glassBlocks = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::WHITE)->asItem()->setCustomBlockData(Utils::readOnlyTag());
        self::$glassIndicator = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::GRAY)->asItem()->setCustomBlockData(Utils::readOnlyTag());

        self::$blockClose = VanillaBlocks::CONCRETE()->setColor(DyeColor::RED)->asItem()->setCustomBlockData(Utils::readOnlyTag())->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . 'Close Crate')->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to close this crate.']);
        self::$blockOpen = VanillaBlocks::CONCRETE()->setColor(DyeColor::LIME)->asItem()->setCustomBlockData(Utils::readOnlyTag())->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GREEN . 'Open Crate')->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to open this crate.']);
        self::$blockOpen5 = VanillaBlocks::CONCRETE()->setColor(DyeColor::YELLOW)->asItem()->setCustomBlockData(Utils::readOnlyTag())->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::YELLOW . 'Open Crates x5')->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to open this crate.']);
        self::$blockOpen10 = VanillaBlocks::CONCRETE()->setColor(DyeColor::ORANGE)->asItem()->setCustomBlockData(Utils::readOnlyTag())->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GOLD . 'Open Crates x10')->setLore(['', TextFormat::RESET . TextFormat::GRAY . 'Click to open this crate.']);
    }

    public static function sendCrate(Player $player, CrateLootTable $lootTable, ?InvMenu $menu = null): void
    {
        $created = false;
        if ($menu === null) {
            $menu = InvMenu::create(MMOPlugin::MENU_CHEST_DOUBLE);
            $menu->setName($lootTable->getName() . ' Crate');

            $created = true;
        }

        $inventory = $menu->getInventory();
        $inventory->clearAll();
        foreach ($lootTable->getEntries() as $entry) {
            $reward = clone $entry->getItem();
            if ($reward->getCustomName() === '') {
                $reward->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::WHITE . $reward->getName());
            }
            if ($reward->getCustomBlockData() === null) {
                $reward->setCustomBlockData(Utils::readOnlyTag());
            } else {
                $reward->getCustomBlockData()->setInt(Utils::READONLY_TAGS, 0);
            }
            $reward->setLore(array_merge($reward->getLore(), ['', TextFormat::RESET . TextFormat::GOLD . 'Chance: ' . TextFormat::WHITE . $entry->getChance() . '%']));
            $inventory->addItem($reward);
        }

        $inventory->setItem(45, self::$blockClose);
        $inventory->setItem(49, self::$blockOpen);
        $inventory->setItem(51, self::$blockOpen5);
        $inventory->setItem(53, self::$blockOpen10);
        $inventory->setItem(47, VanillaItems::PAPER()->setCustomBlockData(Utils::readOnlyTag())->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GOLD . 'Your ' . $lootTable->getName() . ' Keys')->setLore(['', TextFormat::RESET . TextFormat::YELLOW . 'Keys: ' . TextFormat::WHITE . MMOPlugin::getInstance()->getPlayerData()->getKeys($player, $lootTable->getKeyDataType())]));

        $menu->setListener(InvMenu::readonly(function (DeterministicInvMenuTransaction $transaction) use ($lootTable, $menu): void {
            $player = $transaction->getPlayer();
            $action = $transaction->getAction();

            switch ($action->getSlot()) {
                case 47:
                    $player->removeCurrentWindow();
                    break;
                case 49:
                case 51:
                case 53:
                    $normalOnly = $action->getSlot() === 49;
                    $multiply = $action->getSlot() === 53; // Multiply by x10

                    $mode = $normalOnly ? CrateRouletteTask::MODE_ONE : ($multiply ? CrateRouletteTask::MODE_TEN : CrateRouletteTask::MODE_FIVE);
                    $keys = $normalOnly ? 1 : ($multiply ? 10 : 5);

                    $playerData = MMOPlugin::getInstance()->getPlayerData();
                    if ($playerData->getKeys($player, $lootTable->getKeyDataType()) < $keys) {
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You don't have any " . $lootTable->getName() . ' keys!');
                    } else if (!isset(self::$pendingTasks[$player->getName()])) {
                        $menu->setName('Opening ' . $lootTable->getName() . ' Crate');

                        $menu->getInventory()->clearAll();
                        $menu->getInventory()->setContents(array_fill(0, $multiply ? 36 : 27, self::$glassBlocks));
                        if ($normalOnly) {
                            $menu->getInventory()->setItem(4, self::$glassIndicator);
                            $menu->getInventory()->setItem(22, self::$glassIndicator);
                        } else {
                            for ($i = 2; $i <= 6; $i++) {
                                $menu->getInventory()->setItem($i, self::$glassIndicator);
                            }

                            if ($multiply) {
                                for ($i = 29; $i <= 33; $i++) {
                                    $menu->getInventory()->setItem($i, self::$glassIndicator);
                                }
                            } else {
                                for ($i = 20; $i <= 24; $i++) {
                                    $menu->getInventory()->setItem($i, self::$glassIndicator);
                                }
                            }
                        }

                        self::$pendingTasks[$player->getName()] = true;

                        MMOPlugin::getInstance()->getScheduler()->scheduleRepeatingTask(new CrateRouletteTask(MMOPlugin::getInstance(), $player, $menu, $lootTable, 18, self::$crateRotations, $mode), 4);
                        foreach (MMOPlugin::getInstance()->getPlayerChallengeManager()->getActiveChallenges($player) as $challenge) {
                            $challenge->increaseProgress($player, ChallengeSet::CRATE_OPEN);
                        }

                        $playerData->reduceKey($player, $lootTable->getKeyDataType(), $keys);
                    } else {
                        $player->sendMessage(MMOPlugin::getPrefix() . "You are currently opening a crate, please wait for it to complete.");
                    }
                    break;
            }
        }));

        if ($created) {
            $menu->send($player);
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

        if ($player->getWorld() === $player->getServer()->getWorldManager()->getDefaultWorld() && $event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
            $crateManager = $this->getPlugin()->getCrateManager();
            $crate = $crateManager->getCrateFromPosition($event->getBlock()->getPosition());

            if (($lootTable = $crateManager->getLootTable($crate)) !== null) {
                $event->cancel();

                self::sendCrate($player, $lootTable);
            }
        }
    }
}