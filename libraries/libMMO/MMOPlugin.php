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

use ArrayIterator;
use libDiscord\LimitAvoidableDiscordChannel;
use libforms\FormManager;
use libMMO\aggressiveoptz\AggressiveOptzLoader;
use libMMO\challenges\ChallengeManager;
use libMMO\challenges\PlayerChallengeManager;
use libMMO\crates\CrateManager;
use libMMO\economy\auctionHouse\AuctionHouse;
use libMMO\economy\EconomyManager;
use libMMO\economy\shop\Shop;
use libMMO\entities\EntityManager;
use libMMO\event\TradeCancelEvent;
use libMMO\forms\TpaForm;
use libMMO\item\CustomItemRegistry;
use libMMO\item\ItemManager;
use libMMO\kit\KitManager;
use libMMO\player\enchantment\EnchantmentManager;
use libMMO\player\MMOPlayer;
use libMMO\player\PlayerData;
use libMMO\player\PlayerManager;
use libMMO\player\trading\TradingManager;
use libMMO\utils\chunks\ChunkLimits;
use libMMO\utils\Database;
use libMMO\utils\EventEmitter;
use libMMO\utils\InvestigationManager;
use libMMO\utils\rollback\RollbackEngine;
use libMMO\utils\trade\TradeManager;
use muqsit\asynciterator\AsyncIterator;
use muqsit\asynciterator\handler\AsyncForeachResult;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\type\util\InvMenuTypeBuilders;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\utils\discord\DiscordMessageBuffer;
use pocketmine\block\VanillaBlocks;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use SOFe\AwaitGenerator\Await;

abstract class MMOPlugin extends PluginBase
{
    public const MENU_CHEST_SINGLE = 'libmmo:single';
    public const MENU_CHEST_DOUBLE = 'libmmo:double';
    public const MENU_HOPPER = 'libmmo:hopper';
    public const MENU_CHEST_PLAYERHEAD = 'libmmo:playerhead';

    protected static string $serverPrefix = '';

    /** @var self */
    protected static MMOPlugin $instance;

    /** @var AuctionHouse */
    protected AuctionHouse $auctionHouse;
    /** @var ChunkLimits */
    protected ChunkLimits $chunkLimits;
    /** @var CrateManager */
    protected CrateManager $crateManager;
    /** @var EntityManager */
    protected EntityManager $entityManager;
    /** @var ItemManager */
    protected ItemManager $itemManager;
    /** @var PlayerData */
    protected PlayerData $playerData;
    /** @var Database */
    protected Database $database;
    /** @var PlayerManager */
    protected PlayerManager $playerManager;
    /** @var EconomyManager */
    protected EconomyManager $economyManager;
    /** @var EnchantmentManager */
    protected EnchantmentManager $enchantmentManager;
    /** @var KitManager */
    protected KitManager $kitManager;
    /** @var ServerData */
    protected ServerData $serverData;
    /** @var ChallengeManager */
    protected ChallengeManager $challengeManager;
    /** @var PlayerChallengeManager */
    protected PlayerChallengeManager $playerChallengeManager;
    /** @var InvestigationManager */
    protected InvestigationManager $investigationManager;
    /** @var RollbackEngine */
    protected RollbackEngine $rollbackEngine;
    /** @var DiscordMessageBuffer */
    private DiscordMessageBuffer $discordBuffer;
    /** @var TradingManager */
    private TradingManager $tradeManager;
    /** @var Shop */
    protected Shop $shop;
    /** @var EventEmitter */
    protected EventEmitter $eventEmitter;
    /** @var NGEssentials|null */
    private ?NGEssentials $ess;

    public static function getInstance(): MMOPlugin
    {
        return self::$instance;
    }

    public static function getPrefix(): string
    {
        return self::$serverPrefix;
    }

    /**
     * @return AuctionHouse
     */
    public function getAuctionHouse(): AuctionHouse
    {
        return $this->auctionHouse;
    }

