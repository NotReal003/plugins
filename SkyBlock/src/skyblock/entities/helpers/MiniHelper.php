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

namespace skyblock\entities\helpers;

use libforms\elements\Button;
use libforms\elements\ImageButton;
use libforms\FormManager;
use libMMO\item\ItemStorage;
use libMMO\player\Inventory;
use libMMO\utils\RomanNumbers;
use libMMO\utils\Utils;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\BaseInventory;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use skyblock\islands\Island;
use skyblock\item\CustomItemManager;
use skyblock\player\PlayerData;
use skyblock\SkyBlock;
use skyblock\utils\NonDespawnEntity;
use function number_format;

abstract class MiniHelper extends Human implements NonDespawnEntity
{
    public const TIER_TAG = 'tier';
    public const SLOTS_TAG = 'slots';
    public const EFFICIENCY_TAG = 'efficiency';
    public const HELPER_INVENTORY_TAG = 'helperInventory';

    protected const INV_NAME = 'Mini-Helper Inventory';

    protected const PRICE = -1;

    public const TIER_WOOD = 0;
    public const TIER_STONE = 1;
    public const TIER_IRON = 2;
    public const TIER_GOLD = 3;
    public const TIER_DIAMOND = 4;

    protected static ?array $tiers = null;

    public const LUMBERJACK = 0;
    public const MINER = 1;
    public const HARVESTER = 2;

    /** @var int */
    protected int $tier = self::TIER_WOOD;
    /** @var bool */
    protected bool $jobStart = false;
    /** @var Item */
    protected Item $item;
    /** @var int */
    protected int $jobTick = 0;
    /** @var int */
    protected int $slots = 1;
    /** @var int */
    protected int $efficiency = 0;
    /** @var InvMenu */
    protected InvMenu $helperMenu;
    /** @var int */
    private int $delayTick = 0;
    /** @var bool */
    private bool $delayProcessed = false;
    /** @var int */
    private int $jobType;
    /** @var bool */
    private bool $full = false;

    public static function getTiers(): array
    {
        if (self::$tiers === null) {
            self::$tiers = [
                self::TIER_WOOD => [
                    self::LUMBERJACK => VanillaItems::WOODEN_AXE(),
                    self::MINER => VanillaItems::WOODEN_PICKAXE(),
                    self::HARVESTER => VanillaItems::WOODEN_HOE()
                ],
                self::TIER_STONE => [
                    self::PRICE => 1500,
                    self::LUMBERJACK => VanillaItems::STONE_AXE(),
                    self::MINER => VanillaItems::STONE_PICKAXE(),
                    self::HARVESTER => VanillaItems::STONE_HOE()
                ],
                self::TIER_IRON => [
                    self::PRICE => 5000,
                    self::LUMBERJACK => VanillaItems::IRON_AXE(),
                    self::MINER => VanillaItems::IRON_PICKAXE(),
                    self::HARVESTER => VanillaItems::IRON_HOE()
                ],
                self::TIER_GOLD => [
                    self::PRICE => 10000,
                    self::LUMBERJACK => VanillaItems::GOLDEN_AXE(),
                    self::MINER => VanillaItems::GOLDEN_PICKAXE(),
                    self::HARVESTER => VanillaItems::GOLDEN_HOE()
                ],
                self::TIER_DIAMOND => [
                    self::PRICE => 20000,
                    self::LUMBERJACK => VanillaItems::DIAMOND_AXE(),
                    self::MINER => VanillaItems::DIAMOND_PICKAXE(),
                    self::HARVESTER => VanillaItems::DIAMOND_HOE()
                ]
            ];
        }

        return self::$tiers;
    }

