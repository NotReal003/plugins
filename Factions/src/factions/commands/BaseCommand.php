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

namespace factions\commands;

use factions\Factions;
use factions\player\MMOPlayer;
use libMMO\commands\AuctionHouseCommand;
use libMMO\commands\EnchantListCommand;
use libMMO\commands\DepositCommand;
use libMMO\commands\InvCommand;
use libMMO\commands\KitCommand;
use libMMO\commands\PrivateVaults;
use libMMO\commands\RenameCommand;
use libMMO\commands\RepairCommand;
use libMMO\commands\RollbackCommand;
use libMMO\commands\SellCommand;
use libMMO\commands\ShopCommand;
use libMMO\commands\TpaCommand;
use libMMO\commands\TradeCommand;
use libMMO\MMOPlugin;
use NetherGames\NGEssentials\commands\HardTransferCommand;
use NetherGames\NGEssentials\NGEssentials;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;

abstract class BaseCommand extends \libMMO\commands\BaseCommand
{
    public function __construct(string $name, MMOPlugin $owningPlugin)
    {
        parent::__construct($name, $owningPlugin);

        $this->addValidator(function (CommandSender $sender, string $commandLabel, array $args): bool {
            if (!($sender instanceof MMOPlayer)) {
                $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Only players can execute this command.");
                return false;
            }

            if ($sender->isCombatTimerActive() && !in_array($commandLabel, ['faction', 'f', 'chat', 'c'])) {
                $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You cannot execute any commands while in combat mode.");
            } else if (($koth = $this->getOwningPlugin()->getKoth()) !== null && $koth->inMatch($sender) && !in_array($commandLabel, ['koth'])) {
                $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't run this command while you're in a KOTH match. Use " . TextFormat::WHITE . "/koth quit " . TextFormat::RED . "to quit the game and run that command.");
            } else if (Factions::isBadlands() && in_array($commandLabel, ['sethome', 'auctionhouse', 'ah', 'trade', 'wz', 'warzone', 'wild', 'tpa', 'fly'])) {
                $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "This command is disabled in " . TextFormat::GOLD . "Badlands" . TextFormat::RED . "! Return to spawn and run that command again.");
            } else {
                return true;
            }

            return false;
        });
    }

    /**
     * @return Factions
     */
    final public function getOwningPlugin(): Plugin
    {
        /** @var Factions $plugin */
        $plugin = parent::getOwningPlugin();

        return $plugin;
    }

    /**
     * @param MMOPlugin $plugin
     */
    public static function registerCommands(MMOPlugin $plugin): void
    {
        /** @var Factions $plugin */

        $commandMap = $plugin->getServer()->getCommandMap();

        $commandMap->register(HubCommand::class, new HubCommand($plugin));

        $commandMap->register(ResetWildDefaultsCommand::class, new ResetWildDefaultsCommand($plugin));
        $commandMap->register(BlockCommand::class, new BlockCommand($plugin));
        $commandMap->register(FactionCommand::class, new FactionCommand($plugin));
        $commandMap->register(HomeCommand::class, new HomeCommand($plugin));
        $commandMap->register(DeleteHomeCommand::class, new DeleteHomeCommand($plugin));
        $commandMap->register(BalanceCommand::class, new BalanceCommand($plugin));
        $commandMap->register(WithdrawCommand::class, new WithdrawCommand($plugin));
        $commandMap->register(EnchantListCommand::class, new EnchantListCommand($plugin));
        $commandMap->register(FeedCommand::class, new FeedCommand($plugin));
        $commandMap->register(TagCommand::class, new TagCommand($plugin));
        $commandMap->register(HealCommand::class, new HealCommand($plugin));
        $commandMap->register(FeedCommand::class, new FeedCommand($plugin));
        $commandMap->register(LobbyCommand::class, new LobbyCommand($plugin));
        $commandMap->register(KothCommand::class, new KothCommand($plugin));
        $commandMap->register(EmergencyRestartCommand::class, new EmergencyRestartCommand($plugin));
        $commandMap->register(ThreadMemoryCommand::class, new ThreadMemoryCommand($plugin));
        $commandMap->register(InvCommand::class, new InvCommand($plugin));
        $commandMap->register(PvpCommand::class, new PvpCommand($plugin));
        $commandMap->register(AutoClaimCommand::class, new AutoClaimCommand($plugin));
        $commandMap->register(HudCommand::class, new HudCommand($plugin));
        $commandMap->register(KeysCommand::class, new KeysCommand($plugin));
        $commandMap->register(HardTransferCommand::class, new HardTransferCommand(NGEssentials::getInstance()));
        $commandMap->register(DebugCommand::class, new DebugCommand());
        $commandMap->register(RollbackCommand::class, new RollbackCommand($plugin));

        // Badlands disabled commands --
        $commandMap->register(FlyCommand::class, new FlyCommand($plugin));
        $commandMap->register(SetHomeCommand::class, new SetHomeCommand($plugin));
        $commandMap->register(AuctionHouseCommand::class, new AuctionHouseCommand($plugin));
        $commandMap->register(TradeCommand::class, new TradeCommand($plugin));
        $commandMap->register(WarzoneCommand::class, new WarzoneCommand($plugin));
        $commandMap->register(WildCommand::class, new WildCommand($plugin));
        $commandMap->register(ClearEntitiesCommand::class, new ClearEntitiesCommand($plugin));
        $commandMap->register(TpaCommand::class, new TpaCommand($plugin));
        // Badlands disabled commands --

        $commandMap->register(OperatorCommand::class, new OperatorCommand($plugin));
        if (NGEssentials::isInDevelopmentMode()) {
            $commandMap->register(ExecuteCommand::class, new ExecuteCommand($plugin));
            $commandMap->register(TestCommand::class, new TestCommand());
        }

        // Factions only command.
        $commandMap->register(BountyCommand::class, new BountyCommand($plugin));
		$commandMap->register(DepositCommand::class, new DepositCommand($plugin));
        $commandMap->register(KitCommand::class, new KitCommand($plugin));
        $commandMap->register(RepairCommand::class, new RepairCommand($plugin));
        $commandMap->register(PayCommand::class, new PayCommand($plugin));
        $commandMap->register(RenameCommand::class, new RenameCommand($plugin));
        $commandMap->register(SellCommand::class, new SellCommand($plugin));
        $commandMap->register(ShopCommand::class, new ShopCommand($plugin));
        $commandMap->register(PrivateVaults::class, new PrivateVaults($plugin));
    }

    public function sendFailureMessage(CommandSender $sender, string $message): void
    {
        if ($sender instanceof Player && !$sender->isConnected()) {
            return;
        }

        $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . $message);
    }

    public function sendMessage(CommandSender $sender, string $message): void
    {
        if ($sender instanceof Player && !$sender->isConnected()) {
            return;
        }

        $sender->sendMessage(MMOPlugin::getPrefix() . $message);
    }
}