    /**
     * @return CrateManager
     */
    public function getCrateManager(): CrateManager
    {
        return $this->crateManager;
    }

    /**
     * @return EntityManager
     */
    public function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    /**
     * @return ItemManager
     */
    public function getItemManager(): ItemManager
    {
        return $this->itemManager;
    }

    /**
     * @return ServerData
     */
    public function getServerData(): ServerData
    {
        return $this->serverData;
    }

    /**
     * @return Database
     */
    public function getDatabase(): Database
    {
        return $this->database;
    }

    /**
     * @return KitManager
     */
    public function getKitManager(): KitManager
    {
        return $this->kitManager;
    }

    /**
     * @return PlayerManager
     */
    public function getPlayerManager(): PlayerManager
    {
        return $this->playerManager;
    }

    /**
     * @return EconomyManager
     */
    public function getEconomyManager(): EconomyManager
    {
        return $this->economyManager;
    }

    /**
     * @return TradingManager
     */
    public function getTradeManager(): TradingManager
    {
        return $this->tradeManager;
    }

    /**
     * @return EnchantmentManager
     */
    public function getEnchantmentManager(): EnchantmentManager
    {
        return $this->enchantmentManager;
    }

    /**
     * @return Shop
     */
    public function getShop(): Shop
    {
        return $this->shop;
    }

    /**
     * @return PlayerChallengeManager
     */
    public function getPlayerChallengeManager(): PlayerChallengeManager
    {
        return $this->playerChallengeManager;
    }

    /**
     * @return RollbackEngine
     */
    public function getRollbackEngine(): RollbackEngine
    {
        return $this->rollbackEngine;
    }

    /**
     * @return ChallengeManager
     */
    public function getChallengeManager(): ChallengeManager
    {
        return $this->challengeManager;
    }

    public function getEssentials(): ?NGEssentials
    {
        return $this->ess;
    }

    /**
     * @return EventEmitter
     */
    public function getEventEmitter(): EventEmitter
    {
        return $this->eventEmitter;
    }

    /**
     * @return InvestigationManager
     */
    public function getInvestigationManager(): InvestigationManager
    {
        return $this->investigationManager;
    }

    public function getLoggerChannels(): array
    {
        return [''];
    }

    public function getLoggerStream(): DiscordMessageBuffer
    {
        return $this->discordBuffer;
    }

    /**
     * @return PlayerData
     */
    public function getPlayerData(): PlayerData
    {
        return $this->playerData;
    }

