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

namespace factions\utils\chat;


use factions\faction\object\OfflineFaction;
use factions\Factions;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\player\chat\types\ChatType;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use function array_map;

class AllyChat extends ChatType
{
    /** @var Factions */
    private Factions $plugin;

    public function __construct(Factions $faction)
    {
        parent::__construct("Ally Chat");

        $this->plugin = $faction;
    }

    public function canBeUsed(Player $player): bool
    {
        return $this->plugin->getPlayerData()->getFaction($player) !== null;
    }

    public function broadcast(Player $player, string $message): void
    {
        if (($faction = $this->plugin->getPlayerData()->getFaction($player)) === null) {
            return;
        }

        $socialManager = NGEssentials::getInstance()->getPlayerManager()->getSocialManager();
        $playerManager = $socialManager->getManager();

        $realPlayerName = $playerManager->getPlayerColouredName($player, TextFormat::GRAY, true);
        $factionManager = Factions::getInstance()->getFactionManager();

        $targets = []; // Currently online on the server.
        foreach ($faction->getAllies() as $ally) {
            if (($allyFaction = $factionManager->getFaction($ally->getFactionId())) === null) {
                continue;
            }

            foreach ($allyFaction->getMembers() as $member) {
                $target = Server::getInstance()->getPlayerExact($member);

                if ($target !== null) {
                    $targets[] = $target;
                }
            }
        }

        foreach ($faction->getMembers() as $member) {
            $target = Server::getInstance()->getPlayerExact($member);

            if ($target !== null) {
                $targets[] = $target;
            }
        }

        $this->sendEntry($player, $message, 'faction_ally', [
            'faction_id' => $faction->getFactionId(),
            'receiving_factions' => [
                $faction->getFactionId(),
                ...array_map(fn(OfflineFaction $ally) => $ally->getFactionId(), $faction->getAllies())
            ]
        ]);

        $msg = TextFormat::GREEN . 'Ally > ' . $realPlayerName . '§r: ' . $message;

        $this->plugin->getServer()->broadcastMessage($msg, $targets);
        $this->plugin->getEventEmitter()->broadcastChatMessages($msg, $faction->getFactionId(), true);

        NGEssentials::getInstance()->getPlayerManager()->getEnforcementHandler()->sendRelayMessage('§dALLY CHAT RELAY §7> ' . $realPlayerName . '§r: ' . $message, $targets);
    }
}