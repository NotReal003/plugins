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

namespace skyblock;

use libasyncio\s3\S3StorageCredentials;
use libasyncio\s3\S3StorageManager;
use libMMO\challenges\ChallengeSet;
use libMMO\challenges\placeholder\LockData;
use libMMO\challenges\PlayerChallengeManager;
use libMMO\entities\PlayerHead;
use libMMO\entities\stackable\StackingEngine;
use libMMO\forms\EnchantListForm;
use libMMO\item\CustomItemRegistry;
use libMMO\MMOPlugin;
use libMMO\player\enchantment\EnchantmentManager;
use libMMO\player\MMOPlayer;
use libMMO\utils\logger\LogListener;
use libMMO\utils\rollback\RollbackEngine;
use libVanilla\VanillaPlugin;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\type\util\InvMenuTypeBuilders;
use NetherGames\NGEssentials\entity\custom\EntityNPC;
use NetherGames\NGEssentials\entity\custom\FloatingText;
use NetherGames\NGEssentials\entity\custom\HumanNPC;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\ServerManager;
use NetherGames\NGEssentials\utils\SkinUtils;
use NetherGames\NGEssentials\utils\Utils;
use pocketmine\block\Block;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\tile\TileFactory;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\data\bedrock\block\convert\BlockObjectToStateSerializer;
use pocketmine\data\bedrock\block\convert\BlockStateToObjectDeserializer;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\item\StringToItemParser;
use pocketmine\network\mcpe\protocol\types\InputMode;
use pocketmine\player\Player;
use pocketmine\scheduler\AsyncTask;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use ReflectionClass;
use skyblock\block\SpawnerBlock;
use skyblock\block\SpawnerTile;
use skyblock\challenges\SkyblockChallengeManager;
use skyblock\challenges\SkyblockChallengeSet;
use skyblock\commands\BaseCommand;
use skyblock\crates\CrateManager;
use skyblock\economy\auctionhouse\AuctionHouse;
use skyblock\economy\EconomyManager;
use skyblock\economy\shop\Shop;
use skyblock\entities\EntityManager;
use skyblock\forms\IslandForm;
use skyblock\islands\IslandManager;
use skyblock\item\ItemManager;
use skyblock\kit\KitManager;
use skyblock\player\enchantment\EnchantListener;
use skyblock\player\PlayerData;
use skyblock\player\PlayerManager;
use skyblock\utils\Autoloader;
use skyblock\utils\Database;
use skyblock\utils\EventEmitter;
use skyblock\utils\InvestigationManager;
use const DIRECTORY_SEPARATOR;

Autoloader::initAutoloader();

class SkyBlock extends MMOPlugin
{
    /** @var S3StorageManager */
    private static S3StorageManager $storageManager;
    /** @var S3StorageCredentials */
    private static S3StorageCredentials $credentials;
    /** @var IslandManager */
    private IslandManager $islandManager;

    public static function getStorageManager(): S3StorageManager
    {
        return self::$storageManager;
    }

    public static function getCredentials(): S3StorageCredentials
    {
        return self::$credentials;
    }

    /**
     * @return self
     */
    public static function getInstance(): MMOPlugin
    {
        /** @phpstan-ignore-next-line */
        return parent::getInstance();
    }

