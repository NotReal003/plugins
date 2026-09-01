<?php
/**
 *        ______         _   _
 *       |  ____|       | | (_)
 *  __  _| |__ __ _  ___| |_ _  ___  _ __  ___
 *  \ \/ /  __/ _` |/ __| __| |/ _ \| '_ \/ __|
 *   >  <| | | (_| | (__| |_| | (_) | | | \__ \
 *  /_/\_\_|  \__,_|\___|\__|_|\___/|_| |_|___/
 *
 * Copyright (C) 2016-2021 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author larryTheCoder
 */

declare(strict_types=1);

namespace factions;

use Closure;
use factions\block\DragonEgg;
use factions\block\SpawnerBlock;
use factions\block\SpawnerTile;
use factions\block\TNTBlock;
use factions\chunks\FactionChunkLimits;
use factions\commands\BaseCommand;
use factions\crates\CrateManager;
use factions\economy\auctionhouse\AuctionHouse;
use factions\economy\EconomyManager;
use factions\economy\shop\Shop;
use factions\entities\EntityManager;
use factions\faction\claims\ClaimManager;
use factions\faction\FactionManager;
use factions\faction\object\Faction;
use factions\forms\Forms;
use factions\kit\KitManager;
use factions\koth\Koth;
use factions\koth\task\CountDownTask;
use factions\player\bounty\BountyHunter;
use factions\player\enchantments\EnchantListener;
use factions\player\PlayerData;
use factions\player\PlayerManager;
use factions\player\region\FactionRegionManager;
use factions\task\CombatBroadcastTask;
use factions\task\EggSpawnTask;
use factions\task\PurgeEntitiesTask;
use factions\task\ServerRestartTask;
use factions\task\WorkerObserveTask;
use factions\utils\Area;
use factions\utils\Autoloader;
use factions\utils\BlockDurability;
use factions\utils\chat\AllyChat;
use factions\utils\chat\FactionChat;
use factions\utils\Database;
use factions\utils\EventEmitter;
use factions\utils\InvestigationManager;
use Generator;
use InvalidArgumentException;
use libMMO\challenges\ChallengeManager;
use libMMO\challenges\PlayerChallengeManager;
use libMMO\crates\CrateRouletteTask;
use libMMO\entities\OptimizedItemEntity;
use libMMO\forms\TpaForm;
use libMMO\item\CooldownList;
use libMMO\item\CustomItemRegistry;
use libMMO\item\ItemManager;
use libMMO\MMOPlugin;
use libMMO\player\enchantment\EnchantmentManager;
use libMMO\utils\AdventureSettingsObject;
use libMMO\utils\async\AsyncWorldTicker;
use libMMO\utils\logger\LogListener;
use libMMO\utils\rollback\RollbackEngine;
use libVanilla\VanillaPlugin;
use NetherGames\NGEssentials\entity\custom\FloatingText;
use NetherGames\NGEssentials\entity\custom\HumanNPC;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\player\chat\ChatTypes;
use NetherGames\NGEssentials\player\NGPlayer;
use NetherGames\NGEssentials\ServerData;
use NetherGames\NGEssentials\ServerManager;
use NetherGames\NGEssentials\servers\Cluster;
use NetherGames\NGEssentials\utils\SkinUtils;
use pocketmine\block\Block;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\tile\TileFactory;
use pocketmine\data\bedrock\block\BlockStateNames as StateNames;
use pocketmine\data\bedrock\block\BlockTypeNames as Ids;
use pocketmine\data\bedrock\block\convert\BlockObjectToStateSerializer;
use pocketmine\data\bedrock\block\convert\BlockStateReader as Reader;
use pocketmine\data\bedrock\block\convert\BlockStateToObjectDeserializer;
use pocketmine\data\bedrock\block\convert\BlockStateWriter as Writer;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\data\bedrock\EnchantmentIds;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\InputMode;
use pocketmine\player\Player;
use pocketmine\scheduler\AsyncTask;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\WorldCreationOptions;
use ReflectionClass;
use RuntimeException;
use SOFe\AwaitGenerator\Await;
use Symfony\Component\Filesystem\Path;

Autoloader::initAutoloader();

class Factions extends MMOPlugin
{
    /** @var Location|null */
    private static ?Location $spawnLocation = null;

    /** @var FactionManager */
    private FactionManager $factionManager;
    /** @var ClaimManager */
    private ClaimManager $claimManager;
    /** @var BountyHunter */
    private BountyHunter $bountyHunter;
    /** @var Koth|null */
    private ?Koth $koth = null;
    /** @var FactionRegionManager */
    private FactionRegionManager $regionManager;

    /**
     * @return Factions
     */
    public static function getInstance(): MMOPlugin
    {
        return parent::getInstance();
    }

    public static function isBadlands(): bool
    {
        /** @var NGEssentials|null $ess */
        $ess = Server::getInstance()->getPluginManager()->getPlugin('NGEssentials');
        if ($ess === null) {
            return false;
        }

        $gameType = $ess->getServerManager()->getGameType();

        return $gameType === ServerManager::GAME_TYPE_BADLANDS;
    }

    public static function getFactionsCluster(bool $isBadlands = false): ?Cluster
    {
        /** @var NGEssentials|null $ess */
        $ess = Server::getInstance()->getPluginManager()->getPlugin('NGEssentials');
        if ($ess === null) {
            return null;
        }

        if ($isBadlands) {
            return $ess->getServerManager()->getCluster('Factions', ServerManager::GAME_TYPE_BADLANDS);
        } else {
            return $ess->getServerManager()->getCluster('Factions', ServerManager::GAME_TYPE_FARLANDS);
        }
    }

    public static function getSpawnLocation(): Location
    {
        return self::$spawnLocation ?? throw new InvalidArgumentException("Factions spawn position must be set first.");
    }

