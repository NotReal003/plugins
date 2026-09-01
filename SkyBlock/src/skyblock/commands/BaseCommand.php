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

namespace skyblock\commands;

use libMMO\commands\InvCommand;
use libMMO\commands\RollbackCommand;
use libMMO\commands\TradeCommand;
use libMMO\MMOPlugin;
use NetherGames\NGEssentials\commands\HardTransferCommand;
use NetherGames\NGEssentials\NGEssentials;
use pocketmine\plugin\Plugin;
use skyblock\SkyBlock;

abstract class BaseCommand extends \libMMO\commands\BaseCommand
{
    public static function registerCommands(MMOPlugin $plugin): void
    {
        /** @var SkyBlock $plugin */
        parent::registerCommands($plugin);

        $commandMap = $plugin->getServer()->getCommandMap();

        $commandMap->register(HubCommand::class, new HubCommand($plugin));

        if ($plugin->isAgora()) {
            $commandMap->register(PvPCommand::class, new PvPCommand($plugin));
            $commandMap->register(ArenaCommand::class, new ArenaCommand($plugin));
        }
        $commandMap->register(SkyBlockCommand::class, new SkyBlockCommand($plugin));
        $commandMap->register(LobbyCommand::class, new LobbyCommand($plugin));
        $commandMap->register(InvCommand::class, new InvCommand($plugin));
        $commandMap->register(TopBalance::class, new TopBalance($plugin));
        $commandMap->register(HardTransferCommand::class, new HardTransferCommand(NGEssentials::getInstance()));
        $commandMap->register(ClearEntitiesCommand::class, new ClearEntitiesCommand($plugin));
        $commandMap->register(RollbackCommand::class, new RollbackCommand($plugin));
        $commandMap->register(TradeCommand::class, new TradeCommand($plugin));
        //$commandMap->register(TestCommand::class, new TestCommand($plugin));
    }

    /**
     * @return SkyBlock
     */
    final public function getOwningPlugin(): Plugin
    {
        /** @var SkyBlock $plugin */
        $plugin = parent::getOwningPlugin();

        return $plugin;
    }
}