    public function onLoad(): void
    {
        self::$serverPrefix = TextFormat::AQUA . '(' . TextFormat::YELLOW . '+' . TextFormat::AQUA . ') ' . TextFormat::RESET . TextFormat::GOLD;

        parent::onLoad();

        /** @var NGEssentials|null $ess */
        $ess = $this->getServer()->getPluginManager()->getPlugin('NGEssentials');
        if ($ess === null) {
            return;
        }

        $this->database = new Database($this, new Config($ess->getDataFolder() . 'credentials' . DIRECTORY_SEPARATOR . 'credentials.yml', Config::YAML));

        // setup AWS S3 support
        if (NGEssentials::isInDevelopmentMode()) {
            $config = $this->getConfig();

            $accessKey = $config->getNested("s3.access-key");
            $secretKey = $config->getNested("s3.secret-key");
            $region = $config->getNested("s3.region");
            $endpoint = $config->getNested("s3.endpoint");
            $bucket = $config->getNested("s3.bucket");
        } else {
            $accessKey = getenv("S3_ACCESS_KEY");
            $secretKey = getenv("S3_SECRET_KEY");
            $region = getenv("S3_REGION");
            $endpoint = getenv("S3_ENDPOINT");
            $bucket = getenv("S3_BUCKET");
        }

        self::$credentials = new S3StorageCredentials($bucket, $accessKey, $secretKey, $region, $endpoint);
        self::$storageManager = new S3StorageManager($this, self::getCredentials(), 6);

        StackingEngine::$maxStackingEntities = 50;

        EnchantmentManager::addEnchantExclusion(
            EnchantmentManager::VELOCITY(),
            EnchantmentManager::GRAPPLE(),
            EnchantmentManager::ENTANGLEMENT(),
            EnchantmentManager::DETONATION(),
            EnchantmentManager::THOR(),
            EnchantmentManager::DEBILITATE(),
            EnchantmentManager::TANK(),
            EnchantmentManager::DETECT(),
            EnchantmentManager::SPRING(),
            EnchantmentManager::COMBO(),
            EnchantmentManager::FROSTY_ARROWS()
        );

        unset(
            EnchantListForm::$enchants[EnchantmentManager::VELOCITY],
            EnchantListForm::$enchants[EnchantmentManager::GRAPPLE],
            EnchantListForm::$enchants[EnchantmentManager::ENTANGLEMENT],
            EnchantListForm::$enchants[EnchantmentManager::DETONATION],
            EnchantListForm::$enchants[EnchantmentManager::THOR],
            EnchantListForm::$enchants[EnchantmentManager::DEBILITATE],
            EnchantListForm::$enchants[EnchantmentManager::TANK],
            EnchantListForm::$enchants[EnchantmentManager::DETECT],
            EnchantListForm::$enchants[EnchantmentManager::SPRING],
            EnchantListForm::$enchants[EnchantmentManager::COMBO],
            EnchantListForm::$enchants[EnchantmentManager::FROSTY_ARROWS]
        );

        SkyBlock::registerCustomBlocks();

        $this->getServer()->getAsyncPool()->addWorkerStartHook(function (int $worker): void {
            $this->getServer()->getAsyncPool()->submitTaskToWorker(new class extends AsyncTask {
                public function onRun(): void
                {
                    SkyBlock::registerCustomBlocks();
                    CustomItemRegistry::forceInitialization();
                }
            }, $worker);
        });
    }