    public function onLoad(): void
    {
        self::$serverPrefix = TextFormat::DARK_GRAY . TextFormat::BOLD . '(' . TextFormat::GOLD . '!' . TextFormat::DARK_GRAY . ') ' . TextFormat::RESET . TextFormat::GRAY;

        parent::onLoad();

        /** @var NGEssentials|null $ess */
        $ess = $this->getServer()->getPluginManager()->getPlugin('NGEssentials');
        if ($ess === null) {
            return;
        }

        if (NGEssentials::isInDevelopmentMode()) {
            $this->getServer()->getLogger()->removeAttachment($ess->getErrorLogger());
        }

        $this->database = new Database($this, new Config($ess->getDataFolder() . 'credentials' . DIRECTORY_SEPARATOR . 'credentials.yml', Config::YAML));
        if ($ess->getServerManager()->getServerRegion() === ServerManager::REGION_US) {
            $this->startDBRecovery();
        }

        // Restore defaults
        Database::executeChangeRaw('UPDATE faction_vaults SET locked_player = NULL, server_id = NULL WHERE server_id = ?', [
            $ess->getServerManager()->getUniqueId(),
        ]);

        Database::getMySQLDatabase()->waitAll();

        // Setup enchantment feature before enabling the plugin.

        EnchantmentManager::setEnchantmentLevel([
            spl_object_hash(VanillaEnchantments::SHARPNESS()) => 13,
            spl_object_hash(VanillaEnchantments::UNBREAKING()) => 15,
            spl_object_hash(VanillaEnchantments::EFFICIENCY()) => 7,
            spl_object_hash(VanillaEnchantments::PROTECTION()) => 7,
            spl_object_hash(VanillaEnchantments::KNOCKBACK()) => 5,
            spl_object_hash(EnchantmentManager::VAMPIRE()) => 5,
        ]);

        EnchantmentManager::addEnchantExclusion(
            VanillaEnchantments::THORNS(),
            VanillaEnchantments::FEATHER_FALLING(),
            VanillaEnchantments::RESPIRATION(),
            EnchantmentManager::KILL_AURA(),
            EnchantmentManager::LETHAL_PRECISION(),
            EnchantmentManager::TRIPLE_SHOT(),
            EnchantmentManager::GUARDIAN_ANGEL());

        CooldownList::$consumable = [
            ItemTypeIds::ENCHANTED_GOLDEN_APPLE => 33 * 20,
        ];

        CooldownList::$usable = [
            ItemTypeIds::ENDER_PEARL => 16 * 20,
        ];

        Factions::registerCustomBlocks();

        TileFactory::getInstance()->register(SpawnerTile::class, ['nethergames:spawner']);

        $this->getServer()->getAsyncPool()->addWorkerStartHook(function (int $worker): void {
            $this->getServer()->getAsyncPool()->submitTaskToWorker(new class extends AsyncTask {
                public function onRun(): void
                {
                    Factions::registerCustomBlocks();
                    CustomItemRegistry::forceInitialization();
                }
            }, $worker);
        });

        TpaForm::addValidators(static function (Player $receiver, Player $requester): bool {
            $playerData = Factions::getInstance()->getPlayerData();
            $koth = Factions::getInstance()->getKoth();
            if (Factions::isBadlands()) {
                $requester->sendMessage(Factions::getPrefix() . TextFormat::RED . "Teleport requests is disabled in Badlands.");
                return false;
            } else if ($playerData->isFormBlocked($receiver) || $koth->inMatch($receiver) || $koth->inMatch($requester)) {
                $requester->sendMessage(Factions::getPrefix() . TextFormat::RED . "You cannot send teleport request to this player.");
                return false;
            }

            return true;
        }, true);
    }

