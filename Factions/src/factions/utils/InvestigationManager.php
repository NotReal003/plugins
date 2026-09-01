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

namespace factions\utils;

use DateTime;
use factions\faction\object\Faction;
use factions\Factions;
use factions\player\PlayerData;
use libforms\elements\Button;
use libforms\elements\Label;
use libforms\FormManager;
use libforms\SimpleForm;
use libMMO\MMOPlugin;
use pocketmine\entity\utils\ExperienceUtils;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use SOFe\AwaitGenerator\Await;

class InvestigationManager extends \libMMO\utils\InvestigationManager
{

    protected function addButtons(SimpleForm $form, string $fullPlayerName): void
    {
        $form->addButton(new Button(TextFormat::DARK_GRAY . 'Open player homes' . TextFormat::EOL . TextFormat::DARK_AQUA . 'Show homes coordinates', function (Player $player) use ($fullPlayerName): void {
            $this->openHomeCoordinates($player, $fullPlayerName);
        }));

        $form->addButton(new Button(TextFormat::DARK_GRAY . 'Open faction info' . TextFormat::EOL . TextFormat::DARK_AQUA . 'Open factions information', function (Player $player) use ($fullPlayerName): void {
            $this->openFactionsInfo($player, $fullPlayerName);
        }));

        parent::addButtons($form, $fullPlayerName); // For "load death history"
    }

    protected function resetProgress(Player $staff, string $playerName): void
    {
        Await::f2c(function () use ($playerName, $staff) {
            Database::executeGenericRaw("DELETE FROM player_data WHERE player = ?", [$playerName], yield);

            yield Await::ONCE;

            $staff->sendMessage(Factions::getPrefix() . TextFormat::RED . "Player stats has been reset.");
        }, catches: Database::getFailClosure());
    }