    public function __construct(Location $location, CompoundTag $nbt, int $jobType, ?Island $island = null, ?Player $player = null)
    {
        parent::__construct($location, ($player === null) ? Human::parseSkinNBT($nbt) : $player->getSkin());

        $this->setNameTagVisible();
        $this->setNameTagAlwaysVisible();

        $this->helperMenu = InvMenu::create('skyblock:minihelper');
        $this->helperMenu->setName(self::INV_NAME);
        $this->helperMenu->setListener(function (InvMenuTransaction $transaction): InvMenuTransactionResult {
            if ($transaction->getItemClicked()->getBlock()->getTypeId() === BlockTypeIds::STAINED_GLASS_PANE) {
                return $transaction->discard();
            }

            if ($this->isClosed() || $this->isFlaggedForDespawn()) {
                return $transaction->discard();
            }

            return $transaction->continue();
        });

        $this->tier = $nbt->getInt(self::TIER_TAG, self::TIER_WOOD);
        $this->slots = $nbt->getInt(self::SLOTS_TAG, 1);
        $this->efficiency = $nbt->getInt(self::EFFICIENCY_TAG, 0);

        if ($island === null || Utils::hasTag($nbt, self::HELPER_INVENTORY_TAG)) {
            /** @var ListTag $inventoryTag */
            $inventoryTag = $nbt->getListTag(self::HELPER_INVENTORY_TAG);

            $inventoryContents = [];
            foreach ($inventoryTag as $itemSerialized) {
                if ($itemSerialized instanceof CompoundTag) {
                    $inventoryContents[$itemSerialized->getByte('Slot')] = Item::nbtDeserialize($itemSerialized);
                }
            }

            $this->getHelperMenu()->getInventory()->setContents($inventoryContents);
        } else {
            $inventory = $this->getHelperMenu()->getInventory();
            for ($i = $this->getSlots(); $i <= $inventory->getSize() - 1; $i++) {
                $inventory->setItem($i, VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::RED)->asItem()->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . 'Slot Unavailable')->setLore(['', TextFormat::RESET . TextFormat::GRAY . "Upgrade your mini-helper's slots to unlock more space!"]));
            }
        }

        $this->jobType = $jobType;

        $this->setScale(0.75);
        $this->inventory->setItemInHand($this->item = $this->getTierTool($this->tier));
    }

    protected function getInitialDragMultiplier(): float { return 0.0; }
    protected function getInitialGravity(): float { return 0.0; }

    /**
     * Get the helper's menu.
     *
     * @return InvMenu
     */
    public function getHelperMenu(): InvMenu
    {
        return $this->helperMenu;
    }

    public function getSlots(): int
    {
        return $this->slots;
    }

    public function getTierTool(int $tier): Item
    {
        $tiers = self::getTiers();

        /** @var Durable $item */
        $item = $tiers[$tier][$this->jobType] ?? $tiers[self::TIER_WOOD][$this->jobType];

        if ($this->efficiency > 0) {
            $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), $this->efficiency));
        }
        $item->setUnbreakable(true);

        return $item;
    }

    public function getBreakTime(Item $item, Block $block): float
    {
        $base = $block->getBreakInfo()->getHardness();
        if ($block->getBreakInfo()->isToolCompatible($item)) {
            $base *= 1.5;
        } else {
            $base *= 5;
        }

        if (($level = $item->getEnchantmentLevel(VanillaEnchantments::EFFICIENCY())) < 2) {
            $base *= (2 - $level);
        }

        return $base;
    }

    public function getJobType(): int
    {
        return $this->jobType;
    }

    public function saveNBT(): CompoundTag
    {
        $nbt = parent::saveNBT();

        $nbt->setInt(self::TIER_TAG, $this->getTier());
        $nbt->setInt(self::SLOTS_TAG, $this->getSlots());
        $nbt->setInt(self::EFFICIENCY_TAG, $this->getEfficiency());

        /** @var BaseInventory $inventory */
        $inventory = $this->getHelperMenu()->getInventory();
        $nbt->setTag(self::HELPER_INVENTORY_TAG, Inventory::convertInventoryToNBT($inventory));

        return $nbt;
    }

    public function getTier(): int
    {
        return $this->tier;
    }

    public function getEfficiency(): int
    {
        return $this->efficiency;
    }

    /**
     * Runs at the entity's base tick.
     *
     * @param int $tickDiff
     * @return bool
     */
    public function entityBaseTick(int $tickDiff = 1): bool
    {
        if ($this->delayProcessed) {
            if ($this->handleJob()) {
                $this->delayProcessed = false;
            }
        } else {
            $this->helperDelayTick();
        }

        return parent::entityBaseTick($tickDiff);
    }

    /**
     * Handle the job of the helper.
     *
     * @return bool
     */
    abstract protected function handleJob(): bool;

    /**
     * Handle the entity delay tick.
     *
     * @return void
     */
    private function helperDelayTick(): void
    {
        $this->delayTick++;

        if ($this->delayTick === 20 * 2) {
            $this->delayTick = 0;
            $this->delayProcessed = true;

            $this->checkFull();
        }
    }

    private function checkFull(): void
    {
        $full = $this->getHelperMenu()->getInventory()->firstEmpty() === -1;
        if ($full) {
            if (!$this->full) {
                $this->setNameTag(TextFormat::RED . 'FULL');
                $this->full = true;
            }
            return;
        }

        if ($this->full) {
            $this->setNameTag('');
            $this->full = false;
        }
    }

    public function attack(EntityDamageEvent $source): void
    {
        $source->call();

        if (!$source->isCancelled()) {
            if ($source instanceof EntityDamageByEntityEvent) {
                $player = $source->getDamager();

                if ($player instanceof Player) {
                    $this->sendMiniHelperForm($player);
                }
            } else {
                $source->cancel();
            }
        }
    }

    private function sendMiniHelperForm(Player $player): void
    {
        $form = FormManager::createSimpleForm($player);

        if ($form !== null) {
            $form->setTitle('Mini-Helper Menu');

            $form->addButton(new Button(TextFormat::YELLOW . 'Open Inventory', function (Player $player) {
                if (!$this->isClosed() && !$this->isFlaggedForDespawn() && $player->getWorld()->getId() === $this->getWorld()->getId()) {
                    $this->getHelperMenu()->send($player);
                }
            }));
            $form->addButton(new Button(TextFormat::YELLOW . 'Upgrades', function (Player $player) {
                $this->sendUpgradesForm($player);
            }));
            $form->addButton(new ImageButton(TextFormat::RED . TextFormat::BOLD . 'Remove', ImageButton::IMAGE_TYPE_PATH, 'textures/blocks/barrier', function (Player $player) {
                if ($this->isClosed() || $this->isFlaggedForDespawn()) {
                    return;
                }

                $plugin = SkyBlock::getInstance();
                $island = $plugin->getIslandManager()->getIslandByWorld($this->getWorld());

                if ($island !== null && !$this->isFlaggedForDespawn()) { /** @phpstan-ignore-line */
                    if (count($this->getHelperMenu()->getInventory()->getViewers()) === 0) {
                        $contents = $this->getHelperMenu()->getInventory()->getContents(true);
                        $contents = array_filter(array_splice($contents, 0, $this->getSlots()), function (Item $item): bool {
                            return !$item->isNull();
                        });

                        if (count($contents) === 0) {
                            if ($island->removeHelper($player, $this)) {
                                $this->flagForDespawn();

                                $miniHelper = CustomItemManager::getMiniHelperFrom($this);

                                ItemStorage::createValidationId($miniHelper, 'respawn-' . $player->getName(), static function (Item $item) use ($player) {
                                    if ($player->isConnected()) {
                                        $residue = $player->getInventory()->addItem($item);

                                        foreach ($residue as $item) {
                                            $player->getWorld()->dropItem($player->getPosition(), $item);
                                        }
                                    }
                                });
                            }
                        } else {
                            $player->sendMessage(TextFormat::RED . "The inventory of your mini-helper is not empty, so your mini-helper couldn't be removed.");
                        }
                    } else {
                        $player->sendMessage(TextFormat::RED . "There's someone in the inventory of this mini-helper.");
                    }
                }
            }));

            $form->sendForm();
        }
    }

    private function sendUpgradesForm(Player $player): void
    {
        if ($this->isClosed() || $this->isFlaggedForDespawn()) {
            return;
        }

        $form = FormManager::createSimpleForm($player);

        if ($form !== null) {
            $form->setTitle('Mini-Helper Upgrades');

            $tier = $this->tier + 1;
            $tierPrice = self::getTierPrice($tier);
            $efficiency = $this->efficiency + 1;
            $efficiencyPrice = self::getEfficiencyPrice($efficiency);
            $slots = $this->slots + 1;
            $slotPrice = self::getSlotPrice($slots);

            $plugin = SkyBlock::getInstance();
            $playerData = $plugin->getPlayerData();
            $economyManager = $plugin->getEconomyManager();
            $pocketMoney = $playerData->getInt($player, PlayerData::PLAYER_MONEY);

            if ($tierPrice === null) {
                $tierPriceString = TextFormat::RED . 'Fully upgraded';
            } elseif ($pocketMoney >= $tierPrice) {
                $tierPriceString = TextFormat::GREEN . '$' . number_format($tierPrice);
            } else {
                $tierPriceString = TextFormat::RED . '$' . number_format($tierPrice);
            }

            if ($efficiencyPrice === null) {
                $efficiencyPriceString = TextFormat::RED . 'Fully upgraded';
            } elseif ($pocketMoney >= $efficiencyPrice) {
                $efficiencyPriceString = TextFormat::GREEN . '$' . number_format($efficiencyPrice);
            } else {
                $efficiencyPriceString = TextFormat::RED . '$' . number_format($efficiencyPrice);
            }

            if ($slotPrice === null) {
                $slotPriceString = TextFormat::RED . 'Fully upgraded';
            } elseif ($pocketMoney >= $slotPrice) {
                $slotPriceString = TextFormat::GREEN . '$' . number_format($slotPrice);
            } else {
                $slotPriceString = TextFormat::RED . '$' . number_format($slotPrice);
            }

            $form->addButton(new Button(TextFormat::YELLOW . 'Tier Upgrade' . TextFormat::EOL . $tierPriceString, function (Player $player) use ($tier, $tierPrice, $tierPriceString, $economyManager) {
                if ($this->isClosed() || $this->isFlaggedForDespawn()) {
                    return;
                }

                if ($tierPrice === null) {
                    $this->sendUpgradesForm($player);
                } else {
                    $form = FormManager::createModalForm($player);

                    if ($form !== null) {
                        $form->setTitle('Mini-Helper Upgrades');

                        $tool = $this->getTierTool($tier);
                        $form->setContent('Upgrade the tier of this helper to ' . $tool->getName() . TextFormat::EOL . TextFormat::EOL . 'Price: ' . $tierPriceString);

                        $form->setButton1(new Button(TextFormat::GREEN . 'Upgrade', function (Player $player) use ($economyManager, $tool, $tier, $tierPrice) {
                            if ($this->isClosed() || $this->isFlaggedForDespawn()) {
                                return;
                            }

                            $economyManager->reducePlayerMoney($player->getName(), $tierPrice, function () use ($tier, $tool, $player) {
                                $this->tier = $tier;

                                $this->inventory->setItemInHand($this->item = $tool);

                                if ($player->isConnected()) {
                                    $player->sendMessage(TextFormat::GREEN . "Your mini-helper's tier has been upgraded to " . TextFormat::GOLD . $tool->getName() . TextFormat::GREEN . '!');
                                }
                            });
                        }));

                        $form->setButton2(new Button(TextFormat::RED . 'Back', function (Player $player) {
                            $this->sendUpgradesForm($player);
                        }));
                        $form->sendForm();
                    }
                }
            }));

            $form->addButton(new Button(TextFormat::YELLOW . 'Efficiency Upgrade' . TextFormat::EOL . $efficiencyPriceString, function (Player $player) use ($efficiency, $efficiencyPrice, $efficiencyPriceString, $economyManager) {
                if ($this->isClosed() || $this->isFlaggedForDespawn()) {
                    return;
                }

                if ($efficiencyPrice === null) {
                    $this->sendUpgradesForm($player);
                } else {
                    $form = FormManager::createModalForm($player);

                    if ($form !== null) {
                        $form->setTitle('Mini-Helper Upgrades');

                        $form->setContent('Upgrade the efficiency of this helper to ' . TextFormat::GREEN . 'Efficiency ' . RomanNumbers::getRomanNumber($efficiency) . TextFormat::RESET . TextFormat::EOL . TextFormat::EOL . 'Price: ' . $efficiencyPriceString);

                        $form->setButton1(new Button(TextFormat::GREEN . 'Upgrade', function (Player $player) use ($economyManager, $efficiency, $efficiencyPrice) {
                            if ($this->isClosed() || $this->isFlaggedForDespawn()) {
                                return;
                            }

                            $economyManager->reducePlayerMoney($player->getName(), $efficiencyPrice, function () use ($efficiency, $player) {
                                $this->efficiency = $efficiency;

                                $this->inventory->setItemInHand($this->item = $this->getTierTool($this->tier));

                                if ($player->isConnected()) {
                                    $player->sendMessage(TextFormat::GREEN . "Your mini-helper's efficiency has been upgraded to " . TextFormat::GOLD . 'Efficiency ' . RomanNumbers::getRomanNumber($efficiency) . TextFormat::GREEN . '!');
                                }
                            });
                        }));

                        $form->setButton2(new Button(TextFormat::RED . 'Back', function (Player $player) {
                            $this->sendUpgradesForm($player);
                        }));
                        $form->sendForm();
                    }
                }
            }));

            $form->addButton(new Button(TextFormat::YELLOW . 'Slots Upgrade' . TextFormat::EOL . $slotPriceString, function (Player $player) use ($slots, $slotPrice, $slotPriceString, $economyManager) {
                if ($this->isClosed() || $this->isFlaggedForDespawn()) {
                    return;
                }

                if ($slotPrice === null) {
                    $this->sendUpgradesForm($player);
                } else {
                    $form = FormManager::createModalForm($player);

                    if ($form !== null) {
                        $form->setTitle('Mini-Helper Upgrades');

                        $form->setContent('Upgrade the amount of slots of this helper to ' . $slots . TextFormat::EOL . TextFormat::EOL . 'Price: ' . $slotPriceString);

                        $form->setButton1(new Button(TextFormat::GREEN . 'Upgrade', function (Player $player) use ($economyManager, $slots, $slotPrice) {
                            if ($this->isClosed() || $this->isFlaggedForDespawn()) {
                                return;
                            }

                            $economyManager->reducePlayerMoney($player->getName(), $slotPrice, function () use ($slots, $player) {
                                $this->getHelperMenu()->getInventory()->setItem($slots - 1, VanillaItems::AIR());

                                $this->slots = $slots;

                                if ($player->isConnected()) {
                                    $player->sendMessage(TextFormat::GREEN . "Your mini-helper's slots has been upgraded to " . TextFormat::GOLD . $slots . TextFormat::GREEN . '!');
                                }
                            });
                        }));

                        $form->setButton2(new Button(TextFormat::RED . 'Back', function (Player $player) {
                            $this->sendUpgradesForm($player);
                        }));
                        $form->sendForm();
                    }
                }
            }));

            $form->addButton(new ImageButton(TextFormat::RED . TextFormat::BOLD . 'Back', ImageButton::IMAGE_TYPE_PATH, 'textures/blocks/barrier', function (Player $player) {
                $this->sendMiniHelperForm($player);
            }));

            $form->sendForm();
        }
    }

    public static function getTierPrice(int $tier): ?int
    {
        return self::getTiers()[$tier][self::PRICE] ?? null;
    }

    public static function getEfficiencyPrice(int $efficiency): ?int
    {
        switch ($efficiency) {
            case 1:
                return 1500;
            case 2:
                return 3000;
            default:
                return null;
        }
    }

    public static function getSlotPrice(int $slot): ?int
    {
        if ($slot <= (28 - 1)) {
            return 1000;
        }

        return null;
    }

    public function onDispose(): void
    {
        $inventory = $this->helperMenu->getInventory();
        if ($inventory instanceof BaseInventory) {
            $inventory->removeAllViewers();
        }

        parent::onDispose();
    }

    public function canBeMovedByCurrents(): bool
    {
        return false;
    }

    public function setMotion(Vector3 $motion): bool
    {
        return false;
    }

    /**
     * Sends the block breaking animation to the client.
     *
     * @param Block $block
     * @return void
     */
    protected function sendCustomAnimation(Block $block): void
    {
        $eventId = LevelEvent::BLOCK_START_BREAK;
        $clientTime = (int)(65535 / $this->jobTick);
        $pos = $block->getPosition();
        $this->getWorld()->broadcastPacketToViewers($pos->asVector3(), LevelEventPacket::create($eventId, $clientTime, $pos));
    }

    /**
     * Sends the arm swinging client animation to the client.
     *
     * @return void
     */
    protected function sendClientArmAnimation(): void
    {
        $this->getWorld()->broadcastPacketToViewers($this->getPosition()->asVector3(), AnimatePacket::create(
            $this->id,
            AnimatePacket::ACTION_SWING_ARM
        ));
    }
}