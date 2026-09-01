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

use Closure;
use libMMO\crates\loottables\CrateLootTable;
use libMMO\item\CustomItemManager;
use libMMO\item\CustomItemRegistry;
use libMMO\item\ItemStorage;
use libMMO\MMOPlugin;
use libMMO\player\Inventory;
use libMMO\utils\Utils;
use muqsit\invmenu\InvMenu;
use pocketmine\block\BlockTypeIds;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\nbt\tag\IntTag;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;
use pocketmine\world\sound\EndermanTeleportSound;
use pocketmine\world\sound\PopSound;
use function array_chunk;
use function array_merge;
use function array_pop;
use function array_unshift;
use function in_array;
use function mt_rand;

class CrateRouletteTask extends Task
{
    public const MODE_ONE = 0;
    public const MODE_FIVE = 1;
    public const MODE_TEN = 2;

    private const ROULETTE_PLACEMENTS = [
        self::MODE_ONE => [13, 13],
        self::MODE_FIVE => [11, 15],
        self::MODE_TEN => [20, 24],
    ];

    /** @var MMOPlugin */
    private MMOPlugin $plugin;
    /** @var InvMenu */
    private InvMenu $menu;
    /** @var Item[] */
    private array $items1;
    /** @var Item[] */
    private array $items2;
    /** @var int */
    private int $rotations;
    /** @var CrateLootTable */
    private CrateLootTable $lootTables;
    /** @var Player */
    private Player $player;
    /** @var int */
    private int $mode;
    /** @phpstan-var Closure(Player, Item): bool */
    public static Closure $onTagCallback;

    public function __construct(MMOPlugin $plugin, Player $player, InvMenu $menu, CrateLootTable $lootTable, int $rouletteSize, int $rotations, int $mode = self::MODE_ONE)
    {
        $this->mode = $mode;
        $this->plugin = $plugin;
        $this->menu = $menu;

        for ($i = 0; $i < $rouletteSize; ++$i) {
            $item1 = $lootTable->randomEntry()->getItem();
            $item2 = $lootTable->randomEntry()->getItem();
            if ($item1->getCustomBlockData() === null) {
                $item1->setCustomBlockData(Utils::readOnlyTag());
            } else {
                $item1->getCustomBlockData()->setInt(Utils::READONLY_TAGS, 0);
            }

            if ($item2->getCustomBlockData() === null) {
                $item2->setCustomBlockData(Utils::readOnlyTag());
            } else {
                $item2->getCustomBlockData()->setInt(Utils::READONLY_TAGS, 0);
            }

            $this->items1[] = $item1;
            $this->items2[] = $item2;
        }

        $this->rotations = $rotations;
        $this->lootTables = $lootTable;
        $this->player = $player;
    }

