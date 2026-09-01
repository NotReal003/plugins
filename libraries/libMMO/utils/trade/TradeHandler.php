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

namespace libMMO\utils\trade;

use Generator;
use libMMO\MMOPlugin;
use libMMO\player\PlayerData;
use libMMO\utils\AwaitUtils;
use libMMO\utils\Utils;
use libVanilla\LibVanillaItems;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransaction;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\player\NGPlayer;
use NetherGames\NGEssentials\player\PlayerData as NGPlayerData;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\Listener;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;

class TradeHandler implements Listener
{
    // In order to secure the trade, we will move the item into a special inventory
    // where the player can collect them - but not place into them, it is like private vaults
    // but for traded items, this method can secure the traded items.
    // (Which are being written by trader, read-only by recipient and write outside player inventory).
    // For the trader, we just drop them into the ground, because we do not want them to abuse this feature.
    // limit is one inventory.

    // Trade storage system: A one special place where all trades go into, the condition to this is as follows:
    // 1) If the player has no contents stored in this storage, they can perform any trades.
    // 2) The recipients traded items will go in this storage system, (Case 1 will eliminate any existing contents in this storage).
    // 3) If the trader were in transfer mode, the contents will go to their storage system, but this replaces the previous storage contents (Intentionally).

    private const TRADE_ACCEPTED_RANGE = [
        0, 1, 2, 3, 4, 5, 6, 7, 8,
        9, 10, 11, 12, 13, 14, 15, 16, 17,
        18, 19, 20, 21, 22, 23, 24, 25, 26,
        27, 28, 29, 30, 31, 32, 33, 34, 35,
    ];

    public Player $tradePlayer;        // The trader (Read and write)
    public Player $recipientPlayer;    // The recipients from the trader (Read only)

    private InvMenu $tradeMenu;

    private int $transactionPrice;

    private bool $forceTradeCancel = false;
    private bool $transactionInProgress = false;
    private bool $tradeLocked = false;

    /** @var int */
    private int $tradeId; // Used to track trades, removing them if possible.

    /**
     * @var PromiseResolver|null
     * @phpstan-var PromiseResolver<null>
     */
    private PromiseResolver $resolver;

