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

use factions\faction\object\Faction;
use factions\Factions;
use factions\player\MMOPlayer;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\player\permissions\RankManager;
use NetherGames\NGEssentials\player\PlayerData;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class GroupManager
{
    public static function updateNameTag(Player $player, bool $loadAll = false): void
    {
        if (!($player instanceof MMOPlayer)) {
            return;
        }

        $playerFactionData = Factions::getInstance()->getPlayerData();

        if ($loadAll) {
            $playerManager = NGEssentials::getInstance()->getPlayerManager();
            $playerData = $playerManager->getPlugin()->getPlayerData();
            $rankManager = NGEssentials::getInstance()->getPlayerManager()->getRankManager();
            $selectedRank = $playerData->getString($player, PlayerData::SELECTED_RANK);
            $ranks = $player->getRankTags();

            $rankTag = '';
            $tierTag = '';
            $color = TextFormat::YELLOW;

            if ($selectedRank === '') {
                if (isset($ranks[0])) {
                    $selectedRank = $rankManager->getNameByTag($ranks[0]);

                    if ($selectedRank !== null) {
                        $rank = $rankManager->getRankByName($selectedRank);

                        if ($rank !== null) {
                            $rankTag = $rank->getTag();
                            $color = $rank->getColor();
                        }
                    }
                }
            } elseif ($selectedRank === RankManager::NO_RANK) {
                if (count($ranks) === 0) {
                    $playerData->setValue($player, PlayerData::SELECTED_RANK, '');
                }
            } else {
                $rank = $rankManager->getRankByName($selectedRank);

                if ($rank !== null && in_array($tag = $rank->getTag(), $ranks, true)) {
                    $rankTag = $tag;
                    $color = $rank->getColor();
                } else {
                    $playerData->setValue($player, PlayerData::SELECTED_RANK, '');
                }
            }

            if (($tier = $rankManager->getTier($player)) !== null) {
                $tierTag = $tier->getTag();
            }

            if ($tierTag !== '') {
                $tierTag .= ' ';
            }

            if ($rankTag !== '') {
                $rankTag .= ' ';
            }

            $tag = TextFormat::GRAY . '[' . $playerFactionData->getCurrentTag($player) . '] ';

            $playerData->setValue($player, PlayerData::RANKTAG, $tierTag . $rankTag . TextFormat::RESET . $tag . TextFormat::RESET . $color . $player->getName());

            $nametag = $playerManager->getNameTag($player, TextFormat::YELLOW);
            $player->setNameTag($player->getAllowFlight() ? TextFormat::GREEN . '• ' . TextFormat::RESET . $nametag : $nametag);

            $displayName = $playerManager->getPlayerColouredName($player, TextFormat::YELLOW);
            $player->setDisplayName($displayName);
        }

        $kills = TextFormat::BOLD . TextFormat::GOLD . $playerFactionData->getKills($player) . TextFormat::RESET . ' ';

        $factionTag = '';
        if (($faction = $playerFactionData->getFaction($player)) !== null) {
            $factionPrefix = match ($faction->getFactionRole($player)) {
                Faction::MEMBER => $faction->getFactionName(),
                Faction::OFFICER => '*' . $faction->getFactionName(),
                Faction::LEADER => '**' . $faction->getFactionName(),
                default => ''
            };

            $factionTag .= TextFormat::DARK_AQUA . $factionPrefix . ' ';
        }

        $nametag = $kills . $factionTag . NGEssentials::getInstance()->getPlayerManager()->getNameTag($player, TextFormat::YELLOW, true, true);
        $player->setNameTag($player->getAllowFlight() ? TextFormat::GREEN . '• ' . TextFormat::RESET . $nametag : $nametag);
    }
}