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

use Closure;
use Generator;
use libforms\elements\Button;
use libforms\FormManager;
use libMMO\EventListener;
use libMMO\MMOPlugin;
use libMMO\player\PlayerData;
use libMMO\utils\Utils;
use muqsit\invmenu\InvMenu;
use NetherGames\NGEssentials\player\Translator;
use pocketmine\player\Player;
use pocketmine\promise\Promise;
use pocketmine\scheduler\Task;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;
use Throwable;

/**
 * Trade manager, used to manage trades from player
 */
class TradeManager extends Task
{
    use SingletonTrait;

    public const TRADE_TIMEOUT_SECONDS = 60;

    /** @var bool */
    public static bool $tradesEnabled = true; // Indicates that we are currently open for trades.
    /** @var int */
    private static int $tradeId = 0;

    /** @var array[] */
    private array $pendingTrades = []; // sender, receiver, price, time
    /** @var TradeHandler[] */
    private array $activeTrades = [];
    /** @var true[] */
    private array $claimTradesLock = [];

    /** @var MMOPlugin */
    private MMOPlugin $plugin;

    public function __construct(MMOPlugin $plugin)
    {
        $this->plugin = $plugin;

        $plugin->getScheduler()->scheduleRepeatingTask($this, 20);

        self::setInstance($this);
    }

    /**
     * This method loads trade cache from database, if the player wants to claim these items, instead use
     * {@see claimTradeItems()} method. This method should be used as a read only items.
     *
     * @param Player $recipient
     * @param Closure $callback
     * @return void
     */
    public function viewTradeCache(Player $recipient, Closure $callback): void
    {
        Await::f2c(function () use ($recipient, $callback): Generator {
            $playerData = $this->plugin->getPlayerData();

            $playerData->loadValue($recipient->getName(), PlayerData::PLAYER_TRADE_CACHE, yield);
            $data = yield Await::ONCE;

            $items = [];
            foreach ($data as $itemRaw) {
                try {
                    $items[] = Utils::decodeItem($itemRaw);
                } catch (Throwable) {
                    $this->plugin->getLogger()->warning("Unable to load item: " . $itemRaw);
                }
            }

            $callback($items);
        });
    }

