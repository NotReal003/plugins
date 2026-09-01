<?php
/**
 *        ______         _   _
 *       |  ____|       | | (_)
 *  __  _| |__ __ _  ___| |_ _  ___  _ __  ___
 *  \ \/ /  __/ _` |/ __| __| |/ _ \| '_ \/ __|
 *   >  <| | | (_| | (__| |_| | (_) | | | \__ \
 *  /_/\_\_|  \__,_|\___|\__|_|\___/|_| |_|___/
 *
 * Copyright (C) 2016-2022 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author larryTheCoder
 */

declare(strict_types=1);

namespace skyblock\commands;

use libMMO\player\enchantment\EnchantmentManager;
use libMMO\player\MMOPlayer;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\VanillaItems;
use skyblock\SkyBlock;

class TestCommand extends BaseCommand
{
    public function __construct(SkyBlock $plugin)
    {
        parent::__construct('test', $plugin);
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        if (!in_array($sender->getName(), ['larryZ00p', 'larryZ00'])) {
            $sender->sendMessage("This command is reserved for SkyBlock developers.");
            return false;
        }

        $sword = VanillaItems::DIAMOND_SWORD();

        $sword->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 5));
        $sword->addEnchantment(new EnchantmentInstance(VanillaEnchantments::MENDING(), 1));

        $sword->addEnchantment(new EnchantmentInstance(EnchantmentManager::REPLENISH(), 1));
        $sword->addEnchantment(new EnchantmentInstance(EnchantmentManager::ESCAPE(), 1));
        $sword->addEnchantment(new EnchantmentInstance(EnchantmentManager::SWIPE(), 5));
        $sword->addEnchantment(new EnchantmentInstance(EnchantmentManager::KILL_AURA(), 4));

        $sender->getInventory()->addItem($sword);

        return true;
    }
}