    public function onEnable(): void
    {
        parent::onEnable();

        /** @var NGEssentials|null $ess */
        $ess = $this->getServer()->getPluginManager()->getPlugin('NGEssentials');
        if ($ess === null) {
            return;
        }

        $this->getServer()->getWorldManager()->setAutoSave(false);

        VanillaPlugin::CHEST_MINECART()->register($this);
        VanillaPlugin::ENDER_EYE()->register($this);
        VanillaPlugin::NAME_TAG()->register($this);
        VanillaPlugin::ENCHANTS()->register($this);
        VanillaPlugin::ENTITIES()->register($this);
        VanillaPlugin::FISHING_ROD()->register($this);
        VanillaPlugin::HOPPER()->register($this);

        $this->playerData = new PlayerData($this);
        $this->eventEmitter = new EventEmitter($this);
        $this->investigationManager = new InvestigationManager($this);
        $this->entityManager = new EntityManager($this);
        $this->enchantmentManager = new EnchantmentManager($this, false);
        $this->auctionHouse = new AuctionHouse($this);
        $this->itemManager = new ItemManager($this);
        $this->kitManager = new KitManager($this);
        $this->playerManager = new PlayerManager($this);
        $this->economyManager = new EconomyManager($this);
        $this->crateManager = new CrateManager($this);
        $this->challengeManager = new SkyblockChallengeManager($this);
        $this->rollbackEngine = new RollbackEngine($this);
        (new SkyblockChallengeSet())->setup($this->getChallengeManager());
        $this->playerChallengeManager = new PlayerChallengeManager($this);
        $this->shop = new Shop($this->economyManager);

        $this->islandManager = new IslandManager($this);

        if ($this->isAgora()) {
            $this->getServer()->getWorldManager()->loadWorld('pvp');

            $this->registerNPCs();
        } else {
            InvMenuHandler::getTypeRegistry()->register('skyblock:minihelper', InvMenuTypeBuilders::BLOCK_ACTOR_FIXED()
                ->setBlock(VanillaBlocks::CHEST())
                ->setSize(27)
                ->setBlockActorId("Chest")
                ->build());
        }

        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->getServer()->getPluginManager()->registerEvents(new LogListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new EnchantListener($this), $this);

        IslandForm::generateStaticForms($this);
        BaseCommand::registerCommands($this);

        PlayerHead::onInteractCallback(function (Player $damager, string $victimName, int $rewardMoney, int $bountyMoney): void {
            if ($rewardMoney > 0) {
                $graveData = new LockData();
                $graveData->setPlayerName($damager->getName());
                $graveData->setDataField($victimName);

                foreach ($this->getPlayerChallengeManager()->getActiveChallenges($damager) as $challenge) {
                    $challenge->increaseProgress($damager, SkyblockChallengeSet::GRAVE_COLLECT, $graveData);
                }

                $this->getEconomyManager()->increasePlayerMoney($damager->getName(), $rewardMoney, function () use ($damager, $rewardMoney): void {
                    if ($damager->isConnected()) {
                        $damager->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . 'You got ' . TextFormat::GOLD . '$' . number_format($rewardMoney) . TextFormat::GREEN . ' by opening this grave!');
                    }
                });
            }

            if ($bountyMoney > 0 && $damager->getName() !== $victimName) {
                $lock = new LockData();
                $lock->setPlayerName($damager->getName());
                $lock->setDataField($victimName);

                foreach ($this->getPlayerChallengeManager()->getActiveChallenges($damager) as $challenge) {
                    $challenge->increaseProgress($damager, ChallengeSet::COLLECT_BOUNTY, $lock);
                }

                $this->getEconomyManager()->increasePlayerMoney($damager->getName(), $currentBounty = $bountyMoney, function () use ($damager, $currentBounty, $victimName): void {
                    if ($damager->isConnected()) {
                        $damager->sendMessage(MMOPlugin::getPrefix() . TextFormat::GOLD . 'You collected ' . TextFormat::AQUA . $victimName . "'s" . TextFormat::GOLD . 'bounty of ' . TextFormat::GREEN . '$' . number_format($currentBounty) . TextFormat::GOLD . ' by killing them!');
                    }
                });
            }
        });

