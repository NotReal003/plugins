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

namespace skyblock\player;

use Closure;
use Generator;
use libMMO\item\item\MiniHelperItem;
use libMMO\item\ItemStorage;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use libMMO\utils\AdventureSettingsObject;
use libMMO\utils\AwaitUtils;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\player\PlayerData as NGPlayerData;
use NetherGames\NGEssentials\ServerManager;
use NetherGames\NGEssentials\utils\CustomIcon;
use pocketmine\item\Item;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;
use skyblock\commands\SkyBlockCommand;
use skyblock\crates\CrateManager;
use skyblock\entities\helpers\MiniHelper;
use skyblock\forms\IslandForm;
use skyblock\islands\Island;
use skyblock\islands\IslandManager;
use skyblock\item\CustomItemManager;
use skyblock\SkyBlock;
use skyblock\utils\Database;
use skyblock\utils\InvestigationManager;
use SOFe\AwaitGenerator\Await;
use function array_sum;
use function number_format;
use function str_starts_with;

class PlayerManager extends \libMMO\player\PlayerManager
{
    public function setupPlayer(Player $player, bool $newPlayer = false): void
    {
        parent::setupPlayer($player, $newPlayer);

        $playerData = $this->getPlugin()->getPlayerData();
        $ngPlayerData = NGEssentials::getInstance()->getPlayerData();

        Await::f2c(function () use ($player, $newPlayer, $playerData, $ngPlayerData) {
            AwaitUtils::waitPlayerSpawned($player, yield);
            yield Await::ONCE;

            AdventureSettingsObject::getInstance()->setBuildingPermission($player, false);

            yield $this->claimVoterKeys($player);

            if ($newPlayer) {
                $items = [];

                $this->getPlugin()->getPlayerData()->setValue($player, PlayerData::PLAYER_MONEY, 1000);

                $items[] = CustomItemManager::getKitItem('Starter', TextFormat::AQUA);

                foreach ($items as $item) {
                    $player->getInventory()->addItem($item);
                }

                $playerData->getPlugin()->getPlayerData()->saveData($player);

                if ($this->getPlugin()->isAgora()) {
                    $player->setGamemode(GameMode::ADVENTURE());
                    $player->setNoClientPredictions(false);

                    AwaitUtils::waitPlayerSpawned($player, yield);
                    yield Await::ONCE;

                    IslandForm::sendWelcomeForm($player);
                } else {
                    // No Agora to hand the player off to, so welcome them here instead of bouncing to a
                    // server that may not be running. The welcome form leads to island creation, and
                    // IslandManager::createIsland() builds the island locally when not on Agora.
                    $player->setGamemode(GameMode::ADVENTURE());
                    $player->setNoClientPredictions(false);

                    AwaitUtils::waitPlayerSpawned($player, yield);
                    yield Await::ONCE;

                    if (!$player->isConnected()) {
                        return;
                    }

                    IslandForm::sendWelcomeForm($player);
                }
            } else {
                $this->processMiniHelpers($player, yield);
                yield Await::ONCE;

                $playerName = $player->getName();
                $islandManager = $this->getPlugin()->getIslandManager();
                $island = $islandManager->getIslandByOwner($playerName);

                $islandManager->getIslandLocation($playerName, yield Await::RESOLVE_MULTI);

                /**
                 * @var int $status
                 * @var string|null $serverUniqueId
                 */
                [$status, $serverUniqueId] = yield Await::ONCE;

                if (!$player->isConnected()) {
                    return;
                }

                if ($status !== IslandManager::STATUS_NOT_CREATED) {
                    $playerData->setValue($player, PlayerData::HAS_ISLAND, true);
                }

                $adminMode = false;
                $targetIslandName = $playerData->getString($player, PlayerData::TARGET_ISLAND);
                $adminTargetIsland = $playerData->getString($player, PlayerData::TARGET_ISLAND_ADMIN);

                if (empty($targetIslandName) && !empty($adminTargetIsland)) {
                    $adminMode = true;
                    $targetIslandName = $adminTargetIsland;
                }

                $newIsland = $playerData->getInt($player, PlayerData::NEW_ISLAND);

                if ($ngPlayerData->getBool($player, NGPlayerData::TRACK)) {
                    $playerName = $ngPlayerData->getString($player, NGPlayerData::TRACK);

                    $target = $this->getPlugin()->getServer()->getPlayerExact($playerName);
                    if ($target !== null && $target->isConnected()) {
                        $player->teleport($target->getPosition());
                        $player->setNoClientPredictions(false);

                        AdventureSettingsObject::getInstance()->setBuildingPermission($player, false);
                    }
                } else if (!empty($targetIslandName) && !$this->getPlugin()->isAgora()) {
                    $playerData->unsetValue($playerName, PlayerData::TARGET_ISLAND);
                    $playerData->unsetValue($playerName, PlayerData::TARGET_ISLAND_ADMIN);

                    $islandManager->getIslandLocation($targetIslandName, yield Await::RESOLVE_MULTI);

                    /**
                     * @var int $status
                     */
                    [$status] = yield Await::ONCE;

                    if (!$player->isConnected()) {
                        return;
                    }

                    if ($status === IslandManager::STATUS_NOT_CREATED) {
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Are you sure that player has an island?");
                    } else if (!$this->getPlugin()->isAgora()) {
                        $islandLoaded = $islandManager->getIslandByOwner($targetIslandName);

                        // Island is not loaded in this server and the server id is valid.
                        // We can spawn this island in the server here.
                        if ($islandLoaded === null) {
                            $player->sendMessage(MMOPlugin::getPrefix() . "Loading the island in this server, please wait.");

                            $islandManager->loadIsland($targetIslandName, yield Await::RESOLVE_MULTI);

                            /**
                             * @var int $status
                             * @var Island $island
                             */
                            [$status, $island] = yield Await::ONCE;

                            if (!$player->isConnected()) {
                                return;
                            }

                            switch ($status) {
                                case IslandManager::ISLAND_ALREADY_LOADED:
                                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "The island has been loaded at another server, please try again.");
                                    break;
                                case IslandManager::ISLAND_LOAD_ERROR:
                                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Island block storage is offline, this error is internal and no data were lost. Please retry in a few minutes.");
                                    break;
                                case IslandManager::ISLAND_WORLD_ERROR:
                                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "The island you are going into is suffering from world corruption.");
                                    break;
                                case IslandManager::ISLAND_LOADING_DISABLED:
                                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "This service is temporarily disabled, try again later.");
                                    break;
                                case IslandManager::ISLAND_NOT_EXISTS:
                                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Are you sure that player has an island?");
                                    break;
                                case IslandManager::ISLAND_WORLD_LOST:
                                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "The island you are going into is lost.");
                                    break;
                                default:
                                    if ($adminMode) {
                                        InvestigationManager::teleportToLocation($player, $island);
                                        break;
                                    }

                                    $player->teleport($island->getSpawnPosition());
                                    break;
                            }
                        } else {
                            if ($adminMode) {
                                InvestigationManager::teleportToLocation($player, $islandLoaded);
                                return;
                            }

                            $player->teleport($islandLoaded->getSpawnPosition());
                        }
                    }
                } else if (!$this->getPlugin()->isAgora()) {
                    if ($serverUniqueId === $this->getPlugin()->getEssentials()->getServerManager()->getUniqueId()) {
                        if ($island === null || $island->getWorld() === null || !$island->getWorld()->isLoaded()) {
                            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Something went wrong while teleporting to your loaded island, transferring to Agora");

                            $this->getPlugin()->getPlayerManager()->transferPlayer($player, ServerManager::GAME_TYPE_AGORA);
                        } else {
                            $player->teleport($island->getSpawnPosition());
                        }
                    } else if ($status === IslandManager::STATUS_NOT_CREATED) {
                        AwaitUtils::waitPlayerSpawned($player, yield);
                        yield Await::ONCE;

                        if (!$player->isConnected()) {
                            return;
                        }

                        if ($newIsland >= 0) {
                            $playerData->unsetValue($playerName, PlayerData::NEW_ISLAND);

                            $islandManager->createIsland($player, $newIsland);
                        } else {
                            // Offer creation on this server rather than transferring to Agora, so a
                            // standalone Skyland server is usable on its own.
                            $player->sendMessage(MMOPlugin::getPrefix() . "You don't have an island yet, pick one to get started.");

                            IslandForm::sendIslandCreationForm($player, $this->getPlugin());
                        }
                    } else if ($status === IslandManager::STATUS_CREATED_AND_LOCKED) {
                        $player->sendMessage(TextFormat::RED . 'Transferring you to ' . $serverUniqueId . '.');

                        $islandManager->getPlugin()->getPlayerManager()->transferPlayer($player, ServerManager::GAME_TYPE_SKYLAND, $serverUniqueId);
                    } else {
                        $islandManager->loadIsland($playerName, yield Await::RESOLVE_MULTI);

                        /**
                         * @var int $status
                         * @var Island $island
                         */
                        [$status, $island] = yield Await::ONCE;

                        if (!$player->isConnected()) {
                            return;
                        }

                        switch ($status) {
                            case IslandManager::ISLAND_ALREADY_LOADED:
                                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Your island is being loaded by another server.");
                                break;
                            case IslandManager::ISLAND_LOAD_ERROR:
                                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Island block storage is offline, this error is internal and no data were lost. Please retry in a few minutes.");
                                break;
                            case IslandManager::ISLAND_WORLD_ERROR:
                                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Your island is currently suffering from world corruption");
                                break;
                            case IslandManager::ISLAND_LOADING_DISABLED:
                                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "This service is temporarily disabled, try again later.");
                                break;
                            case IslandManager::ISLAND_NOT_EXISTS:
                                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Your island doesn't seem to be exists?");
                                break;
                            case IslandManager::ISLAND_WORLD_LOST:
                                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Your island were unable to be loaded, please report this issue to an administrator at ngmc.co/d');
                                break;
                            default:
                                $player->teleport($island->getSpawnPosition());
                                return;
                        }

                        $this->getPlugin()->getPlayerManager()->transferPlayer($player, ServerManager::GAME_TYPE_AGORA);
                    }
                } else if ($status === IslandManager::STATUS_NOT_CREATED) {
                    $player->setGamemode(GameMode::ADVENTURE());
                    $player->setNoClientPredictions(false);

                    AwaitUtils::waitPlayerSpawned($player, yield);
                    yield Await::ONCE;

                    IslandForm::sendWelcomeForm($player);
                } else {
                    $player->setGamemode(GameMode::ADVENTURE());
                    $player->setNoClientPredictions(false);
                }
            }
        }, null, Database::getFailClosure());
    }

    private function claimVoterKeys(Player $player): Generator
    {
        $playerData = $this->getPlugin()->getPlayerData();
        $ngPlayerData = NGEssentials::getInstance()->getPlayerData();

        if ((time() - $ngPlayerData->getInt($player, NGPlayerData::VOTE_TIME)) >= (60 * 60 * 24)) {
            return;
        }

        $playerData->loadValue($player->getName(), PlayerData::EXTRA_DATA, yield);
        $extraData = yield Await::ONCE;

        if (!$player->isConnected()) {
            return;
        }

        $lastVoted = time() - (24 * 60 * 60);
        if (isset($extraData['lastVoted'])) {
            $lastVoted = $extraData['lastVoted'];
        }

        if (time() >= $lastVoted) {
            $player->sendMessage(MMOPlugin::getPrefix() . "§aThanks for voting for §eNether§6Games§a! Claiming your vote rewards now.");

            $streak = $extraData['voteStreak'] ?? 0;
            $overdue = time() - 24 * 60 * 60 - $lastVoted;

            if ($overdue >= 24 * 60 * 60) {
                $streak = $extraData['voteStreak'] = 1;

                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You have lost your $streak vote streak.");
            } else {
                $streak = $extraData['voteStreak'] = $streak + 1;

                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::YELLOW . "You are now on $streak vote streaks!");
            }

            $extraData['lastVoted'] = time() + 86400;

            if ($streak > 10) {
                $streak = 10;
            }

            $this->getPlugin()->getPlayerData()->increaseKey($player, CrateManager::VOTE, $streak);

            if ($streak > 1) {
                $reward = $streak . 'x Voter Keys';
            } else {
                $reward = $streak . 'x Voter Key';
            }

            $player->sendMessage(MMOPlugin::getPrefix() . "§aYou have received §e" . $reward . '§a. Claim your key' . ($streak > 1 ? 's' : '') . ' in our §eVoter Crate.');

            $playerData->setValue($player, PlayerData::EXTRA_DATA, $extraData, true);
            if (!$playerData->getBool($player, PlayerData::DATA_LOADED)) {
                $playerData->saveValue($player->getName(), PlayerData::EXTRA_DATA, yield);
                yield Await::ONCE;
            }
        }
    }

    /**
     * @return SkyBlock
     */
    public function getPlugin(): MMOPlugin
    {
        /** @phpstan-ignore-next-line */
        return parent::getPlugin();
    }

    public function transferPlayer(Player $player, string $gameType, ?string $serverUniqueId = ''): void
    {
        $serverUniqueId = $serverUniqueId ?? '';

        /** @var MMOPlayer $player */
        if ($player->isCombatTimerActive()) {
            $player->sendMessage(TextFormat::RED . "You can't transfer to another server while combat tagged.");
            return;
        }

        Await::f2c(function () use ($player, $gameType, $serverUniqueId): Generator {
            AwaitUtils::waitPlayerSpawned($player, yield);
            yield Await::ONCE;

            /** @var NGEssentials $ess */
            $ess = $this->getPlugin()->getEssentials();

            $ignoreFull = $gameType === ServerManager::GAME_TYPE_AGORA || !$this->getPlugin()->isAgora();
            if (!empty($serverUniqueId)) {
                $server = $ess->getServerManager()->getServer($serverUniqueId);
                if ($server === null) {
                    $player->sendMessage(TextFormat::RED . "$serverUniqueId is currently offline");
                } else {
                    $ess->getPlayerManager()->transferPlayer($player, $server, ignoreFull: $ignoreFull);
                }
            } else {
                $ess->getPlayerManager()->transferPlayer($player, $ess->getServerManager()->getServerType(), $gameType, ignoreFull: $ignoreFull);
            }
        });
    }

    public function canFly(Player $player, ?World $world = null): bool
    {
        /** @var MMOPlayer $player */
        if ($world === null) {
            $world = $player->getWorld();
        }

        return !$player->isCombatTimerActive() && !in_array($world->getFolderName(), SkyBlockCommand::BLOCKED_LEVELS) && !str_starts_with($world->getFolderName(), 'IslandUpgrade-');
    }

    public function sendScoreboard(Player $player): void
    {
        Await::f2c(function () use ($player): Generator {
            AwaitUtils::waitPlayerSpawned($player, yield);
            yield Await::ONCE;

            $this->getPlugin()->getPlayerData()->loadValue($player->getName(), PlayerData::BANK_MONEY, yield);
            $value = yield Await::ONCE;

            if (!$player->isConnected()) {
                return;
            }

            $scoreboard = $this->getPlugin()->getEssentials()->getServerData()->getScoreBoard();
            $scoreboard->addPlayer($player);

            $activeChallenges = $this->getPlugin()->getPlayerChallengeManager()->getActiveChallenges($player);
            $challenge = $activeChallenges[0] ?? null;
            if ($challenge !== null && $challenge->getChallenge()->isDailyChallenge() && count($activeChallenges) > 1) {
                $challenge = $activeChallenges[1] ?? null;
            }

            $scoreboard->setLines([$player],
                [
                    1 => CustomIcon::NETHERGAMES . TextFormat::GOLD . 'ngmc.co',
                    2 => '',
                    3 => CustomIcon::PLAYERS_TINY . 'Players: ' . TextFormat::GREEN . $this->getPlugin()->getEssentials()->getServerManager()->getServer()->getCluster()->getOnlinePlayers(),
                    4 => '',
                    5 => ($challenge === null ? TextFormat::YELLOW . 'Use /ch' : TextFormat::WHITE . '(' . TextFormat::YELLOW . array_sum($challenge->getProgress()) . TextFormat::GRAY . '/' . TextFormat::GREEN . $challenge->getAllGoals() . TextFormat::WHITE . ')'),
                    6 => ($challenge === null ? TextFormat::RED . 'None started' : TextFormat::YELLOW . $challenge->getChallenge()->getName()),
                    7 => 'Challenge:',
                    8 => '',
                    9 => 'Purse: ' . TextFormat::GREEN . '$' . number_format($this->getPlugin()->getPlayerData()->getInt($player, PlayerData::PLAYER_MONEY)),
                    10 => 'Bank: ' . TextFormat::GREEN . '$' . number_format($value),
                    11 => ''
                ]);
        });
    }

    public function updateMoneyScoreboard(Player $player): void
    {
        $scoreboard = $this->getPlugin()->getEssentials()->getServerData()->getScoreBoard();
        $scoreboard->setLine([$player], 9, 'Purse: ' . TextFormat::GREEN . '$' . number_format($this->getPlugin()->getPlayerData()->getInt($player, PlayerData::PLAYER_MONEY)));
    }

    public function updateBankScoreboard(Player $player, int $amount): void
    {
        $scoreboard = $this->getPlugin()->getEssentials()->getServerData()->getScoreBoard();
        $scoreboard->setLine([$player], 10, 'Bank: ' . TextFormat::GREEN . '$' . number_format($amount));
    }

    public function updateChallengeScoreboard(Player $player): void
    {
        $scoreboard = $this->getPlugin()->getEssentials()->getServerData()->getScoreBoard();

        $activeChallenges = $this->getPlugin()->getPlayerChallengeManager()->getActiveChallenges($player);
        $challenge = $activeChallenges[0] ?? null;
        if ($challenge !== null && $challenge->getChallenge()->isDailyChallenge() && count($activeChallenges) > 1) {
            $challenge = $activeChallenges[1] ?? null;
        }

        $scoreboard->setLines([$player], [
            5 => ($challenge === null ? TextFormat::YELLOW . 'Use /ch' : TextFormat::WHITE . '(' . TextFormat::YELLOW . array_sum($challenge->getProgress()) . TextFormat::GRAY . '/' . TextFormat::GREEN . $challenge->getAllGoals() . TextFormat::WHITE . ')'),
            6 => ($challenge === null ? TextFormat::RED . 'None started' : TextFormat::YELLOW . $challenge->getChallenge()->getName()),
        ]);
    }

    public function updateBountyScoreboard(string $playerName, int $bounty): void
    {
        // NOOP
    }

    private function processMiniHelpers(Player $player, ?Closure $onComplete = null): void
    {
        Await::f2c(function () use ($player): Generator {
            Database::executeSelect(Database::GET_HELPERS_REMAINDER, ['xuid' => $player->getXuid()], yield, yield Await::REJECT);
            $rows = yield Await::ONCE;

            if (count($rows) > 0) {
                $row = $rows[0];

                $lHelper = $row["lumberjack_remainder"];
                $mHelper = $row["miner_remainder"];
                $hHelper = $row["harvester_remainder"];

                if ($lHelper <= 0 && $mHelper <= 0 && $hHelper <= 0) {
                    return;
                }

                $products = yield from $this->processHelpers($player, $lHelper, $mHelper, $hHelper);

                if ($products === null) {
                    return;
                }

                Database::executeInsert(Database::SET_HELPERS_REMAINDER, [
                    'xuid' => $player->getXuid(),
                    'lumberjack_helper' => $lHelper - $products[MiniHelper::LUMBERJACK],
                    'miner_helper' => $mHelper - $products[MiniHelper::MINER],
                    'harvester_helper' => $hHelper - $products[MiniHelper::HARVESTER]
                ], yield, yield Await::REJECT);
                yield Await::ONCE;

                if ($player->isConnected()) {
                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . "You have received the items you have purchased.");
                }
            }
        }, $onComplete);
    }

    private function processHelpers(Player $player, int $lHelper, int $mHelper, int $hHelper): Generator
    {
        $ngPlayerData = $this->getPlugin()->getEssentials()->getPlayerData();

        $products = [
            MiniHelper::LUMBERJACK => $lHelper,
            MiniHelper::MINER => $mHelper,
            MiniHelper::HARVESTER => $hHelper,
        ];

        /** @var Item[] $items */
        $items = [];
        while ($lHelper > 0) {
            $miniHelper = CustomItemManager::getMiniHelper(MiniHelper::LUMBERJACK);

            ItemStorage::createValidationId($miniHelper, 'join-' . $player->getName(), yield);

            $items[] = yield Await::ONCE;

            if (!$player->isConnected() || $ngPlayerData->getBool($player, NGPlayerData::TRANSFER)) {
                goto skipCondition;
            }

            $lHelper--;
        }

        while ($mHelper > 0) {
            $miniHelper = CustomItemManager::getMiniHelper(MiniHelper::MINER);

            ItemStorage::createValidationId($miniHelper, 'join-' . $player->getName(), yield);

            $items[] = yield Await::ONCE;

            if (!$player->isConnected() || $ngPlayerData->getBool($player, NGPlayerData::TRANSFER)) {
                goto skipCondition;
            }

            $mHelper--;
        }

        while ($hHelper > 0) {
            $miniHelper = CustomItemManager::getMiniHelper(MiniHelper::HARVESTER);

            ItemStorage::createValidationId($miniHelper, 'join-' . $player->getName(), yield);

            $items[] = yield Await::ONCE;

            if (!$player->isConnected() || $ngPlayerData->getBool($player, NGPlayerData::TRANSFER)) {
                goto skipCondition;
            }

            $hHelper--;
        }

        skipCondition:
        if (empty($items)) {
            return null; // Empty?
        }

        $isConnected = $player->isConnected() && !$ngPlayerData->getBool($player, NGPlayerData::TRANSFER);

        $residue = [];
        foreach ($items as $item) {
            if ($isConnected) {
                $leftovers = $player->getInventory()->addItem($item);

                if (count($leftovers) > 0) {
                    $residue = array_merge($residue, $leftovers);
                } else {
                    $data = $item->getCustomBlockData();

                    $type = $data->getInt(MiniHelperItem::JOB_TYPE);
                    $products[$type]--;
                }
            } else {
                ItemStorage::removeValidationId($item, yield);
                yield Await::ONCE;
            }
        }

        // Not connected? Discard all changes.
        if (!$isConnected) {
            return null;
        }

        if (!empty($residue)) {
            $player->sendMessage(SkyBlock::getPrefix() . TextFormat::RED . "Your inventory is full, please rejoin the game with an empty inventory to receive all mini helpers you bought.");

            foreach ($residue as $item) {
                ItemStorage::removeValidationId($item);
            }
        }

        return $products;
    }
}