    protected function onEnable(): void
    {
        /** @var NGEssentials|null $ess */
        $ess = $this->getServer()->getPluginManager()->getPlugin('NGEssentials');
        if ($ess === null) {
            new FormManager($this);
        }
        $this->ess = $ess;

        Await::f2c(function () {
            $uniqueId = NGEssentials::getInstance()->getServerManager()->getUniqueId();

            Database::executeGeneric(Database::DELETE_SERVER_NODE, [
                'server' => $uniqueId
            ], yield, yield Await::REJECT);
            yield Await::ONCE;
            Database::executeGeneric(Database::CREATE_SERVER_NODE, [
                'server' => $uniqueId
            ], yield, yield Await::REJECT);
            yield Await::ONCE;
        }, catches: Database::getFailClosure(true));

        Database::getMySQLDatabase()->waitAll();

        if (!InvMenuHandler::isRegistered()) {
            InvMenuHandler::register($this);
        }

        InvMenuHandler::getTypeRegistry()->register(self::MENU_CHEST_SINGLE, InvMenuTypeBuilders::BLOCK_ACTOR_FIXED()
            ->setBlock(VanillaBlocks::CHEST())
            ->setSize(27)
            ->setBlockActorId("Chest")
            ->build());

        InvMenuHandler::getTypeRegistry()->register(self::MENU_CHEST_DOUBLE, InvMenuTypeBuilders::DOUBLE_PAIRABLE_BLOCK_ACTOR_FIXED()
            ->setBlock(VanillaBlocks::CHEST())
            ->setSize(54)
            ->setBlockActorId("Chest")
            ->setAnimationDuration(1)
            ->build());

        InvMenuHandler::getTypeRegistry()->register(self::MENU_HOPPER, InvMenuTypeBuilders::BLOCK_ACTOR_FIXED()
            ->setBlock(VanillaBlocks::HOPPER())
            ->setSize(5)
            ->setBlockActorId("Hopper")
            ->setNetworkWindowType(WindowTypes::HOPPER)
            ->build());

        InvMenuHandler::getTypeRegistry()->register(self::MENU_CHEST_PLAYERHEAD, InvMenuTypeBuilders::DOUBLE_PAIRABLE_BLOCK_ACTOR_FIXED()
            ->setBlock(VanillaBlocks::CHEST())
            ->setSize(54)
            ->setBlockActorId("Chest")
            ->setAnimationDuration(1)
            ->build());

        new TradeManager($this);

        $asyncIterator = new AsyncIterator($this->getScheduler());
        $this->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(function () use (&$asyncIterator): void {
            $autoSaveProfile = [];

            $asyncIterator
                ->forEach(new ArrayIterator(Server::getInstance()->getOnlinePlayers()), 1, 2)
                ->as(function (string $index, Player $player) use (&$autoSaveProfile): AsyncForeachResult {
                    /** @var MMOPlayer $player */

                    if ($player->isConnected()) {
                        $start = microtime(true);
                        $this->getPlayerData()->saveData($player);
                        $autoSaveProfile[] = microtime(true) - $start;
                    }

                    return AsyncForeachResult::CONTINUE();
                })->onCompletion(function () use (&$autoSaveProfile): void {
                    if (empty($autoSaveProfile)) {
                        return;
                    }

                    $execution = round((array_sum($autoSaveProfile) / count($autoSaveProfile)) * 1000, 2);
                    $maxOperation = round(max($autoSaveProfile) * 1000, 2);

                    $this->getLogger()->info("Successfully saved all player data, it took " . $execution . "ms/op to execute, " . $maxOperation . "ms largest spikes.");
                });
        }), 15 * 60 * 20, 15 * 60 * 20);

        TpaForm::addDefaultValidators($this);

        $this->tradeManager = new TradingManager($this);
        $this->discordBuffer = new DiscordMessageBuffer(
            new LimitAvoidableDiscordChannel($this->getLoggerChannels()), "Moderation",
            $ess->getServerManager()->getPlugin()->getServerManager()->getUniqueId()
        );

        (new AggressiveOptzLoader())->enable($this);
    }

    protected function onDisable(): void
    {
        if (!isset($this->database)) {
            // onEnable aborted before the database was ready, so there is nothing to flush and the
            // members below are still uninitialised. Returning here keeps the original startup error
            // visible instead of masking it with a second crash during shutdown.
            return;
        }

        $tradeEvent = new TradeCancelEvent();
        $tradeEvent->call();

        $tradeManager = TradeManager::getInstance();

        /** @var MMOPlayer $player */
        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            $player->setCombatTimer(0);

            $pendingTrades = $tradeManager->getAllPendingTrades();
            if (count($pendingTrades) > 0) {
                $tradeManager->closeAllTrades(false);
            }

            $this->getPlayerData()->saveData($player);
        }
        Database::getMySQLDatabase()->waitAll();

        $uniqueId = NGEssentials::getInstance()->getServerManager()->getUniqueId();
        Database::executeGeneric(Database::DELETE_SERVER_NODE, [
            'server' => $uniqueId
        ]);

        $this->database->close();
    }

    protected function onLoad(): void
    {
        self::$instance = $this;

        CustomItemRegistry::forceInitialization();
    }
}
