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

namespace factions\player;

use factions\faction\object\Faction;
use factions\Factions;
use factions\player\tags\TagManager;
use factions\utils\Area;
use factions\utils\Database;
use factions\utils\GroupManager;
use Generator;
use JsonException;
use libMMO\MMOPlugin;
use libMMO\utils\AwaitUtils;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\player\PlayerData as NGPlayerData;
use NetherGames\NGEssentials\ServerManager;
use NetherGames\NGEssentials\utils\CustomIcon;
use pocketmine\math\Vector3;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\world\World;
use SOFe\AwaitGenerator\Await;

class PlayerManager extends \libMMO\player\PlayerManager
{
    public const PERMISSION_MODIFY = "factions.modify";

    /**
     * @throws JsonException
     */
    public function setupPlayer(Player $player, bool $newPlayer = false): void
    {
        $plugin = NGEssentials::getInstance();
        $enforcementHandler = $plugin->getPlayerManager()->getEnforcementHandler();
        $isTracking = $plugin->getPlayerData()->getBool($player, NGPlayerData::TRACK);
        $playerData = $this->getPlugin()->getPlayerData();

        if (!$player instanceof MMOPlayer) {
            return;
        }

        if ($isTracking && !$player->hasPermission("nethergames.trainee")) {
            $enforcementHandler->setTracking($player, false, false);

            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You cannot track players in Factions.");
        } else if ($isTracking) {
            $player->enableHud(false);
        } elseif ($playerData->isHudEnabled($player)) {
            $player->showCoordinates();
        }

        parent::setupPlayer($player, $newPlayer); // Performs callback call to check armours, load inventory and others.

        if ($newPlayer) {
            $kitManager = $this->getPlugin()->getKitManager();
            $kitManager->redeemKit($player, $kitManager->getKit('Superior Starter'));

            $playerData->setValue($player, PlayerData::REGISTER_DATE, time());
            $playerData->loadValue($player->getName(), PlayerData::REGISTER_DATE, function (int $unixTimestamp) use ($player, $playerData): void {
                if (!$player->isConnected()) {
                    return;
                }

                $playerData->setValue($player, PlayerData::REGISTER_DATE, $unixTimestamp);
            });
            $playerData->setValue($player, PlayerData::PLAYER_MONEY, 5000);
            $playerData->saveData($player);
        } else {
            $tags = TagManager::flagsToTags($playerData->getInt($player, PlayerData::TAGS));
            $playerData->setValue($player, PlayerData::RUNTIME_TAGS, $tags);
        }

        if ($player->hasPermission('nethergames.developer')) {
            $player->getPermissionAttachment()->setPermission(DefaultPermissionNames::COMMAND_STATUS, true);
            $player->getPermissionAttachment()->setPermission(DefaultPermissionNames::COMMAND_GC, true);
        }

        GroupManager::updateNameTag($player, true);

        $oldPlayerName = $this->getPlugin()->getPlayerData()->getString($player, PlayerData::OLD_PLAYER_NAME);
        $this->getPlugin()->getFactionManager()->loadFactionByPlayer($player, function (?Faction $faction) use ($player, $oldPlayerName): void {
            $player->setHealthTag();
            $player->setNoClientPredictions(false);

            if ($faction === null) {
                return;
            }

            $this->getPlugin()->getPlayerManager()->updateFactionScoreboard($player);

            GroupManager::updateNameTag($player, true);

            // Player changed their in-game name, update appropriately.
            if (!empty($oldPlayerName)) {
                $faction->updatePlayerName($player->getName(), $oldPlayerName, true);

                $this->getPlugin()->getPlayerData()->unsetValue($player, PlayerData::OLD_PLAYER_NAME);
            }

            // TODO: Notify faction members about the player joining the server.
        });

        $worldManager = $this->getPlugin()->getServer()->getWorldManager();
        if (Factions::isBadlands()) {
            if (!$isTracking) {
                $player->setGamemode(GameMode::ADVENTURE());
            }
        } else {
            if (!$isTracking) {
                $player->setGamemode(GameMode::SURVIVAL());
            }

            if (!empty($teleport = $playerData->getString($player, PlayerData::TRANSFER_LOCATION))) {
                $teleportData = json_decode($teleport, true, 512, JSON_THROW_ON_ERROR);

                switch ($teleportData[0]) {
                    case "faction":
                        $cord = explode(":", $teleportData[1]);

                        $x = (int)$cord[0];
                        $y = (int)$cord[1];
                        $z = (int)$cord[2];
                        $world = $worldManager->getWorldByName($cord[3]);
                        if ($world === null || !$world->isLoaded()) {
                            break;
                        }
                        $pos = new Position($x, $y, $z, $world);

                        $world->requestChunkPopulation($x >> 4, $z >> 4, null)->onCompletion(static function () use ($player, $pos): void {
                            $player->teleport($pos);
                        }, static function (): void {});
                        break;
                    case "home_private":
                        [$x, $y, $z, $serverRegion] = $teleportData[1];

                        if ($this->getPlugin()->getEssentials()->getServerManager()->getServerRegion() !== $serverRegion) {
                            $player->sendMessage(Factions::getPrefix() . TextFormat::RED . 'You were teleported to the wrong server, how could this happen?');
                            return;
                        }

                        $pos = Position::fromObject(new Vector3((int)$x, (int)$y, (int)$z), $worldManager->getWorldByName('wild'));
                        $pos->getWorld()->requestChunkPopulation($pos->getFloorX() >> 4, $pos->getFloorZ() >> 4, null)->onCompletion(static function () use ($player, $pos): void {
                            $player->teleport($pos);
                        }, static function (): void {});
                        break;
                }

                $playerData->unsetValue($player, PlayerData::TRANSFER_LOCATION);
            } else {
                $region = NGEssentials::getInstance()->getServerManager()->getServerRegion();

                $player->sendMessage(TextFormat::EOL . '§bWelcome to §eNether§6Games §cFactions§a.');
                $player->sendMessage('§6Start your journey by using the §d/wild §6command.');

                $player->sendMessage('§eYou are currently located in the ' . $region . ' region.');
            }
        }
    }