    private function openHomeCoordinates(Player $player, string $playerName, int $pageNumber = 1): void
    {
        Await::f2c(function () use ($player, $playerName, $pageNumber) {
            $plugin = $this->getPlugin();
            $playerData = $plugin->getPlayerData();

            $targetPlayer = $this->getPlugin()->getServer()->getPlayerExact($playerName);
            if ($targetPlayer === null || !$targetPlayer->isConnected()) {
                $playerData->loadValue($playerName, PlayerData::HOME_COORDINATES, yield);

                $homeCoordinates = yield Await::ONCE;
            } else {
                $homeCoordinates = $playerData->getArray($playerName, PlayerData::HOME_COORDINATES);
            }

            if (empty($homeCoordinates)) {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'The player has no homes set');
                return;
            }

            $moreEntries = count($homeCoordinates) > 10;

            $totalPages = 1;
            if ($moreEntries) {
                $items = array_chunk($homeCoordinates, 10);
                $pageNumber = min($totalPages = count($items), $pageNumber);
                if ($pageNumber < 1) {
                    $pageNumber = 1;
                }

                $homeCoordinates = $items[$pageNumber - 1];
            }

            $form = FormManager::createSimpleForm($player);
            if ($form !== null) {
                $form->setCloseClosure(function (Player $player) use ($playerName) {
                    $this->sendBaseForm($player, $playerName);
                });

                $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . 'Player homes');
                $form->setContent(TextFormat::GRAY . 'Listing ' . TextFormat::YELLOW . $playerName . "'s" . TextFormat::GRAY . " homes: " . TextFormat::EOL);
                foreach ($homeCoordinates as $homeName => $coordinate) {
                    $form->addButton(new Button(TextFormat::DARK_GRAY . $homeName . TextFormat::EOL . TextFormat::DARK_AQUA . "[$coordinate[0], $coordinate[1], $coordinate[2], $coordinate[4]]", function (Player $player) use ($homeName, $coordinate): void {
                        $serverRegion = $this->getPlugin()->getEssentials()->getServerManager()->getServerRegion();

                        if (Factions::isBadlands() || $coordinate[4] !== $serverRegion) {
                            $player->sendMessage(MMOPlugin::getPrefix() . 'This is an invalid server, please teleport to Wilderness ' . $coordinate[4]);
                        } else {
                            $this->getPlugin()->getServer()->dispatchCommand($player, 'track "' . $player->getName() . '"');

                            $wild = $this->getPlugin()->getServer()->getWorldManager()->getWorldByName('wild');
                            if ($wild !== null) {
                                $player->teleport(new Position($coordinate[0], $coordinate[1], $coordinate[2], $wild));
                            }

                            $player->sendMessage(MMOPlugin::getPrefix() . 'Teleported you to ' . $homeName);
                        }
                    }));
                }

                if ($totalPages !== 1) {
                    $page = TextFormat::DARK_PURPLE . '[Page ' . TextFormat::DARK_AQUA . $pageNumber . TextFormat::DARK_PURPLE . ' of ' . TextFormat::DARK_AQUA . $totalPages . TextFormat::DARK_PURPLE . ']';
                    if ($pageNumber === 1) {
                        $form->addButton(new Button(TextFormat::DARK_GRAY . 'Next entry' . TextFormat::EOL . $page, function (Player $player) use ($pageNumber, $playerName): void {
                            $this->openHomeCoordinates($player, $playerName, $pageNumber + 1);
                        }));
                    } else {
                        $form->addButton(new Button(TextFormat::DARK_GRAY . 'Previous entry' . TextFormat::EOL . $page, function (Player $player) use ($pageNumber, $playerName): void {
                            $this->openHomeCoordinates($player, $playerName, $pageNumber - 1);
                        }));

                        if ($pageNumber < $totalPages) {
                            $form->addButton(new Button(TextFormat::DARK_GRAY . 'Next entry', function (Player $player) use ($pageNumber, $playerName): void {
                                $this->openHomeCoordinates($player, $playerName, $pageNumber + 1);
                            }));
                        }
                    }
                }

                $form->sendForm();
            }

        });
    }

    protected function openPlayerProgress(Player $player, string $playerName): void
    {
        Await::f2c(function () use ($player, $playerName) {
            $plugin = $this->getPlugin();
            $playerData = $plugin->getPlayerData();

            $targetPlayer = $this->getPlugin()->getServer()->getPlayerExact($playerName);
            if ($targetPlayer === null || !$targetPlayer->isConnected()) {
                $playerData->loadValue($playerName, PlayerData::PLAYER_MONEY, yield);
                $playerData->loadValue($playerName, PlayerData::BOUNTY, yield);
                $playerData->loadValue($playerName, PlayerData::KILL_STREAK, yield);
                $playerData->loadValue($playerName, PlayerData::BEST_STREAK, yield);
                $playerData->loadValue($playerName, PlayerData::HOME_COORDINATES, yield);
                $playerData->loadValue($playerName, PlayerData::REGISTER_DATE, yield);
                $playerData->loadValue($playerName, PlayerData::XP, yield);

                [
                    0 => $playerMoney,
                    1 => $bounty,
                    2 => $killStreak,
                    3 => $bestStreak,
                    4 => $homeCoordinates,
                    5 => $registerDate,
                    6 => $xp,
                ] = yield Await::ALL;
            } else {
                $playerData->loadValue($playerName, PlayerData::BOUNTY, yield);

                $bounty = yield Await::ONCE;
                $playerMoney = $playerData->getInt($playerName, PlayerData::PLAYER_MONEY);
                $killStreak = $playerData->getInt($playerName, PlayerData::KILL_STREAK);
                $bestStreak = $playerData->getInt($playerName, PlayerData::BEST_STREAK);
                $homeCoordinates = $playerData->getArray($playerName, PlayerData::HOME_COORDINATES);
                $registerDate = $playerData->getInt($playerName, PlayerData::REGISTER_DATE);
                $xp = $playerData->getInt($playerName, PlayerData::XP);
            }

            $dateTime = new DateTime();
            $dateTime->setTimestamp($registerDate);

            $form = FormManager::createCustomForm($player, function (Player $player) use ($playerName) {
                $this->sendBaseForm($player, $playerName);
            });

            if ($form !== null) {
                $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . $playerName . "'s info");

                $form->addElement(new Label(TextFormat::GRAY . 'Player: ' . TextFormat::YELLOW . $playerName . TextFormat::EOL .
                    TextFormat::GRAY . 'Xp: ' . TextFormat::YELLOW . $xp . TextFormat::EOL .
                    TextFormat::GRAY . 'Level: ' . TextFormat::YELLOW . round(ExperienceUtils::getLevelFromXp($xp)) . TextFormat::EOL .
                    TextFormat::GRAY . 'Coins: ' . TextFormat::YELLOW . number_format($playerMoney) . ' coins' . TextFormat::EOL .
                    TextFormat::GRAY . 'Bounty: ' . TextFormat::YELLOW . number_format($bounty) . ' coins' . TextFormat::EOL .
                    TextFormat::GRAY . 'Streak: ' . TextFormat::YELLOW . number_format($killStreak) . ' streaks' . TextFormat::EOL .
                    TextFormat::GRAY . 'Best Streak: ' . TextFormat::YELLOW . number_format($bestStreak) . ' streaks' . TextFormat::EOL .
                    TextFormat::GRAY . 'Total Homes set: ' . TextFormat::YELLOW . count($homeCoordinates) . ' homes' . TextFormat::EOL .
                    TextFormat::GRAY . 'Joined since: ' . TextFormat::YELLOW . $dateTime->format('Y-m-d H:i:s')));

                $form->sendForm();
            }
        });
    }

    private function openFactionsInfo(Player $player, string $playerName): void
    {
        $this->getPlugin()->getFactionManager()->loadFactionPlayerOffline($playerName, function (?Faction $faction) use ($player, $playerName): void {
            if (!$player->isConnected()) {
                return;
            }

            if ($faction === null) {
                $player->sendMessage(Factions::getPrefix() . "$playerName has never joined any factions.");
                return;
            }

            $this->getPlugin()->getFactionManager()->sendFactionForm($player, $faction);
        });
    }

    /**
     * @return Factions
     */
    public function getPlugin(): MMOPlugin
    {
        return parent::getPlugin();
    }
}