    public function onEnable(): void
    {
        parent::onEnable();

        /** @var NGEssentials|null $ess */
        $ess = $this->getServer()->getPluginManager()->getPlugin('NGEssentials');
        if ($ess === null) {
            return;
        }

        $wm = $this->getServer()->getWorldManager();
        $wm->setAutoSave(false);

        $isBadlands = Factions::isBadlands();
        if (!$isBadlands) {
            if (!$wm->isWorldGenerated('wild')) {
                $generator = GeneratorManager::getInstance()->getGenerator('vanilla_overworld');
                $wm->generateWorld('wild', WorldCreationOptions::create()
                    ->setGeneratorClass($generator->getGeneratorClass())
                    ->setSeed(-43879934)); //   FL-1: -52515853 | FL-2: -75336154 | FL-3: -43879934
            } else {
                $wm->loadWorld('wild', true);
            }
            $wm->setAutoSaveInterval(60 * 60 * 20); // Save the world every 1 hour.

            $wild = $wm->getWorldByName('wild');
            $wild->setAutoSave(true);

            $wm->setDefaultWorld($wild);

            // 6.25% to be ticked.
            // A subchunk has 256 blocks

            // A chunk has 16 sub chunks
            // Current setup:
            // - Radius: 2 (16 chunks)
            // - Subchunks: 2*16 (32 sub chunks)
            // - Per block: 2 (32*2 64 blocks)

            // - Radius: 6 (4096 chunks)
            // - Subchunks
            self::$spawnLocation = Location::fromObject($wild->getSpawnLocation(), $wild, 270, 0);

            Area::setWarzoneSafezone();

            $sm = NGEssentials::getInstance()->getServerManager();
            $sm->setSpawn(Area::addVectorToLocation(new Vector3(0.5, 0.0, 0.5), 270, 0));

            // LEAD #1: Vector3(-19.5,1,-19.5)
            // LEAD #2: Vector3(-20.5,1,-24.5)
            // LEAD #3: Vector3(-20.5,1,-14.5)

            $facKillerText = new FloatingText(Area::addVectorToLocation(new Vector3(-19, 3, -19)), '§l§aFACTIONS KILLS LEADERBOARD§r');
            $facStreakText = new FloatingText(Area::addVectorToLocation(new Vector3(-20, 3, -24)), '§l§aCURRENT STREAK LEADERBOARD§r');
            $facBestStreakText = new FloatingText(Area::addVectorToLocation(new Vector3(-20, 3, -14)), '§l§aBEST STREAK LEADERBOARD§r');

            $entityManager = $this->getEssentials()->getEntityManager();
            $entityManager->addEntity($facKillerText);
            $entityManager->addEntity($facStreakText);
            $entityManager->addEntity($facBestStreakText);

            $entityManager->getPlugin()->getServerData()->setValue(ServerData::BOARDS, [
                0 => $facKillerText->getId(),
                1 => $facStreakText->getId(),
                2 => $facBestStreakText->getId()
            ]);

            OptimizedItemEntity::addValidator(static function (OptimizedItemEntity $entity): bool {
                return !Area::isAreaInside($entity->getPosition());
            });

            BlockDurability::init($wild, Path::join($this->getServer()->getDataPath(), "worlds"));
        } else {
            $wm->loadWorld('FactionsPvP');

            $world = $wm->getWorldByName('FactionsPvP');
            if ($world === null) {
                throw new RuntimeException('Factions PvP arena should have been loaded before this point!');
            }

            $wm->setDefaultWorld($world);

            self::$spawnLocation = Location::fromObject($world->getSpawnLocation(), $world, 0, 0);

            Area::setWarzoneSafezone();

            $sm = NGEssentials::getInstance()->getServerManager();
            $sm->setSpawn(Area::addVectorToLocation(new Vector3(0.5, 0.0, 0.5), 0, 0));

            $world->setAutoSave(false);
            $world->setTime(6000);
            $world->stopTime();

            $facKillerText = new FloatingText(Area::addVectorToLocation(new Vector3(10, 3, -7.5)), '§l§aFACTIONS KILLS LEADERBOARD§r');
            $facStreakText = new FloatingText(Area::addVectorToLocation(new Vector3(12, 3, 0)), '§l§aCURRENT STREAK LEADERBOARD§r');
            $facBestStreakText = new FloatingText(Area::addVectorToLocation(new Vector3(10, 3, 8.5)), '§l§aBEST STREAK LEADERBOARD§r');

            $entityManager = $this->getEssentials()->getEntityManager();
            $entityManager->addEntity($facKillerText);
            $entityManager->addEntity($facStreakText);
            $entityManager->addEntity($facBestStreakText);

            $entityManager->getPlugin()->getServerData()->setValue(ServerData::BOARDS, [
                0 => $facKillerText->getId(),
                1 => $facStreakText->getId(),
                2 => $facBestStreakText->getId()
            ]);

            OptimizedItemEntity::addValidator(static function (OptimizedItemEntity $entity): bool {
                return false;
            });
        }

        if (($world = $wm->getWorldByName('Hub')) !== null) {
            $wm->unloadWorld($world);
        }

        VanillaPlugin::CHEST_MINECART()->register($this);
        VanillaPlugin::ENDER_EYE()->register($this);
        VanillaPlugin::NAME_TAG()->register($this);
        VanillaPlugin::ENCHANTS()->register($this);
        VanillaPlugin::ENTITIES()->register($this);
        VanillaPlugin::FISHING_ROD()->register($this);
        VanillaPlugin::HOPPER()->register($this);

        CrateRouletteTask::$onTagCallback = function (Player $viewer, Item $reward): bool {
            $nbt = $reward->getCustomBlockData();

            $tagId = $nbt->getInt('CustomTagId');
            $tagName = $nbt->getString('CustomTagName');

            $factionInstance = Factions::getInstance();

            $playerData = $factionInstance->getPlayerData();
            if (!in_array($tagName, $playerData->getOwnedTags($viewer))) {
                $playerData->addTags($viewer, $tagId);
            } else {
                $factionInstance->getEconomyManager()->increasePlayerMoney($viewer->getName(), 15000, ignoreLock: true);

                $viewer->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You already have " . TextFormat::GOLD . $tagName . TextFormat::RED . " tag, so you've received 15000 coins in substitute.");
            }

            return false;
        };

        $enchantmentId = EnchantmentIdMap::getInstance();
        EnchantmentManager::addEnchantExclusion(
            $enchantmentId->fromId(EnchantmentIds::BANE_OF_ARTHROPODS),
            $enchantmentId->fromId(EnchantmentIds::LURE),
            $enchantmentId->fromId(EnchantmentIds::SMITE),
            $enchantmentId->fromId(EnchantmentIds::PUNCH),
            $enchantmentId->fromId(EnchantmentIds::FROST_WALKER),
            $enchantmentId->fromId(EnchantmentIds::FIRE_ASPECT),
            $enchantmentId->fromId(EnchantmentIds::LOYALTY),
            $enchantmentId->fromId(EnchantmentIds::CHANNELING),
            $enchantmentId->fromId(EnchantmentIds::RIPTIDE),
            $enchantmentId->fromId(EnchantmentIds::IMPALING),
            $enchantmentId->fromId(EnchantmentIds::MULTISHOT),
            $enchantmentId->fromId(EnchantmentIds::PIERCING),
            $enchantmentId->fromId(EnchantmentIds::QUICK_CHARGE),
            $enchantmentId->fromId(EnchantmentIds::LUCK_OF_THE_SEA),
            $enchantmentId->fromId(EnchantmentIds::SWIFT_SNEAK),
            $enchantmentId->fromId(EnchantmentIds::KNOCKBACK));

        $this->entityManager = new EntityManager($this);
        $this->enchantmentManager = new EnchantmentManager($this, false);
        $this->auctionHouse = new AuctionHouse($this);
        $this->itemManager = new ItemManager($this);
        $this->kitManager = new KitManager($this);
        $this->playerManager = new PlayerManager($this);
        $this->playerData = new PlayerData($this);
        $this->economyManager = new EconomyManager($this);
        $this->factionManager = new FactionManager($this);
        $this->eventEmitter = new EventEmitter($this);
        $this->rollbackEngine = new RollbackEngine($this);
        $this->challengeManager = new ChallengeManager($this);
        $this->bountyHunter = new BountyHunter();
        $this->playerChallengeManager = new PlayerChallengeManager($this);
        $this->crateManager = new CrateManager($this);
        $this->investigationManager = new InvestigationManager($this);
        $this->regionManager = new FactionRegionManager($this);
        if (!$isBadlands) {
            $this->claimManager = new ClaimManager($this);
            $this->chunkLimits = new FactionChunkLimits($this);
            $this->koth = new Koth($this);
        }
        $this->shop = new Shop($this->getEconomyManager(), 'Faction Shop');

        BaseCommand::registerCommands($this);

        ChatTypes::getInstance()->register(10, new FactionChat($this), ['f', 'faction']);
        ChatTypes::getInstance()->register(11, new AllyChat($this), ['a', 'ally']);

        $this->getRollbackEngine()->addRollbackListener(function (string $playerName, string $playerXuid, array $inventoryData): void {
            $player = Server::getInstance()->getPlayerExact($playerName);
            if ($player !== null && $player->isConnected()) {
                $this->getPlayerData()->setKillStreak($player, $inventoryData['streak']);
            } else {
                $this->getEventEmitter()->broadcastDefault($playerName, EventEmitter::EVENT_CHANGE_STREAK, [$inventoryData['streak']]);

                Database::executeChangeRaw("UPDATE player_data SET streak = ? WHERE xuid = ?", [$inventoryData['streak'], $playerXuid]);
            }
        });

        $scheduler = $this->getScheduler();

        $scheduler->scheduleRepeatingTask(new PurgeEntitiesTask(), 20);
        $scheduler->scheduleRepeatingTask(new CombatBroadcastTask($this), 20);

        if (!$isBadlands) {
            $this->getLogger()->info("Enable entities async updates.");

            $scheduler->scheduleRepeatingTask(new AsyncWorldTicker(['enable_entities_async_updates' => true]), 1);
            $scheduler->scheduleDelayedRepeatingTask(new EggSpawnTask(), 60 * 20, 30 * 60 * 20);
            $scheduler->scheduleDelayedRepeatingTask(new ClosureTask(function () use ($scheduler): void {
                $scheduler->scheduleRepeatingTask(new CountDownTask($this->getKoth()), 20);
            }), mt_rand(1, 2) * 10 * 60 * 20, mt_rand(1, 3) * 25 * 60 * 20);
            $scheduler->scheduleRepeatingTask(new WorkerObserveTask(), 30 * 20);
        }

        $scheduler->scheduleDelayedTask(new ClosureTask(function () use ($scheduler): void {
            $scheduler->scheduleRepeatingTask(new ServerRestartTask(NGEssentials::getInstance()), 20);
        }), (9 + mt_rand(4, 8)) * 60 * 60 * 20);

        if (!self::isBadlands() && NGEssentials::getInstance()->getServerManager()->getServerRegion() === ServerManager::REGION_US) {
            $scheduler->scheduleDelayedRepeatingTask(new ClosureTask(function (): void {
                Database::executeGeneric(Database::FACTIONS_DELETE_ENDPOINT);
            }), 5 * 20, 30 * 60 * 20);

            $this->getLogger()->info(TextFormat::GRAY . "Running database cleanup tasks.");
        }

        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->getServer()->getPluginManager()->registerEvents(new LogListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new EnchantListener($this), $this);

        AdventureSettingsObject::getInstance();

        $this->registerNPCs();

        $this->getLogger()->info('§eNether§6Games §bFactions-' . $this->getEssentials()->getServerManager()->getGameType() . ' enabled!');
    }

