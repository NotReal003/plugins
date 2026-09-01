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

namespace libMMO\commands;

use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use libMMO\player\PlayerData;
use libMMO\vaults\VaultEntry;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\utils\TextFormat;

class PrivateVaults extends BaseCommand
{
    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct('privatevault', $plugin);

        $this->setAliases(['pv']);
        $this->setDescription('Open your private vaults.');
        $this->setUsage(TextFormat::RED . '/pv <number>');
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        if ($sender->isCombatTimerActive()) {
            $sender->sendMessage(TextFormat::RED . "You can't open Private Vaults while you are in combat.");
        } else if (isset($args[0])) {
            $number = (int)$args[0];
            if ($number <= 0) {
                throw new InvalidCommandSyntaxException();
            } else if ($number > 1 && !$sender->hasPermission('nethergames.vip.ultra')) {
                $sender->sendMessage("§cYou don't have permission to open more than 1 vaults. Buy a rank at §bngmc.co/store §cto open more vaults!");
            } else if ($number > 2 && !$sender->hasPermission('nethergames.vip.emerald')) {
                $sender->sendMessage("§cYou don't have permission to open more than 2 vaults. Buy the §l§aEMERALD§r §cor §l§bLEGEND§r§c rank at §bngmc.co/store §cto open more vaults!");
            } else if ($number > 3 && !$sender->hasPermission('nethergames.vip.legend')) {
                $sender->sendMessage("§cYou don't have permission to open more than 3 vaults. Buy §l§bLEGEND§r§c rank at §bngmc.co/store §cto open more vaults!");
            } else if ($number > 4 && !$sender->hasPermission('nethergames.vip.titan')) { // §l§cTITAN§r
                $sender->sendMessage("§cYou don't have permission to open more than 4 vaults. Buy §l§cTITAN§r §c rank at §bngmc.co/store §cto open more vaults!");
            } else if ($number > 5) {
                $sender->sendMessage(TextFormat::RED . 'You have exceeded the amount of Private Vaults you can access.');
            } else {
                $vaultNumber = max($number, 1);

                /** @var VaultEntry[] $menu */
                $menu = MMOPlugin::getInstance()->getPlayerData()->getValue($sender, PlayerData::RUNTIME_PRIVATE_VAULTS);

                if (!isset($menu[$vaultNumber - 1])) {
                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Something went wrong while trying to open your vaults");
                } else {
                    $menu[$vaultNumber - 1]->getInvMenu()->send($sender);
                }
            }
        } else {
            throw new InvalidCommandSyntaxException();
        }
        return true;
    }
}