        Database::getMySQLDatabase()->waitAll();
    }

    public function onDisable(): void
    {
        // onEnable can abort part-way through (no NGEssentials, failed bootstrap), leaving typed
        // properties uninitialised. Without this check the shutdown path throws on its own and buries
        // the original failure behind a "crashed while crashing" report.
        if (!$this->isAgora() && isset($this->islandManager)) {
            foreach ($this->getIslandManager()->getIslandLevelManager()->getAll() as $levelup) {
                $levelup->handleDone();
            }

            $this->getIslandManager()->saveAllIslands();
        }

        parent::onDisable();
    }

    public function registerNPCs(): void
    {
        $npcs = [];
        $defaultWorld = $this->getServer()->getWorldManager()->getDefaultWorld();

        $npcs[] = new EntityNPC(new Location(36, 95, 17, $defaultWorld, 125, 0), TextFormat::BOLD . TextFormat::YELLOW . 'Join PvP arena' . TextFormat::EOL . TextFormat::RESET . TextFormat::GREEN . 'Tap me!', 'ng:skyblock_npc_pvp', static function (Player $player) {
            $player->getServer()->dispatchCommand($player, 'pvp');
        });
        $npcs[] = new EntityNPC(new Location(35, 96, 21, $defaultWorld, 130, 0), TextFormat::BOLD . TextFormat::YELLOW . 'Associated Islands' . TextFormat::EOL . TextFormat::RESET . TextFormat::GREEN . 'Tap me!', 'ng:skyblock_npc_associated_islands', function (Player $player) {
            IslandForm::sendAssociatedIslands($player, $this);
        });
        $npcs[] = new EntityNPC(new Location(32, 96, 24, $defaultWorld, 140, 0), TextFormat::BOLD . TextFormat::YELLOW . 'Go to your island' . TextFormat::EOL . TextFormat::RESET . TextFormat::GREEN . 'Tap me!', 'ng:skyblock_npc_your_island', function (Player $player) {
            $hasIsland = $this->getPlayerData()->getBool($player, PlayerData::HAS_ISLAND);

            if ($hasIsland) {
                $this->getIslandManager()->getIslandLocation($player->getName(), function (int $status, ?string $serverUniqueId) use ($player) {
                    if ($player->isConnected() && $status !== IslandManager::STATUS_NOT_CREATED) {
                        IslandForm::sendIslandTransfer($player, $serverUniqueId, '', $this);
                    }
                });
            } else {
                $player->sendMessage(TextFormat::RED . "You don't have any islands!");
            }
        });
        $npcs[] = new EntityNPC(new Location(28, 95, 25, $defaultWorld, 145, 0), TextFormat::BOLD . TextFormat::YELLOW . 'Public Islands' . TextFormat::EOL . TextFormat::RESET . TextFormat::GREEN . 'Tap me!', 'ng:skyblock_npc_public_islands', function (Player $player) {
            IslandForm::sendPublicIslandsForm($player, $this);
        });

        // kits skin
        $skin = new Skin('Standard_Custom', SkinUtils::getTextureFromString(Utils::getResourceContent('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'creative.png')));
        $npcs[] = new HumanNPC(new Location(2858, 107, 3002, $defaultWorld, 135, 0), TextFormat::BOLD . TextFormat::YELLOW . 'Kits' . TextFormat::EOL . TextFormat::RESET . TextFormat::GREEN . 'Tap me!', $skin, static function (Player $player) {
            $player->getServer()->dispatchCommand($player, 'kit');
        });
        $npcs[] = new EntityNPC(new Location(9.5, 93, 29.5, $defaultWorld, 270, 0), TextFormat::BOLD . TextFormat::YELLOW . 'Challenges' . TextFormat::EOL . TextFormat::RESET . TextFormat::GREEN . 'Tap me!', 'ng:skyblock_npc_challenges', static function (Player $player) {
            $player->getServer()->dispatchCommand($player, 'ch');
        });
        $npcs[] = new FloatingText(new Location(4.5, 102, -6.5, $defaultWorld, 0, 0), '§fWelcome to §eNether§6Games §fSkyblock!', '§fIf you find any bugs during your stay,' . TextFormat::EOL . 'please report them to §b#bugs §fon our Discord server.' . TextFormat::EOL . 'Use §b/is §fto create an island now!' . TextFormat::EOL . TextFormat::EOL . '§l§6Quick Links:' . TextFormat::EOL . '§r§6Vote: §rngmc.co/v' . TextFormat::EOL . '§6Store: §rngmc.co/store' . TextFormat::EOL . '§6Discord: §rngmc.co/d' . TextFormat::EOL . '§6Twitter: §r@NetherGamesMC');
        $npcs[] = new FloatingText(new Location(-12, 96, -24, $defaultWorld, 0, 0), TextFormat::BOLD . TextFormat::YELLOW . 'SKYBLOCK MARKETS', 'Follow this path for the shops & auction house ' . TextFormat::EOL . 'or use ' . TextFormat::GOLD . '/shop' . TextFormat::RESET . ' or ' . TextFormat::GOLD . '/ah' . TextFormat::RESET . ' for quick access.');

        //$minX = 240;
        //$minZ = 1274;
        //$maxX = 282;
        //$maxZ = 1428;
        //$skin = new Skin('Standard_Custom', SkinUtils::getTextureFromString(Utils::getResourceContent('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'bw.png'));
        //$x = mt_rand($minX, $maxX);
        //$z = mt_rand($minZ, $maxZ);
        //
        //$npcs[] = $dailyNPC = new HumanNPC(new Location($x, $defaultWorld->getHighestBlockAt($x, $z) + 1, $z, $defaultWorld, 0, 0), TextFormat::BOLD . TextFormat::YELLOW . 'Daily Challenges' . TextFormat::EOL . TextFormat::RESET . TextFormat::GREEN . 'Tap me!', $skin, static function (Player $player) {
        //    $player->getServer()->dispatchCommand($player, 'dch');
        //});
        //
        //$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () use ($minX, $minZ, $maxZ, $maxX, $dailyNPC, $defaultWorld): void {
        //    $x = mt_rand($minX, $maxX);
        //    $z = mt_rand($minZ, $maxZ);
        //    $loc = $dailyNPC->getLocation();
        //    $loc->x = $x + 0.5;
        //    $loc->y = $defaultWorld->getHighestBlockAt($x, $z) + 1;
        //    $loc->z = $z + 0.5;
        //    $pks = $dailyNPC->getPackets($dailyNPC::UPDATE_MOVEMENT);
        //    foreach ($loc->getWorld()->getPlayers() as $player) {
        //        foreach ($pks as $pk) {
        //            $player->getNetworkSession()->sendDataPacket($pk);
        //        }
        //    }
        //}), 20 * 60 * 20);

        //$currentHour = (int)date('H');
        //$minutes = $currentHour % 6 === 0 ? 60 * 6 : 60 - (int)date('i') + 60 * (ceil($currentHour / 6) * 6 - $currentHour) - 60;
        //$spawnBoss = function (): void {
        //    $worldManager = $this->getServer()->getWorldManager();
        //    $world = $worldManager->getWorldByName('sb-arena');
        //
        //    if ($world === null && !$worldManager->isWorldLoaded('sb-arena')) {
        //        $worldManager->loadWorld('sb-arena');
        //        $world = $worldManager->getWorldByName('sb-arena');
        //    }
        //
        //    if ($world !== null) {
        //        BossManager::spawnBoss(new Location(128, 4, 128, $world, 0, 0), BossManager::THANOS);
        //    }
        //};
        //$this->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask($spawnBoss), (int)$minutes * 20, 60 * 6 * 20);

        /* MARKET */
        $shopKeepers = [
            'Decoration' => new Location(-2.5, 116, -57.5, $defaultWorld, 90, 0),
            'Redstone' => new Location(-20.5, 115, -66.5, $defaultWorld, 270, 0),
            'Food' => new Location(-20.5, 115, -64.5, $defaultWorld, 270, 0),
            'Farming' => new Location(-10.5, 118, -80.5, $defaultWorld, 0, 0),
            'Utilities' => new Location(-12.5, 118, -80.5, $defaultWorld, 0, 0),
            'Minerals & Mob Drops' => new Location(15.5, 119, -73.5, $defaultWorld, 90, 0),
            'Blocks' => new Location(15.5, 119, -75.5, $defaultWorld, 90, 0),
        ];
        // shopkeeper skin
        $skin = new Skin('Standard_Custom', SkinUtils::getTextureFromString(Utils::getResourceContent('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'sw.png')));
        foreach ($shopKeepers as $category => $location) {
            $npcs[] = new HumanNPC($location, TextFormat::BOLD . TextFormat::AQUA . $category . TextFormat::EOL . TextFormat::RESET . TextFormat::YELLOW . 'Click to view!', $skin, function (Player $player) use ($category) {
                /** @var MMOPlayer $player */
                $this->getShop()->getCategory($category)->send($player, $player->getInputMode() === InputMode::TOUCHSCREEN);
            });
        }

        /* AH */
        // auction house skin
        $skin = new Skin('Standard_Custom', SkinUtils::getTextureFromString(Utils::getResourceContent('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'creative.png')));
        $npcs[] = new HumanNPC(new Location(-2.5, 116, -59.5, $defaultWorld, 90, 0), TextFormat::BOLD . TextFormat::YELLOW . 'Auction House' . TextFormat::EOL . TextFormat::RESET . TextFormat::GREEN . 'Tap me!', $skin, static function (Player $player) {
            $player->getServer()->dispatchCommand($player, 'ah');
        });

        /* BLACKSMITH */
        // blacksmith skin
        $skin = new Skin('Standard_Custom', SkinUtils::getTextureFromString(Utils::getResourceContent('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'mm.png')));
        $npcs[] = new HumanNPC(new Location(262.5, 81, 1377.5, $defaultWorld, 270, 0), TextFormat::BOLD . TextFormat::LIGHT_PURPLE . 'Blacksmith' . TextFormat::EOL . TextFormat::RESET . TextFormat::GREEN . 'Tap me!', $skin, static function (Player $player) {
            $player->getServer()->dispatchCommand($player, 'repair');
        });

        /** @var NGEssentials $ess */
        $ess = $this->getEssentials();
        /* PVP ARENA */
        $pvpLevel = $this->getServer()->getWorldManager()->getWorldByName('pvp');
        // return lobby skin
        $skin = new Skin('Standard_Custom', SkinUtils::getTextureFromString(Utils::getResourceContent('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'bw.png')));
        $npcs[] = new HumanNPC(new Location(1.5, 113, 3.5, $pvpLevel, 120, 0), TextFormat::BOLD . TextFormat::YELLOW . 'Return to Lobby' . TextFormat::EOL . TextFormat::RESET . TextFormat::GREEN . 'Tap me!', $skin, function (Player $player) use ($ess) {
            $player->teleport($ess->getServerManager()->getSpawn());
        });

        $entityManager = $ess->getEntityManager();
        foreach ($npcs as $npc) {
            $entityManager->addEntity($npc);
        }
    }

    public static function registerCustomBlocks(): void
    {
        //this is intentionally not the vanilla save ID
        self::registerSimpleBlock(BlockTypeNames::MOB_SPAWNER, new SpawnerBlock(), ["mob_spawner", "monster_spawner"]);

        TileFactory::getInstance()->register(SpawnerTile::class, ['nethergames:spawner']);
    }

    /**
     * @param string[] $stringToItemParserNames
     */
    private static function registerSimpleBlock(string $id, Block $block, array $stringToItemParserNames): void
    {
        $registry = RuntimeBlockStateRegistry::getInstance();

        $runtimeBlockRegistry = new ReflectionClass(RuntimeBlockStateRegistry::class);
        $runtimeBlockRefProp = $runtimeBlockRegistry->getProperty("typeIndex");

        $deserializerMap = $runtimeBlockRefProp->getValue($registry);
        unset($deserializerMap[$block->getTypeId()]);
        $runtimeBlockRefProp->setValue($registry, $deserializerMap);

        $registry->register($block);

        // this entire thing is a hack (orig: libVanilla)
        $deserializer = GlobalBlockStateHandlers::getDeserializer();
        $serializer = GlobalBlockStateHandlers::getSerializer();

        $deserializerRefClass = new ReflectionClass(BlockStateToObjectDeserializer::class);
        $deserializerRefProp = $deserializerRefClass->getProperty("deserializeFuncs");

        $serializerRefClass = new ReflectionClass(BlockObjectToStateSerializer::class);
        $serializerRefProp = $serializerRefClass->getProperty("serializers");

        $deserializerMap = $deserializerRefProp->getValue($deserializer);
        unset($deserializerMap[$id]);
        $deserializerRefProp->setValue($deserializer, $deserializerMap);

        $itemSerializerMap = $serializerRefProp->getValue($serializer);
        unset($itemSerializerMap[$block->getTypeId()]);
        $serializerRefProp->setValue($serializer, $itemSerializerMap);
        // ===========================

        GlobalBlockStateHandlers::getDeserializer()->mapSimple($id, fn() => clone $block);
        GlobalBlockStateHandlers::getSerializer()->mapSimple($block, $id);

        foreach ($stringToItemParserNames as $name) {
            StringToItemParser::getInstance()->override($name, fn() => $block->asItem());
        }
    }

    public function getIslandManager(): IslandManager
    {
        return $this->islandManager;
    }

    /**
     * @return PlayerData
     */
    public function getPlayerData(): \libMMO\player\PlayerData
    {
        /** @phpstan-ignore-next-line */
        return parent::getPlayerData();
    }

    /**
     * @return EconomyManager
     */
    public function getEconomyManager(): \libMMO\economy\EconomyManager
    {
        /** @phpstan-ignore-next-line */
        return parent::getEconomyManager();
    }

    /**
     * @return EntityManager
     */
    public function getEntityManager(): \libMMO\entities\EntityManager
    {
        /** @phpstan-ignore-next-line */
        return parent::getEntityManager();
    }

    /**
     * @return PlayerManager
     */
    public function getPlayerManager(): \libMMO\player\PlayerManager
    {
        /** @phpstan-ignore-next-line */
        return parent::getPlayerManager();
    }

    public function getLoggerChannels(): array
    {
        // Discord relay for moderation logging is disabled by default. Populate this with your own
        // webhook paths ("<id>/<token>") to enable it; an empty list makes LimitAvoidableDiscordChannel
        // drop messages instead of posting.
        return [];
    }

    public function isAgora(): bool
    {
        return $this->getEssentials()->getServerManager()->getGameType() === ServerManager::GAME_TYPE_AGORA;
    }
}
