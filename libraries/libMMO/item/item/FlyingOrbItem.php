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
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder
 */

namespace libMMO\item\item;

use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use pocketmine\item\Item;
use pocketmine\item\ItemUseResult;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class FlyingOrbItem extends Item
{
    use ReusableInteractTrait;

    public function onUse(Player $player): ItemUseResult
    {
        if (!($player instanceof MMOPlayer)) {
            return ItemUseResult::FAIL;
        }

        if ($player->getAllowFlight()) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Your ability to fly has already enabled.');
            return ItemUseResult::FAIL;
        }

        if ($player->isCombatTimerActive()) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You are not allowed to use the Orb of Flight while in combat.");
            return ItemUseResult::FAIL;
        }

        if (!MMOPlugin::getInstance()->getItemManager()->canFly($player)) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You cannot use the Orb of Flight in this area.");
            return ItemUseResult::FAIL;
        }

        $this->pop();

        $player->setAllowFlight(true);
        $player->addAttachment(MMOPlugin::getInstance(), 'nethergames.flight.orb', true);

        $flightMode = TextFormat::GREEN . '• ' . TextFormat::RESET;

        $player->setNameTag($flightMode . str_replace($flightMode, '', $player->getNameTag()));
        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . 'Your ability to fly has been enabled. It will be disabled when you attempt to fight, when you disconnect from the server or when the server restarts.');

        return ItemUseResult::SUCCESS;
    }
}