    public function __construct(int $tradeId, Player $trader, Player $recipient, int $transactionPrice)
    {
        $this->tradeId = $tradeId;
        $this->transactionPrice = $transactionPrice;

        $this->tradePlayer = $trader;
        $this->recipientPlayer = $recipient;

        $this->resolver = new PromiseResolver();

        $this->tradeMenu = InvMenu::create(MMOPlugin::MENU_CHEST_DOUBLE);
        $this->tradeMenu->setName("{$this->tradePlayer->getName()}'s trade");

        self::setDefaultContents($this->tradeMenu->getInventory(), $this->tradePlayer, $this->recipientPlayer, $transactionPrice);

        $this->tradeMenu->setListener(function (InvMenuTransaction $transaction) {
            if ($this->forceTradeCancel || $this->transactionInProgress) {
                return $transaction->discard();
            }

            $player = $transaction->getPlayer();

            if ($player->getId() !== $this->tradePlayer->getId()) {
                $result = $transaction->discard();

                $statusHolder = $transaction->getItemClicked();
                $blockData = $statusHolder->getCustomBlockData();

                if ($blockData !== null && $blockData->getCompoundTag("TradeInfo") !== null) {
                    if (!$this->tradeLocked) {
                        /** @var NGPlayer $player */
                        $player->playSound('mob.villager.no');
                    } else {
                        $info = $statusHolder->getCustomBlockData()->getInt("TradeData");

                        $this->tradeLocked = $info !== self::TRADE_REJECT_STATUS;
                        $this->transactionInProgress = $this->tradeLocked;

                        $this->tradeMenu->getInventory()->setItem(49, self::getInfoStatusItem($this->tradePlayer->getName(), $this->recipientPlayer->getName(), $this->transactionPrice, $info));
                        $this->tradeMenu->getInventory()->setItem(52, self::getTradeAcceptItem($info));
                        $this->tradeMenu->getInventory()->setItem(53, self::getTraderStatusItem($info));

                        if ($this->transactionInProgress) {
                            $this->processTransaction($this->tradePlayer, $this->recipientPlayer, $this->transactionPrice);
                        }
                    }
                }
            } else {
                $result = $transaction->continue();
                foreach ($transaction->getTransaction()->getActions() as $action) {
                    if ($action instanceof SlotChangeAction && !in_array($action->getSlot(), self::TRADE_ACCEPTED_RANGE, true)) {
                        $result = $transaction->discard();
                    }
                }

                $statusHolder = $transaction->getItemClicked();
                $blockData = $statusHolder->getCustomBlockData();

                if ($blockData !== null && $blockData->getCompoundTag("TradeInfo") !== null) {
                    $info = $blockData->getInt("TradeData");

                    $this->tradeLocked = $info === self::TRADE_ACCEPT_STATUS;

                    $this->tradeMenu->getInventory()->setItem(53, self::getTraderStatusItem($info));
                }

                $blockData = $this->tradeMenu->getInventory()->getItem(52)->getCustomBlockData();

                if ($blockData->getCompoundTag("RecipientInfo") !== null) {
                    $data = $blockData->getInt("RecipientData");

                    if ($data === self::TRADE_REJECT_STATUS) {
                        $this->tradeMenu->getInventory()->setItem(52, self::getTradeAcceptItem());
                    }
                }

                if (!$result->cancelled && $this->tradeLocked) {
                    $result = $transaction->discard();
                }
            }

            return $result;
        });

        // Let assume the player closes the inventory too soon, before the transaction completes,
        // we have to make sure the transaction is either completes, or failed before we pass the items into
        // their inventory again, the condition are as follows:
        // - If the transaction fails, we return the item to its original trader (Only if they were online, if not, we store in temporary trade cache).
        // - If the transaction completes, we return the items to the recipients (In temporary trade cache).

        $this->tradeMenu->setInventoryCloseListener(function (Player $player, Inventory $inventory): void {
            if ($this->forceTradeCancel) {
                return; // Handle this case ONCE
            }

            $this->forceTradeCancel = true;

            try {
                $otherPlayer = $this->getOtherPlayer($player);

                if ($this->transactionInProgress) {
                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Transaction is in progress, do not leave the game or your traded items will be lost forever!');
                    $otherPlayer?->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Transaction is in progress, do not leave the game or your traded items will be lost forever!');
                } else {
                    $this->revertTransaction();

                    TradeManager::getInstance()->removeTrade($this->tradeId);
                }

                if ($otherPlayer->getCurrentWindow() !== null) {
                    $otherPlayer->removeCurrentWindow();
                }
            } finally {
                $this->resolver->resolve(null);
            }
        });

        $this->tradeMenu->send($this->recipientPlayer);
        $this->tradeMenu->send($this->tradePlayer);
    }

    /**
     * Trading is forcefully closed by other reasons (i.e: server is restarting, player disconnected)
     * This method will close the player trade inventory, it will eventually call the "setInventoryCloseListener"
     * lambda to subsequently fire a promise.
     *
     * @param bool $gracefully close the current trade as it is designed to be.
     * @return void
     */
    public function closeCurrentTrade(bool $gracefully): void
    {
        $this->tradePlayer->removeCurrentWindow();

        if (!$gracefully) {
            $this->revertTransaction();
        }
    }