    public function getLoggerChannels(): array
    {
        // Discord relay for moderation logging is disabled by default. Populate this with your own
        // webhook paths ("<id>/<token>") to enable it; an empty list makes LimitAvoidableDiscordChannel
        // drop messages instead of posting.
        return [];
    }

    public function registerNPCs(): void
    {
        Await::f2c(function (): Generator {
            $entityManager = $this->getEssentials()->getEntityManager();
            $npcs = [];

            if (Factions::isBadlands()) {
                $pvpWorld = $this->getServer()->getWorldManager()->getWorldByName('FactionsPvP');
                $spawnLocation = Location::fromObject($pvpWorld->getSpawnLocation()->addVector(new Vector3(0.5, 2.0, 6.5)), $pvpWorld, 0, 0);

                $entityManager->addEntity(new FloatingText($spawnLocation, '§6Welcome to §eNether§6Games §cFactions§r§a!', '§fYou are currently located in §cBadlands' . TextFormat::EOL . '§cFactions pure PvP Arena' . TextFormat::EOL . TextFormat::RESET . TextFormat::EOL . TextFormat::GREEN . 'Return to§6 Farlands§a server by using §6/spawn§a command' . TextFormat::EOL . TextFormat::GREEN . 'or return to this platform using §c/pvp' . TextFormat::EOL . TextFormat::RESET . TextFormat::EOL . '§c§l!! PVP IS ENABLED ONCE YOU JUMP !!' . TextFormat::EOL));
            } else {
                // Warzone Drop: Vector3(x=37.5,y=1.9,z=-0.5)
                // Welcome Sign: Vector3(x=9.5,y=0.90000000000001,z=-0.5)

                $entityManager->addEntity(new FloatingText(Area::addVectorToLocation(new Vector3(9.5, 2.5, 0.5)), '§fWelcome to §eNether§6Games §cFactions§r§a §b§lFINAL SEASON!',
                    TextFormat::EOL . TextFormat::YELLOW . TextFormat::EOL .
                    '§c§l!!! BORDER LIMIT REMOVED !!!' . TextFormat::EOL .
                    TextFormat::EOL . TextFormat::YELLOW . TextFormat::EOL .
                    '§fIf you find any bugs during your stay,' . TextFormat::EOL .
                    'please report them to §b#bugs §fon our Discord server.' . TextFormat::EOL .
                    '§cPvP is enabled in the warzone area!' . TextFormat::EOL . TextFormat::YELLOW . TextFormat::EOL .
                    '§l§6Quick Links:' . TextFormat::EOL .
                    '§r§6Vote: §rngmc.co/v' . TextFormat::EOL .
                    '§6Store: §rngmc.co/store' . TextFormat::EOL .
                    '§6Discord: §rngmc.co/d' . TextFormat::EOL .
                    '§6Twitter: §r@NetherGamesMC'
                ));

                $entityManager->addEntity(new FloatingText(Area::addVectorToLocation(new Vector3(37.5, 2.5, 0.5)),
                    TextFormat::RED . 'You are now entering',
                    TextFormat::GREEN . TextFormat::EOL . TextFormat::RED . 'The Warzone Area' .
                    TextFormat::WHITE . TextFormat::EOL . TextFormat::WHITE . TextFormat::EOL .
                    TextFormat::YELLOW . 'If you are inexperienced in factions' . TextFormat::EOL .
                    TextFormat::YELLOW . 'and/or bad at combat, you are advised to use ' . TextFormat::AQUA . '/wild' . TextFormat::EOL . TextFormat::WHITE .
                    TextFormat::YELLOW . 'to find the perfect place for your base.' . TextFormat::EOL . TextFormat::DARK_AQUA . TextFormat::EOL .
                    TextFormat::LIGHT_PURPLE . 'You may be targeted if you' . TextFormat::EOL .
                    TextFormat::LIGHT_PURPLE . 'choose to jump into the warzone.'
                ));

                // Shop Directory:      Vector3(x=21.5,y=2,z=-16.5)
                // Hall of Fame:        Vector3(x=-12.5,y=1,z=0.5)

                $entityManager->addEntity(new FloatingText(Area::addVectorToLocation(new Vector3(21, 0.75, -17)), '§e§lMarketplace',
                    TextFormat::WHITE . TextFormat::EOL .
                    'Have something to buy from our shops?' . TextFormat::EOL .
                    'Get inside and have a look around.'
                ));

                $entityManager->addEntity(new FloatingText(Area::addVectorToLocation(new Vector3(-20.5, 3, 0.5)), '§e§lFactions Hall of Fame',
                    TextFormat::WHITE . TextFormat::EOL . 'Only for those who are considered worthy' . TextFormat::EOL .
                    'and powerful can be placed in the holy' . TextFormat::EOL .
                    'building of factions.' . TextFormat::EOL . TextFormat::WHITE . TextFormat::EOL .
                    TextFormat::GOLD . 'Secure your place by fighting more players and' . TextFormat::EOL .
                    TextFormat::GOLD . 'get more faction power by inviting' . TextFormat::EOL .
                    TextFormat::GOLD . 'more players into your faction!'
                ));

                // World Selector 1 (Bounty Hunter):        new Vector3(23, 1, -12)
                // World Selector 2 (Wilderness Selector):  new Vector3(25, 1, -7)
                // World Selector 3 (PvP Selector):         new Vector3(25, 1, 7)
                // World Selector 4 (Shop Tp):              new Vector3(23, 1, 12)

                $skin = new Skin('Standard_Custom', SkinUtils::getTextureFromResources('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'factions.png'));
                $npcs[] = new HumanNPC(Area::addVectorToLocation(new Vector3(23.5, 1, -11.5), 90, 0), TextFormat::BOLD . TextFormat::RED . 'Teleport to wild', $skin, function (Player $player) {
                    Server::getInstance()->dispatchCommand($player, 'wild');
                });

                $skin = new Skin('Standard_Custom', SkinUtils::getTextureFromResources('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'catgirl.png'));
                $npcs[] = new HumanNPC(Area::addVectorToLocation(new Vector3(25.5, 1, -6.5), 90, 0), TextFormat::BOLD . TextFormat::RED . 'Wilderness Selector', $skin, function (Player $player) {
                    Forms::sendWildernessSelector($player);
                });

                $skin = new Skin('Standard_Custom', SkinUtils::getTextureFromResources('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'king.png'));
                $npcs[] = new HumanNPC(Area::addVectorToLocation(new Vector3(25.5, 1, 7.5), 90, 0), TextFormat::BOLD . TextFormat::RED . 'PvP Selector', $skin, function (Player $player) {
                    Server::getInstance()->dispatchCommand($player, 'pvp');
                });

                $skin = new Skin('Standard_Custom', SkinUtils::getTextureFromResources('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'sw.png'));
                $npcs[] = new HumanNPC(Area::addVectorToLocation(new Vector3(23.5, 1, 12.5), 90, 0), TextFormat::BOLD . TextFormat::RED . "DISABLED" . TextFormat::EOL . TextFormat::BOLD . TextFormat::YELLOW . '1 vs 1', $skin, function (Player $player) {
                    $player->sendMessage(TextFormat::RED . "This feature is temporarily disabled for thorough testing.");
                });

                // Auction House Sign: Vector3(x=11.5,y=1,z=32.5)
                // Bounty Hunter Sign: Vector3(x=37.5,y=0,z=55.5)

                $entityManager->addEntity(new FloatingText(Area::addVectorToLocation(new Vector3(66.5, 3, 38.5)), '§e§lTrading Mechanics',
                    TextFormat::WHITE . TextFormat::EOL . 'Trading with another player is now possible!' . TextFormat::EOL .
                    'If another player would like to trade' . TextFormat::EOL .
                    'with you, be sure to ask them do' . TextFormat::EOL . TextFormat::WHITE . TextFormat::EOL .
                    TextFormat::GOLD . '/trade [player] {price}' . TextFormat::EOL . TextFormat::WHITE . TextFormat::EOL .
                    'It is much safer and secure!' . TextFormat::EOL .
                    'No strings attached'
                ));

                $entityManager->addEntity(new FloatingText(Area::addVectorToLocation(new Vector3(11.5, 1, 32.5)), '§d§lAuction House',
                    TextFormat::WHITE . TextFormat::EOL .
                    'Got something interesting to' . TextFormat::EOL . 'place in auction?'));

                $entityManager->addEntity(new FloatingText(Area::addVectorToLocation(new Vector3(37.5, 0, 55.5)),
                    '§d§lBounty Hunter Shack',
                    TextFormat::WHITE . TextFormat::EOL .
                    'Kill a player for my needs and' . TextFormat::EOL .
                    'I return you the rewards.'));

                // Auction House: Vector3(x=11.5,y=1,z=57.5)
                // Bounty Hunter: Vector3(x=37.5,y=-1,z=66.5)

                $skin = new Skin('Standard_Custom', SkinUtils::getTextureFromResources('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'duels.png'));
                $npcs[] = [new HumanNPC(Area::addVectorToLocation(new Vector3(11.5, 1, 57.5), 180), TextFormat::BOLD . TextFormat::YELLOW . 'Auction House' . TextFormat::EOL . TextFormat::RESET . TextFormat::GREEN . 'Tap me!', $skin, function (Player $player) {
                    $this->getServer()->dispatchCommand($player, 'ah');
                }), 1.25];

                $skin = new Skin('Standard_Custom', SkinUtils::getTextureFromResources('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'tb.png'));
                $npcs[] = [new HumanNPC(Area::addVectorToLocation(new Vector3(37.5, -1, 66.5), 180), TextFormat::BOLD . TextFormat::YELLOW . 'Bounty Hunter' . TextFormat::EOL . TextFormat::RESET . TextFormat::GREEN . 'Tap me!', $skin, function (Player $player) {
                    $this->getServer()->dispatchCommand($player, 'bounty');
                }), 1.25];

                // Shop 1 (Kits):                   Vector3(-2.5, 1, -30.5)
                // Shop 2 (Decoration):             Vector3(-4.5, 1, -37.5)
                // Shop 3 (Blocks):                 Vector3(-3.5, 1, -48.5)
                // Shop 4 (Potions):                Vector3(-3.5, 1, -59.5)

                // Shop 5 (Utility):                Vector3(-3.5, 1, -69.5)
                // Shop 6 (Farming):                Vector3(34.5, 1, -67.5)
                // Shop 7 (Food):                   Vector3(35.5, 1, -54.5)
                // Shop 8 (Mineral & Mob Drops):    Vector3(30.5, 1, -46.5)

                // Shop 9 (Spawners):               Vector3(34.5, 1, -43.5)
                // Shop 10 (Enchantment):           Vector3(35.5, 1, -33.5)
                // Shop 11 (Shards & Scrolls):      Vector3(31.5, 1, -31.5)

                /* MARKET */
                $shopKeepers = [
                    'Kits' => [Area::addVectorToLocation(new Vector3(-2.5, 1, -30.5)), SkinUtils::getTextureFromResources('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'sw.png')],
                    'Decoration' => [Area::addVectorToLocation(new Vector3(-4.5, 1, -37.5)), SkinUtils::getTextureFromResources('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'maid2.png')],
                    'Blocks' => [Area::addVectorToLocation(new Vector3(-3.5, 1, -48.5)), SkinUtils::getTextureFromResources('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'cq.png')],
                    'Potions' => [Area::addVectorToLocation(new Vector3(-3.5, 1, -58.5)), SkinUtils::getTextureFromResources('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'creative.png')],

                    'Utilities' => [Area::addVectorToLocation(new Vector3(-2.5, 1, -68.5)), SkinUtils::getTextureFromResources('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'mm.png')],
                    'Farming' => [Area::addVectorToLocation(new Vector3(34.5, 1, -67.5), 90, 0), SkinUtils::getTextureFromResources('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'sb.png')],
                    'Food' => [Area::addVectorToLocation(new Vector3(35.5, 1, -54.5), 90, 0), SkinUtils::getTextureFromResources('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'maid.png')],
                    'Minerals & Mob Drops' => [Area::addVectorToLocation(new Vector3(30.5, 1, -46.5)), SkinUtils::getTextureFromResources('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'sp.png')],
                    'Spawners' => [Area::addVectorToLocation(new Vector3(34.5, 1, -43.5), 90, 0), SkinUtils::getTextureFromResources('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'bw.png')],

                    'Enchantments' => [Area::addVectorToLocation(new Vector3(35.5, 1, -33.5), 90, 0), SkinUtils::getTextureFromResources('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'mm.png')],
                    'Shards & Scrolls' => [Area::addVectorToLocation(new Vector3(31.5, 1, -31.5)), SkinUtils::getTextureFromResources('skins' . DIRECTORY_SEPARATOR . 'npcs' . DIRECTORY_SEPARATOR . 'creative.png')],
                ];

                // shopkeeper skin
                foreach ($shopKeepers as $category => [$location, $skinPath]) {
                    $skin = new Skin('Standard_Custom', $skinPath);

                    $npcs[] = [new HumanNPC($location, TextFormat::BOLD . TextFormat::AQUA . $category . TextFormat::EOL . TextFormat::RESET . TextFormat::YELLOW . 'Click to view!', $skin, function (Player $player) use ($category) {
                        /** @var NGPlayer $player */
                        $this->getShop()->getCategory($category)->send($player, $player->getInputMode() === InputMode::TOUCHSCREEN);
                    }), 1.25];
                }

                Database::executeSelect(Database::TOP_FACTIONS, [], yield, yield Await::REJECT);

                $result = yield Await::ONCE;

                $factionNPCs = [];
                foreach ($result as ['faction_name' => $factionName, 'leader' => $leader, 'strength' => $strength]) {
                    SkinUtils::getSkin($leader, yield);

                    /** @var Skin $skin */
                    $skin = yield Await::ONCE;

                    $npc = match (count($factionNPCs)) {
                        0 => new HumanNPC(Area::addVectorToLocation(new Vector3(-44.5, 3, 0.5), 270, 0), TextFormat::BOLD . TextFormat::YELLOW . "1st Place " . TextFormat::RESET . TextFormat::GRAY . "- " . TextFormat::AQUA . $factionName . " - " . TextFormat::YELLOW . "$strength  " . TextFormat::EOL . TextFormat::GRAY . "Leader: " . TextFormat::WHITE . $leader, $skin),
                        1 => new HumanNPC(Area::addVectorToLocation(new Vector3(-45.5, 2, 3.5), 270, 0), TextFormat::BOLD . TextFormat::GOLD . "2nd Place " . TextFormat::RESET . TextFormat::GRAY . "- " . TextFormat::AQUA . $factionName . " - " . TextFormat::YELLOW . "$strength " . TextFormat::EOL . TextFormat::GRAY . "Leader: " . TextFormat::WHITE . $leader, $skin),
                        2 => new HumanNPC(Area::addVectorToLocation(new Vector3(-45.5, 2, -2.5), 270, 0), TextFormat::BOLD . TextFormat::RED . "3rd Place " . TextFormat::RESET . TextFormat::GRAY . "- " . TextFormat::AQUA . $factionName . " - " . TextFormat::YELLOW . "$strength " . TextFormat::EOL . TextFormat::GRAY . "Leader: " . TextFormat::WHITE . $leader, $skin),
                        3 => new HumanNPC(Area::addVectorToLocation(new Vector3(-44.5, 1, 6.5), 270, 0), TextFormat::YELLOW . "#4 " . TextFormat::AQUA . "$factionName - " . TextFormat::YELLOW . "$strength " . TextFormat::EOL . TextFormat::GRAY . "Leader: " . TextFormat::WHITE . " $leader", $skin),
                        default => new HumanNPC(Area::addVectorToLocation(new Vector3(-44.5, 1, -5.5), 270, 0), TextFormat::YELLOW . "#5 " . TextFormat::AQUA . "$factionName - " . TextFormat::YELLOW . "$strength " . TextFormat::EOL . TextFormat::GRAY . "Leader: " . TextFormat::WHITE . " $leader", $skin),
                    };

                    $npc->setCallable(static function (Player $player) use ($factionName): void {
                        $player->getServer()->dispatchCommand($player, 'f info ' . $factionName);
                    });

                    $npcs[] = $npc;
                    $factionNPCs[] = [$factionName, $npc];
                }

                $defaultSkin = new Skin('Standard_Custom', SkinUtils::getTextureFromResources('skins' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'steve.png'));
                while (count($factionNPCs) < 5) {
                    $placeNumber = count($factionNPCs);

                    $npc = match ($placeNumber) {
                        0 => new HumanNPC(Area::addVectorToLocation(new Vector3(-44.5, 3, 0.5), 270, 0), TextFormat::BOLD . TextFormat::YELLOW . "1st Place " . TextFormat::RESET . TextFormat::GRAY . "- " . TextFormat::RED . "No Record - " . TextFormat::YELLOW . "0  " . TextFormat::EOL . TextFormat::GRAY . "Leader: " . TextFormat::WHITE . 'Unknown', $defaultSkin),
                        1 => new HumanNPC(Area::addVectorToLocation(new Vector3(-45.5, 2, 3.5), 270, 0), TextFormat::BOLD . TextFormat::GOLD . "2nd Place " . TextFormat::RESET . TextFormat::GRAY . "- " . TextFormat::RED . "No Record - " . TextFormat::YELLOW . "0 " . TextFormat::EOL . TextFormat::GRAY . "Leader: " . TextFormat::WHITE . 'Unknown', $defaultSkin),
                        2 => new HumanNPC(Area::addVectorToLocation(new Vector3(-45.5, 2, -2.5), 270, 0), TextFormat::BOLD . TextFormat::RED . "3rd Place " . TextFormat::RESET . TextFormat::GRAY . "- " . TextFormat::RED . "No Record - " . TextFormat::YELLOW . "0 " . TextFormat::EOL . TextFormat::GRAY . "Leader: " . TextFormat::WHITE . 'Unknown', $defaultSkin),
                        3 => new HumanNPC(Area::addVectorToLocation(new Vector3(-44.5, 1, 6.5), 270, 0), TextFormat::YELLOW . "#4 " . TextFormat::RED . "No Record - " . TextFormat::YELLOW . "0 " . TextFormat::EOL . TextFormat::GRAY . "Leader: " . TextFormat::WHITE . " Unknown", $defaultSkin),
                        4 => new HumanNPC(Area::addVectorToLocation(new Vector3(-44.5, 1, -5.5), 270, 0), TextFormat::YELLOW . "#5 " . TextFormat::RED . "No Record - " . TextFormat::YELLOW . "0 " . TextFormat::EOL . TextFormat::GRAY . "Leader: " . TextFormat::WHITE . " Unknown", $defaultSkin),
                    };

                    $npc->setCallable(static function (Player $player) use ($placeNumber): void {
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Oops, you were here too soon, create a faction to claim #' . ($placeNumber + 1) . ' place :D');
                    });

                    $npcs[] = $npc;
                    $factionNPCs[] = [null, $npc];
                }

                $this->getFactionManager()->setFactionNPCs($factionNPCs);

                foreach ($npcs as $npc) {
                    if (is_array($npc)) {
                        $npc[0]->setScale($npc[1]);

                        $entityManager->addEntity($npc[0]);
                    } else {
                        $npc->setScale(1.5);

                        $entityManager->addEntity($npc);
                    }
                }
            }
        });
    }

    public function onDisable(): void
    {
        BlockDurability::close();
        if ($this->getKoth() !== null) {
            foreach ($this->getKoth()->getPlayers() as $player) {
                $this->getKoth()->removePlayer($player);
            }
        }

        parent::onDisable();

        $wild = $this->getServer()->getWorldManager()->getWorldByName('wild');
        $wild?->save(true);
    }

    public static function registerCustomBlocks(): void
    {
        self::registerSimpleBlock(Ids::DRAGON_EGG, new DragonEgg(), [
            "dragon_egg" => static fn(DragonEgg $block) => $block
        ]);
        self::registerSimpleBlock(Ids::MOB_SPAWNER, new SpawnerBlock(), [
            "mob_spawner" => static fn(SpawnerBlock $block) => $block,
            "monster_spawner" => static fn(SpawnerBlock $block) => $block
        ]);

        // TNT block must be mapped in order to work.
        self::registerSimpleBlock(Ids::TNT, $tntBlock = new TNTBlock(), [
            "tnt" => static fn(TNTBlock $block) => $block,
        ], false);
        self::registerSimpleBlock(Ids::UNDERWATER_TNT, $tntBlock, [
            "underwater_tnt" => static fn(TNTBlock $block) => $block->setWorksUnderwater(true),
        ], false);

        $deserializer = GlobalBlockStateHandlers::getDeserializer();
        $deserializer->map(Ids::TNT, function (Reader $in) use ($tntBlock): Block {
            return (clone $tntBlock)
                ->setUnstable($in->readBool(StateNames::EXPLODE_BIT));
        });
        $deserializer->map(Ids::UNDERWATER_TNT, function (Reader $in) use ($tntBlock): Block {
            return (clone $tntBlock)
                ->setUnstable($in->readBool(StateNames::EXPLODE_BIT))
                ->setWorksUnderwater(true);
        });
        GlobalBlockStateHandlers::getSerializer()->map($tntBlock, function (TNTBlock $block): Writer {
            return Writer::create($block->worksUnderwater() ? Ids::UNDERWATER_TNT : Ids::TNT)
                ->writeBool(StateNames::EXPLODE_BIT, $block->isUnstable());
        });
    }

    /**
     * @param Closure[] $stringToItemParserNames
     *
     * @phpstan-template T of Block
     * @phpstan-param (Closure(T): T)[] $stringToItemParserNames
     */
    private static function registerSimpleBlock(string $id, Block $block, array $stringToItemParserNames, bool $simple = true): void
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

        if ($simple) {
            GlobalBlockStateHandlers::getDeserializer()->mapSimple($id, fn() => clone $block);
            GlobalBlockStateHandlers::getSerializer()->mapSimple($block, $id);
        }

        foreach ($stringToItemParserNames as $name => $fn) {
            StringToItemParser::getInstance()->override($name, fn() => $fn($block)->asItem());
        }
    }

    /**
     * @return PlayerManager
     */
    public function getPlayerManager(): PlayerManager
    {
        return parent::getPlayerManager();
    }

    /**
     * @return PlayerData
     */
    public function getPlayerData(): PlayerData
    {
        return parent::getPlayerData();
    }

    /**
     * @return EventEmitter
     */
    public function getEventEmitter(): EventEmitter
    {
        return parent::getEventEmitter();
    }

    public function getRegionManager(): FactionRegionManager
    {
        return $this->regionManager;
    }

    /**
     * @return BountyHunter
     */
    public function getBountyHunter(): BountyHunter
    {
        return $this->bountyHunter;
    }

    /**
     * @return FactionManager
     */
    public function getFactionManager(): FactionManager
    {
        return $this->factionManager;
    }

    /**
     * @return ClaimManager
     */
    public function getClaimManager(): ClaimManager
    {
        return $this->claimManager;
    }

    /**
     * @return Koth|null
     */
    public function getKoth(): ?Koth
    {
        return $this->koth;
    }

    /**
     * Database recovery system, an application-level database consistency check.
     * The 'faction_member' where faction_role = 2 and 'factions' tables must have the same total of rows.
     * This will make sure that both tables are in sync, but this is very highly unlikely to happen in the future.
     */
    private function startDBRecovery(): void
    {
        // Attempts to validate all factions data, this process will take a huge chunk of memory usage
        // However, they will be deleted when the server restarts
        Await::f2c(function () {
            Database::executeSelectRaw("SELECT * FROM factions", [], yield, yield Await::REJECT);
            $data = yield Await::ONCE;

            $orphaned = [];
            foreach ($data as $a) {
                $orphaned[$a['faction_id']] = $a['faction_name'];
            }

            $this->getLogger()->info(TextFormat::YELLOW . "Processed a total of " . count($orphaned) . " factions.");

            Database::executeSelectRaw("SELECT * FROM faction_members WHERE faction_role = 2", [], yield, yield Await::REJECT);
            $data = yield Await::ONCE;

            foreach ($data as $a) {
                unset($orphaned[$a['faction_id']]);
            }

            if (empty($orphaned)) {
                $this->getLogger()->info(TextFormat::GREEN . "Database is currently healthy and validated.");
            } else {
                $this->getLogger()->info(TextFormat::RED . "Found " . count($orphaned) . " invalid factions, starting recovery.");

                foreach ($orphaned as $factionId => $factionName) {
                    Database::executeSelectRaw("SELECT * FROM factions WHERE faction_id = ?", [
                        $factionId
                    ], yield, yield Await::REJECT);

                    $data = yield Await::ONCE;

                    $factionOwner = $data[0]['leader'];

                    Database::executeSelectRaw("SELECT * FROM faction_members WHERE faction_id = ?", [
                        $factionId
                    ], yield, yield Await::REJECT);

                    $data = yield Await::ONCE;

                    Database::executeSelectRaw("SELECT * FROM faction_members WHERE player_name = ?", [
                        $factionOwner
                    ], yield, yield Await::REJECT);

                    $ownerResult = (yield Await::ONCE)[0] ?? [];

                    // If the table data is empty, we will try to search for the owner of this factions and if the
                    // faction owner have not joined any faction, we will try to recover it back to its original owner
                    // and if the owner joined a faction, we will delete this faction from our database.
                    if (empty($data)) {
                        if (empty($ownerResult)) {
                            Database::executeInsert(Database::ADD_FACTION_PLAYER, [
                                'player_name' => $factionOwner,
                                'faction_role' => Faction::LEADER,
                                'faction_id' => $factionId
                            ], yield, yield Await::REJECT);

                            $this->getLogger()->warning("Recovery method for faction with id='" . $factionId . "' is 0x1");
                        } else {
                            Database::executeChangeRaw("DELETE FROM factions WHERE faction_id = ?", [
                                $factionId
                            ], yield, yield Await::REJECT);

                            $this->getLogger()->warning("Recovery method for faction with id='" . $factionId . "' is 0x2");
                        }

                        yield Await::ONCE;

                        continue;
                    }

                    $playerName = []; // player_name => faction_role

                    foreach ($data as $value) {
                        $playerName[$value['player_name']] = $value['faction_role'];
                    }

                    // Check if the owner is still in this faction, if not, we choose highest ranking possible
                    // in this database entry
                    if (isset($ownerResult['player_name']) && isset($playerName[$ownerResult['player_name']])) {
                        Database::executeInsert(Database::SET_FACTION_ROLE, [
                            'player_name' => $factionOwner,
                            'faction_role' => Faction::LEADER,
                            'faction_id' => $factionId
                        ], yield, yield Await::REJECT);

                        $this->getLogger()->warning("Recovery method for faction with id='" . $factionId . "' is 0x3");
                        yield Await::ONCE;
                    } else {
                        if (($target = array_search(Faction::OFFICER, $playerName)) === false) {
                            $target = array_rand($playerName);
                        }

                        Database::executeInsert(Database::SET_FACTION_ROLE, [
                            'player_name' => $target,
                            'faction_role' => Faction::LEADER,
                            'faction_id' => $factionId
                        ], yield, yield Await::REJECT);
                        Database::executeInsert(Database::UPDATE_SET_FACTION_LEADER, [
                            'player_name' => $target,
                            'faction_id' => $factionId
                        ], yield, yield Await::REJECT);

                        $this->getLogger()->warning("Recovery method for faction with id='" . $factionId . "' is 0x4");

                        yield Await::ALL;
                    }
                }
            }
        }, catches: Database::getFailClosure(true));

        Database::getMySQLDatabase()->waitAll();
    }
}