    public function transferPlayer(Player $player, string $gameType = ServerManager::GAME_TYPE_FARLANDS, ?string $serverRegion = ServerManager::REGION_EU): void
    {
        $serverRegion = $serverRegion ?? ServerManager::REGION_EU;

        if (!in_array($serverRegion, [ServerManager::REGION_EU, ServerManager::REGION_US, ServerManager::REGION_AP])) {
            return;
        }

        /** @var MMOPlayer $player */
        if ($player->isCombatTimerActive()) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't transfer to another server while combat tagged.");
            return;
        }

        Await::f2c(function () use ($player, $gameType, $serverRegion): Generator {
            AwaitUtils::waitPlayerSpawned($player, yield);
            yield Await::ONCE;

            $manager = Factions::getInstance()->getRegionManager();

            $gameClusters = $manager->getRegions()[$gameType] ?? null;
            if ($gameClusters !== null) {
                $server = $gameClusters[$serverRegion];

                if ($server === null) {
                    $player->sendMessage(Factions::getPrefix() . TextFormat::RED . 'The server you are teleporting into is currently offline.');
                } else {
                    NGEssentials::getInstance()->getPlayerManager()->forceTransfer($player, $server);
                }
            }
        });
    }

    /**
     * @return Factions
     */
    public function getPlugin(): MMOPlugin
    {
        /** @var Factions $plugin */
        $plugin = parent::getPlugin();

        return $plugin;
    }

    public function updateKillsScoreboard(Player $player): void
    {
        $playerData = $this->getPlugin()->getPlayerData();

        $bestStreak = $playerData->getInt($player->getName(), PlayerData::BEST_STREAK);
        $killStreak = $playerData->getInt($player->getName(), PlayerData::KILL_STREAK);
        $kills = $playerData->getInt($player->getName(), PlayerData::KILLS);

        if ($player->isConnected() && $playerData->isHudEnabled($player)) {
            $scoreboard = $this->getPlugin()->getEssentials()->getServerData()->getScoreBoard();
            $scoreboard->setLines([$player], [
                8 => 'Best Streak: ' . TextFormat::GREEN . $bestStreak,
                9 => 'Streak: ' . TextFormat::GREEN . $killStreak,
                10 => 'Kills: ' . TextFormat::GREEN . $kills,
            ]);
        }
    }

    public function sendScoreboard(Player $player): void
    {
        Await::f2c(function () use ($player) {
            $playerData = $this->getPlugin()->getPlayerData();

            $playerData->loadValue($player->getName(), PlayerData::PLAYER_MONEY, yield);
            $playerData->loadValue($player->getName(), PlayerData::BOUNTY, yield);

            AwaitUtils::waitPlayerSpawned($player, yield);

            [$balance, $bounty] = yield Await::ALL;

            if ($player->isConnected() && $playerData->isHudEnabled($player)) {
                $scoreboard = $this->getPlugin()->getEssentials()->getServerData()->getScoreBoard();
                $scoreboard->addPlayer($player);

                $bestStreak = $playerData->getInt($player->getName(), PlayerData::BEST_STREAK);
                $killStreak = $playerData->getInt($player->getName(), PlayerData::KILL_STREAK);
                $kills = $playerData->getInt($player->getName(), PlayerData::KILLS);

                $scoreboard->setLines([$player], [
                    1 => CustomIcon::NETHERGAMES . TextFormat::GOLD . 'nethergames.org',
                    2 => '',
                    3 => 'Players: ' . TextFormat::GREEN . $this->getPlugin()->getEssentials()->getServerManager()->getServer()->getCluster()->getOnlinePlayers(),
                    4 => '',
                    5 => 'Bounty: ' . TextFormat::GREEN . '$' . number_format($bounty),
                    6 => 'Balance: ' . TextFormat::GREEN . '$' . number_format($balance),
                    7 => '',
                    8 => 'Best Streak: ' . TextFormat::GREEN . $bestStreak,
                    9 => 'Streak: ' . TextFormat::GREEN . $killStreak,
                    10 => 'Kills: ' . TextFormat::GREEN . $kills,
                    11 => '',
                ]);

                $this->updateFactionScoreboard($player);
            }
        });
    }

    public function canFly(Player $player, ?World $world = null): bool
    {
        return $player->getWorld()->getFolderName() === 'wild' || !Area::inPvpArea($player);
    }

    public function updateMoneyScoreboard(Player $player): void
    {
        $playerData = $this->getPlugin()->getPlayerData();

        if ($playerData->isHudEnabled($player)) {
            $scoreboard = $this->getPlugin()->getEssentials()->getServerData()->getScoreBoard();
            $scoreboard->setLine([$player], 6, 'Balance: ' . TextFormat::GREEN . '$' . number_format($this->getPlugin()->getPlayerData()->getInt($player, PlayerData::PLAYER_MONEY)));
        }
    }

    public function updateFactionScoreboard(Player $player): void
    {
        $faction = $this->getPlugin()->getPlayerData()->getFaction($player);
        if ($faction === null) {
            return;
        }

        $playerData = $this->getPlugin()->getPlayerData();
        if ($player->isConnected() && $playerData->isHudEnabled($player)) {
            $scoreboard = $this->getPlugin()->getEssentials()->getServerData()->getScoreBoard();
            $scoreboard->setLines([$player], [
                12 => 'Strength: ' . TextFormat::GREEN . $faction->getStrength(),
                13 => 'Leader: ' . TextFormat::GREEN . $faction->getLeader(),
                14 => 'Faction: ' . TextFormat::GREEN . $faction->getFactionName(),
                15 => '',
            ]);
        }
    }

    /**
     * @param Faction $faction
     */
    public function updateStrengthScoreboard(Faction $faction): void
    {
        $playerData = $this->getPlugin()->getPlayerData();

        foreach ($faction->getMembers() as $member) {
            $player = Server::getInstance()->getPlayerExact($member);

            if ($player !== null && $player->isConnected() && $playerData->isHudEnabled($player)) {
                $scoreboard = $this->getPlugin()->getEssentials()->getServerData()->getScoreBoard();

                $scoreboard->setLine([$player], 12, 'Strength: ' . TextFormat::GREEN . $faction->getStrength());
            }
        }
    }

    public function updateBountyScoreboard(string $playerName, int $bounty): void
    {
        $player = Server::getInstance()->getPlayerExact($playerName);
        $playerData = $this->getPlugin()->getPlayerData();

        if ($player !== null && $player->isConnected() && $playerData->isHudEnabled($player)) {
            $scoreboard = $this->getPlugin()->getEssentials()->getServerData()->getScoreBoard();
            $scoreboard->setLines([$player], [
                5 => 'Bounty: ' . TextFormat::GREEN . '$' . number_format($bounty),
            ]);
        }
    }

    public function updateBankScoreboard(Player $player, int $amount): void
    {
        // NOOP
    }

    public function updateChallengeScoreboard(Player $player): void
    {
        // NOOP
    }
}