    public function processTransaction(Player $trader, Player $recipient, int $price): void
    {
        Await::f2c(function () use ($trader, $recipient, $price) {
            $successful = false;

            [$c1, $c2] = AwaitUtils::createOrCallback(yield);

            $plugin = MMOPlugin::getInstance();
            $plugin->getEconomyManager()->reducePlayerMoney($recipient->getName(), $price, $c1, $c2);

            [$cId] = yield Await::ONCE;

            if ($cId === 1) {
                $trader->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "That player does not have enough balance, the trade was cancelled.");
                $recipient->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You do not have enough money in your balance, the trade was cancelled.");

                $this->revertTransaction();
            } else {
                [$c1, $c2] = AwaitUtils::createOrCallback(yield);

                $plugin->getEconomyManager()->increasePlayerMoney($trader->getName(), $price, $c1, $c2);

                [$cId] = yield Await::ONCE;

                if ($cId === 1) {
                    [$c1, $c2] = AwaitUtils::createOrCallback(yield);

                    $plugin->getEconomyManager()->increasePlayerMoney($recipient->getName(), $price, $c1, $c2);
                    [$cId] = yield Await::ONCE;

                    $trader->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "We are unable to increase your balance, the trade was cancelled automatically.");

                    if ($cId === 1) {
                        $recipient->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Condition error, if you are getting this message, you are in trouble as your balance cannot be restored.");
                    } else {
                        $recipient->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "There were an issue while trying to send the money to the trader, the trade was cancelled, no balance were deducted.");
                    }

                    $this->revertTransaction();
                } else {
                    /** @var Item[] $items */
                    $items = iterator_to_array($this->getTradeContents());

                    yield from $this->addItemsIntoVaults($recipient, ...$items);

                    $successful = true;
                }
            }

            $this->transactionInProgress = false;

