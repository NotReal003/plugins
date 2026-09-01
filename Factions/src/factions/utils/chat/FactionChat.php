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

use factions\Factions;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\player\chat\types\ChatType;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class FactionChat extends ChatType
{
    /** @var Factions */
    private Factions $plugin;

    public function __construct(Factions $faction)
    {
        parent::__construct("Faction Chat");

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

        $targets = [];
        foreach ($faction->getMembers() as $member) {
            $target = Server::getInstance()->getPlayerExact($member);

            if ($target !== null) {
                $targets[] = $target;
            }
        }

        $this->sendEntry($player, $message, 'faction', [
            'faction_id' => $faction->getFactionId()
        ]);

        $msg = TextFormat::RED . 'Faction > ' . $realPlayerName . '§r: ' . $message;

        $this->plugin->getServer()->broadcastMessage($msg, $targets);
        $this->plugin->getEventEmitter()->broadcastChatMessages($msg, $faction->getFactionId());

        NGEssentials::getInstance()->getPlayerManager()->getEnforcementHandler()->sendRelayMessage('§dFACTION CHAT RELAY §7> ' . $realPlayerName . '§r: ' . $message, $targets);
    }
}