    public function claimTradeItems(Player $recipient, bool $claimAll = false): void
    {
        Await::f2c(function () use ($recipient, $claimAll): Generator {
            $playerData = $this->plugin->getPlayerData();

            $playerData->loadValue($recipient->getName(), PlayerData::PLAYER_TRADE_CACHE, yield);
            $data = yield Await::ONCE;

            if (isset($this->claimTradesLock[$recipient->getName()])) {
                $recipient->sendMessage(TextFormat::RED . TextFormat::RED . "Please wait for a few moments after running the same command again.");
                return false;
            }

            $this->claimTradesLock[$recipient->getName()] = true;

            if (!$this->plugin->getPlayerManager()->canDoTransactions($recipient)) {
                $recipient->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You cannot claim trade items right now, please disable your flight settings.");
                return true;
            }

            if (empty($data)) {
                $recipient->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "There is no item in your trade storage content.");
            } else if (!$claimAll) {
                $invMenu = InvMenu::create(MMOPlugin::MENU_CHEST_DOUBLE);
                $invMenu->setListener(InvMenu::readonly());

                foreach ($data as $itemRaw) {
                    $invMenu->getInventory()->addItem(Utils::decodeItem($itemRaw));
                }

                $invMenu->setName($recipient->getName() . "'s trade storage");
                $invMenu->send($recipient);
            } else {
                $leftovers = [];
                $rawContents = [];
                $pendingQueue = [];

                foreach ($data as $itemRaw) {
                    $pendingQueue[] = Utils::decodeItem($itemRaw);
                }

                foreach ($pendingQueue as $item) {
                    $leftovers = array_merge($leftovers, $recipient->getInventory()->addItem($item));
                }

                if (!empty($leftovers)) {
                    foreach ($leftovers as $item) {
                        $rawContents[] = Utils::zlibEncodeItem($item);
                    }
                }

                // This can cause unwanted dupe if the server has "database lag", lets hope that did not happen
                // after we get database clustering online.

                $playerData->addCallbackToPlayer($recipient->getName(), yield, true);
                $playerData->setValue($recipient->getName(), PlayerData::PLAYER_TRADE_CACHE, $rawContents, true);
                if (!$playerData->getBool($recipient->getName(), PlayerData::DATA_LOADED)) {
                    $playerData->saveValue($recipient->getName(), PlayerData::PLAYER_TRADE_CACHE);
                }

                yield Await::ONCE;

                $recipient->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . "You have received all your trades content.");
            }

            return true;
        }, function (bool $success) use ($recipient) {
            if ($success) {
                unset($this->claimTradesLock[$recipient->getName()]);
            }
        }, function (Throwable $error) use ($recipient) {
            unset($this->claimTradesLock[$recipient->getName()]);

            $this->plugin->getLogger()->logException($error);

            if ($recipient->isConnected()) {
                $recipient->sendMessage(Translator::getTranslationPlayer($recipient, 'db.error'));
            }
        });
    }

    /**
     *
     * @param Player $trader
     * @param Player $recipient
     * @param int $price
     * @return void
     */
    public function addTradeQueue(Player $trader, Player $recipient, int $price = 0): void
    {
        // Each player will only be allowed to have 1 trade queue.
        if (!self::$tradesEnabled) {
            $trader->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Trading is temporarily disabled until the server restarted.");
        } elseif ($trader->getName() === $recipient->getName()) {
            $trader->sendMessage(MMOPlugin::getPrefix() . "§7You cannot start a trade with yourself.");
        } else {
            $this->pendingTrades[] = [$trader, $recipient, $price, time()];

            if ($price <= 0) {
                $recipient->sendMessage(MMOPlugin::getPrefix() . "§7The player §b" . $trader->getName() . "§7 is requesting to start a trade with you.");
            } else {
                $recipient->sendMessage(MMOPlugin::getPrefix() . "§7The player §b" . $trader->getName() . "§7 is requesting to start a trade with you for " . TextFormat::YELLOW . "$" . number_format($price) . ".");
            }

            $recipient->sendMessage(MMOPlugin::getPrefix() . "§7Use the trade menu command (§6/trade§7) to view all pending trade requests.");
            $trader->sendMessage(MMOPlugin::getPrefix() . "§7Trade request sent to the player §b" . $recipient->getName());
        }
    }

    public function getTradeInvites(Player $player): ?array
    {
        if (!self::$tradesEnabled) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Trading is temporarily disabled until the server restarted.");
            return null;
        }

        // Any pending trades less than 60 seconds will expire.
        return array_filter($this->pendingTrades, function ($data) use ($player): bool {
            return $data[1]->getId() === $player->getId() && (time() - $data[3]) < self::TRADE_TIMEOUT_SECONDS;
        });
    }

    /**
     * Accept a trade from a trader, the recipient will have to accept the request from
     * this trader to start a trade properly.
     *
     * @param Player $recipient
     * @param Player|null $target
     * @return bool
     */
    public function acceptTradeRequest(Player $recipient, ?Player $target = null): bool
    {
        if (!self::$tradesEnabled) {
            $recipient->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Trading is temporarily disabled until the server restarted.");
            return true;
        }

        foreach (array_filter($this->pendingTrades, function ($data) use ($recipient, $target): bool {
            return $data[1]->getId() === $recipient->getId() && ($target === null || $data[0]->getId() === $target->getId());
        }) as $queueId => $trade) {
            Await::f2c(function () use ($trade, $queueId): Generator {
                /**
                 * @var Player $recipient
                 * @var Player $trader
                 * @var int $tradePrice
                 */
                [$trader, $recipient, $tradePrice] = $trade;

                $this->plugin->getPlayerData()->loadValue($recipient->getName(), PlayerData::PLAYER_TRADE_CACHE, yield);
                $data = yield Await::ONCE;

                if (!empty($data)) {
                    $recipient->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You have to clear the contents in your trade storage first! Use " . TextFormat::YELLOW . "/trade storage" . TextFormat::RED . ' to reclaim your items back');
                } else {
                    $form = FormManager::createModalForm($recipient);
                    if ($form === null) {
                        return;
                    }

                    $form->setTitle(MMOPlugin::getPrefix() . TextFormat::BLACK . 'Trade confirmation');
                    $form->setContent(TextFormat::RED . 'Are you sure to accept a trade from ' . $recipient->getName() . ' for ' . TextFormat::YELLOW . '$' . number_format(($tradePrice)) . TextFormat::RED . '? Please confirm this info again to prevent scamming.');
                    $form->setButton1(new Button('Accept', function () use ($trader, $recipient, $tradePrice, $queueId): void {
                        if (!isset($this->pendingTrades[$queueId])) {
                            $recipient->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'The trade requested by ' . TextFormat::GRAY . $trader->getName() . TextFormat::RED . ' has expired, you may ask the player to trade with you again.');
                            return;
                        }

                        if ($trader->isOnline() && $this->plugin->getPlayerManager()->canDoTransactions($trader) && $this->plugin->getPlayerManager()->canDoTransactions($recipient)) {
                            $trader->sendMessage(MMOPlugin::getPrefix() . "§b" . $recipient->getName() . " accepted your trade request.");
                            $recipient->sendMessage(MMOPlugin::getPrefix() . "§7You accepted §b" . $trader->getName() . "'s §7trade request.");

                            unset(EventListener::$sellObjects[$recipient->getName()], EventListener::$sellObjects[$trader->getName()]); // Clear the sell object

                            $tradeId = self::$tradeId++;
                            $this->activeTrades[$tradeId] = new TradeHandler($tradeId, $trader, $recipient, $tradePrice);
                        } else {
                            $recipient->sendMessage(MMOPlugin::getPrefix() . "§cThe player requested to start the trade with you is offline.");
                        }

                        unset($this->pendingTrades[$queueId]);
                    }));
                    $form->setButton2(new Button('Reject', function () use ($trader, $queueId) {
                        if (isset($this->pendingTrades[$queueId])) {
                            $this->rejectTradeRequest($trader);
                        }
                    }));
                    $form->sendForm();
                }
            });

            return true;
        }

        return false;
    }

    /**
     * Reject a trade requested by a trader, this option can only be executed
     * if the recipient has pending trades.
     *
     * @param Player $recipient
     * @return bool
     */
    public function rejectTradeRequest(Player $recipient): bool
    {
        foreach (array_filter($this->pendingTrades, function ($data) use ($recipient): bool {
            return $data[1]->getId() === $recipient->getId();
        }) as $queueId => $trade) {
            /**
             * @var Player $trader
             * @var Player $recipient
             */
            [$trader, $recipient] = $trade;

            $trader->sendMessage(MMOPlugin::getPrefix() . "§c" . $recipient->getName() . " has rejected your trade request.");
            $recipient->sendMessage(MMOPlugin::getPrefix() . "§cYou rejected §b" . $trader->getName() . "'s §7trade request.");

            unset($this->pendingTrades[$queueId]);

            return true;
        }

        return false;
    }

    /**
     * @param Player $player
     * @return bool
     */
    public function isTradeInProgress(Player $player): bool
    {
        foreach ($this->activeTrades as $trade) {
            if ($trade->recipientPlayer->getId() === $player->getId() || $trade->tradePlayer->getId() === $player->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<Promise> Resolved to a boolean indicating that all islands has been unloaded.
     * @phpstan-return array<Promise<null>>
     */
    public function getAllPendingTrades(): array
    {
        $trades = [];
        foreach ($this->activeTrades as $trade) {
            $trades[] = $trade->getFuture();
        }

        return $trades;
    }

    public function closeAllTrades(bool $gracefully): void
    {
        foreach ($this->activeTrades as $trade) {
            $trade->closeCurrentTrade($gracefully);
        }
    }

    /**
     * @param int $tradeId
     */
    public function removeTrade(int $tradeId): void
    {
        unset($this->activeTrades[$tradeId]);
    }

    public function onRun(): void
    {
        foreach ($this->pendingTrades as $queueId => $trade) {
            /**
             * @var Player $player
             * @var Player $target
             * @var int $timeout
             */
            [$player, $target, , $timeout] = $trade;

            if ((time() - $timeout) >= self::TRADE_TIMEOUT_SECONDS) {
                if ($player->isConnected()) {
                    $player->sendMessage(MMOPlugin::getPrefix() . "§b" . $target->getName() . "§c didn't accept your trade on time!");
                }
                if ($target->isConnected()) {
                    $target->sendMessage("§cYour trade from §b" . $player->getName() . "'s§c has expired!");
                }

                unset($this->pendingTrades[$queueId]);
            }
        }
    }
}