            if ($successful) {
                $trader->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . "The trade was successful, you received " . TextFormat::GRAY . "$" . number_format($price) . TextFormat::GREEN . " in advance for the goods you sold.");
                $recipient->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . "The trade was successful, " . TextFormat::GRAY . "$" . number_format($price) . TextFormat::GREEN . " was deducted from your balance. Receive your traded items using /trade storage");
            }

            if (!$this->forceTradeCancel) {
                $trader->removeCurrentWindow();
            }
        });
    }

    /**
     * Inverse of the trade player, which is the recipient in this case, vice-versa
     * for the recipient. This function will return null if the other player was not found.
     *
     * @param Player $player
     * @return Player|null
     */
    private function getOtherPlayer(Player $player): ?Player
    {
        if ($this->tradePlayer->getId() === $player->getId()) {
            return $this->recipientPlayer;
        } else if ($this->recipientPlayer->getId() === $player->getId()) {
            return $this->tradePlayer;
        }

        return null;
    }

    /**
     * Reverts all transactions done from the trader itself, the items will be moved to
     * the trader's inventory, dropped if their inventory is full, and clears the trade inventory.
     *
     * @return void
     */
    private function revertTransaction(): void
    {
        $items = iterator_to_array($this->getTradeContents());

        // The hot-case scenario, the trader has decided to go on transfer mode, this case is considered the hotspot
        // where player is able to dupe the item (possibly?). This however will replace the previous trade cache.
        // Sometimes, to defeat the devil, we have to become the devil.
        if (!$this->tradePlayer->isConnected() || NGEssentials::getInstance()->getPlayerData()->getBool($this->tradePlayer, NGPlayerData::TRANSFER)) {
            $rawContents = [];
            foreach ($items as $item) {
                $rawContents[] = Utils::zlibEncodeItem($item);
            }

            $playerName = $this->tradePlayer->getName();

            $playerData = MMOPlugin::getInstance()->getPlayerData();
            $playerData->setValue($playerName, PlayerData::PLAYER_TRADE_CACHE, $rawContents, true);
            if (!$playerData->getBool($playerName, PlayerData::DATA_LOADED)) {
                $playerData->saveValue($playerName, PlayerData::PLAYER_TRADE_CACHE);
            }
        } else {
            $leftover = [];

            foreach ($items as $item) {
                $leftover = array_merge($leftover, $this->tradePlayer->getInventory()->addItem($item));
            }

            foreach ($leftover as $item) {
                $this->tradePlayer->getWorld()->dropItem($this->tradePlayer->getPosition()->asVector3(), $item);
            }
        }
    }

    /**
     * Add items into the recipient temporary trade cache, this will make sure the trade is secure from any
     * unexpected scenarios. (i.e. being killed, disconnected from the server)
     *
     * @param Player $player
     * @param Item ...$items
     * @return Generator
     */
    private function addItemsIntoVaults(Player $player, Item ...$items): Generator
    {
        $rawContents = [];
        foreach ($items as $item) {
            $rawContents[] = Utils::zlibEncodeItem($item);
        }

        $playerName = $player->getName();

        $playerData = MMOPlugin::getInstance()->getPlayerData();

        $playerData->addCallbackToPlayer($playerName, yield, true);
        $playerData->setValue($playerName, PlayerData::PLAYER_TRADE_CACHE, $rawContents, true);
        if (!$playerData->getBool($playerName, PlayerData::DATA_LOADED)) {
            $playerData->saveValue($playerName, PlayerData::PLAYER_TRADE_CACHE);
        }

        return yield Await::ONCE;
    }

    /**
     * @return Generator The contents of the trade, will reset the trade contents after all items has been retained.
     */
    private function getTradeContents(): Generator
    {
        foreach ($this->tradeMenu->getInventory()->getContents() as $slot => $item) {
            if (in_array($slot, self::TRADE_ACCEPTED_RANGE, true)) {
                yield $item;
            }
        }

        $this->tradeMenu->getInventory()->clearAll(); // Safety precaution.
    }

    ############################################### INTERNAL USE CASES ################################################

    private const TRADE_DEFAULT_STATUS = 0;
    private const TRADE_ACCEPT_STATUS = 1;
    private const TRADE_REJECT_STATUS = 2;

    private static function setDefaultContents(Inventory $inventory, Player $trader, Player $recipient, int $price): void
    {
        $contents = [];

        for ($n = 36; $n < 45; $n++) {
            $contents[$n] = VanillaBlocks::IRON_BARS()->asItem()->setCustomName(TextFormat::RESET . TextFormat::RED . TextFormat::BOLD . "Separator");
        }

        $contents[45] = VanillaBlocks::CONCRETE()->setColor(DyeColor::GREEN)->asItem()
            ->setCustomBlockData(CompoundTag::create()->setTag("TradeInfo", new CompoundTag())->setInt("TradeData", self::TRADE_ACCEPT_STATUS))
            ->setCustomName(TextFormat::RESET . TextFormat::GREEN . "Accept trade");
        $contents[46] = VanillaBlocks::CONCRETE()->setColor(DyeColor::RED)->asItem()
            ->setCustomBlockData(CompoundTag::create()->setTag("TradeInfo", new CompoundTag())->setInt("TradeData", self::TRADE_REJECT_STATUS))
            ->setCustomName(TextFormat::RESET . TextFormat::RED . "Reject trade");

        $contents[49] = self::getInfoStatusItem($trader->getName(), $recipient->getName(), $price);

        $contents[52] = self::getTradeAcceptItem();
        $contents[53] = self::getTraderStatusItem();

        $inventory->setContents($contents);
    }

    private static function getTradeAcceptItem(int $tradeStatus = self::TRADE_DEFAULT_STATUS): Item
    {
        if ($tradeStatus === self::TRADE_DEFAULT_STATUS) {
            return VanillaItems::ENDER_PEARL()->setCustomName(TextFormat::RESET . TextFormat::YELLOW . 'Recipient status')
                ->setCustomBlockData(CompoundTag::create()->setTag("RecipientInfo", new CompoundTag())->setInt("RecipientData", $tradeStatus))
                ->setLore([
                    TextFormat::RESET,
                    TextFormat::RESET . TextFormat::GRAY . "You may observe the trader's traded",
                    TextFormat::RESET . TextFormat::GRAY . "items before transaction begin.",
                    TextFormat::RESET,
                    TextFormat::RESET . TextFormat::GRAY . "Once the trader is ready, you can choose",
                    TextFormat::RESET . TextFormat::GRAY . "to accept or reject their trades."
                ]); // Ender pearl -> Eye of ender
        } else if ($tradeStatus === self::TRADE_ACCEPT_STATUS) {
            return LibVanillaItems::ENDER_EYE()->setCustomName(TextFormat::RESET . TextFormat::YELLOW . 'Trade accepted')
                ->setCustomBlockData(CompoundTag::create()->setTag("RecipientInfo", new CompoundTag())->setInt("RecipientData", $tradeStatus))
                ->setLore([
                    TextFormat::RESET,
                    TextFormat::RESET . TextFormat::GRAY . "The recipient has accepted the trader's",
                    TextFormat::RESET . TextFormat::GRAY . "trades, please wait for the transaction",
                    TextFormat::RESET . TextFormat::GRAY . "to complete.",
                ]); // Ender pearl -> Eye of ender/Barrier
        } else {
            return VanillaBlocks::BARRIER()->asItem()->setCustomName(TextFormat::RESET . TextFormat::RED . 'Trade rejected')
                ->setCustomBlockData(CompoundTag::create()->setTag("RecipientInfo", new CompoundTag())->setInt("RecipientData", $tradeStatus))
                ->setLore([
                    TextFormat::RESET,
                    TextFormat::RESET . TextFormat::GRAY . "The recipient has rejected your trade.",
                    TextFormat::RESET,
                    TextFormat::RESET . TextFormat::GRAY . "You may edit your trade if you were the",
                    TextFormat::RESET . TextFormat::GRAY . "trader until recipient satisfies with your",
                    TextFormat::RESET . TextFormat::GRAY . "trade or cancels this trade.",
                ]);
        }
    }

    private static function getTraderStatusItem(int $traderStatus = self::TRADE_REJECT_STATUS): Item
    {
        if ($traderStatus === self::TRADE_REJECT_STATUS) {
            return VanillaItems::GUNPOWDER()->setCustomName(TextFormat::RESET . TextFormat::YELLOW . "Trader status")->setLore([
                TextFormat::RESET,
                TextFormat::RESET . TextFormat::GRAY . 'The trader is not ready to start the',
                TextFormat::RESET . TextFormat::GRAY . 'trade just yet, wait until the trader',
                TextFormat::RESET . TextFormat::GRAY . 'is ready to start the trading.',
            ]);   // Gunpowder   -> Redstone
        } else {
            return VanillaItems::REDSTONE_DUST()->setCustomName(TextFormat::RESET . TextFormat::GREEN . "Trader is ready")->setLore([
                TextFormat::RESET,
                TextFormat::RESET . TextFormat::GRAY . 'The trader is now ready to start the',
                TextFormat::RESET . TextFormat::GRAY . 'trade, you may decide to accept or reject',
                TextFormat::RESET . TextFormat::GRAY . 'their traded items.',
            ]);   // Gunpowder   -> Redstone
        }
    }

    private static function getInfoStatusItem(string $trader, string $recipient, int $price, int $tradeStatus = self::TRADE_DEFAULT_STATUS): Item
    {
        // In case the transaction was failed, we just close their trades respectively.
        if ($tradeStatus === self::TRADE_ACCEPT_STATUS) {
            return VanillaItems::PAPER()->setCustomName(TextFormat::RESET . TextFormat::RED . TextFormat::BOLD . "Transaction in progress")->setLore([
                TextFormat::RESET,
                TextFormat::RESET . TextFormat::GRAY . "Closing this trade too soon may resulting",
                TextFormat::RESET . TextFormat::GRAY . "all your traded items to be lost during",
                TextFormat::RESET . TextFormat::GRAY . "transaction!",
            ]);
        } else {
            return VanillaItems::PAPER()->setCustomName(TextFormat::RESET . TextFormat::GRAY . "$trader's trade")->setLore([
                TextFormat::RESET,
                TextFormat::RESET . TextFormat::GOLD . "Trade value: " . TextFormat::WHITE . "$" . number_format($price),
                TextFormat::RESET . TextFormat::GOLD . "Recipient: " . TextFormat::WHITE . "$recipient",
            ]);
        }
    }

    public function getFuture(): Promise
    {
        return $this->resolver->getPromise();
    }
}