    public function onRun(): void
    {
        $viewer = $this->player;

        if (--$this->rotations === 0) {
            $this->getHandler()->cancel();

            $inventory = $this->menu->getInventory();

            foreach ($inventory->getViewers() as $player) {
                $player->getWorld()->addSound($player->getPosition(), new EndermanTeleportSound(), [$player]);
            }

            /** @var Item[] $rewards */
            $rewards = [];
            switch ($this->mode) {
                case self::MODE_ONE:
                    $rewards[13] = $inventory->getItem(13);
                    $inventory->setContents(array_fill(0, 27, CrateListener::$glassIndicator));
                    break;
                case self::MODE_FIVE:
                case self::MODE_TEN:
                    for ($i = self::ROULETTE_PLACEMENTS[self::MODE_FIVE][0]; $i <= self::ROULETTE_PLACEMENTS[self::MODE_FIVE][1]; $i++) {
                        $rewards[$i] = $inventory->getItem($i);
                    }

                    if ($this->mode === self::MODE_TEN) {
                        for ($i = self::ROULETTE_PLACEMENTS[self::MODE_TEN][0]; $i <= self::ROULETTE_PLACEMENTS[self::MODE_TEN][1]; $i++) {
                            $rewards[$i] = $inventory->getItem($i);
                        }

                        $inventory->setContents(array_fill(0, 36, CrateListener::$glassIndicator));
                    } else {
                        $inventory->setContents(array_fill(0, 27, CrateListener::$glassIndicator));
                    }
                    break;
            }

            $rewardMessage = [];
            foreach ($rewards as $itemId => $reward) {
                $rewardMessage[] = TextFormat::GOLD . $reward->getCount() . 'x ' . TextFormat::clean($reward->getName()) . TextFormat::GREEN;
                $inventory->setItem($itemId, $reward);
            }

            $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($inventory, $rewards, $rewardMessage, $viewer): void {
                if ($viewer->isOnline()) {
                    $message = implode(", ", $rewardMessage);
                    $viewer->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . 'You won ' . $message . '!');
                }

                foreach ($rewards as $item) {
                    $this->sendReward($item, $viewer);
                }

                if ($viewer->isConnected() && in_array($viewer, $inventory->getViewers(), true)) {
                    CrateListener::sendCrate($viewer, $this->lootTables, $this->menu);
                }
            }), 20);
        } else if ($this->mode !== self::MODE_TEN) {
            array_unshift($this->items1, array_pop($this->items1));
            $rows = array_chunk($this->menu->getInventory()->getContents(true), 9);
            $this->menu->getInventory()->setContents(array_merge($rows[0], array_chunk($this->items1, 9)[0], $rows[2]));

            foreach ($this->menu->getInventory()->getViewers() as $viewer) {
                $viewer->getWorld()->addSound($viewer->getPosition(), new PopSound(), [$viewer]);
            }
        } else {
            array_unshift($this->items1, array_pop($this->items1));
            array_unshift($this->items2, array_pop($this->items2));
            $rows = array_chunk($this->menu->getInventory()->getContents(true), 9);
            $this->menu->getInventory()->setContents(array_merge($rows[0], array_chunk($this->items1, 9)[0], array_chunk($this->items2, 9)[0], $rows[3]));

            foreach ($this->menu->getInventory()->getViewers() as $viewer) {
                $viewer->getWorld()->addSound($viewer->getPosition(), new PopSound(), [$viewer]);
            }
        }
    }

    private function sendReward(Item $reward, Player $viewer): void
    {
        $nbt = $reward->getCustomBlockData();
        $nbt?->removeTag(Utils::READONLY_TAGS);

        $isItem = true;
        if ($nbt !== null) {
            if (ItemTypeIds::toBlockTypeId($reward->getTypeId()) === BlockTypeIds::TRIPWIRE_HOOK && Utils::hasTag($nbt, 'KeyDataType', IntTag::class)) {
                $this->plugin->getPlayerData()->increaseKey($viewer, $nbt->getInt('KeyDataType'), $reward->getCount());
                $isItem = false;
            } elseif ($reward->getTypeId() === CustomItemRegistry::MONEY_POUCH()->getTypeId() && Utils::hasTag($nbt, 'Min', IntTag::class) && Utils::hasTag($nbt, 'Max', IntTag::class)) {
                $reward = CustomItemManager::getMoneyPouch(mt_rand($nbt->getInt('Min'), $nbt->getInt('Max')));

                ItemStorage::createValidationId($reward, 'crate-' . $viewer->getName(), static function (Item $reward) use ($viewer) {
                    if ($viewer->isConnected()) {
                        $viewer->getInventory()->addItem($reward);
                    }
                });

                $isItem = false;
            } elseif (Utils::hasTag($nbt, 'CustomTagId') && Utils::hasTag($nbt, 'CustomTagName')) {
                $isItem = (self::$onTagCallback)($viewer, $reward);
            }
        }

        if ($viewer->isConnected()) {
            if ($isItem) {
                foreach ($viewer->getInventory()->addItem($reward) as $overflow) {
                    $viewer->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'There is not enough space in your inventory, the items were dropped!');

                    $viewer->dropItem($overflow);
                }
            }
        } else if ($isItem) {
            Inventory::addItemToPlayer(MMOPlugin::getInstance(), $viewer->getName(), $reward);
        }
    }

    public function onCancel(): void
    {
        unset(CrateListener::$pendingTasks[$this->player->getName()]);
    }
}