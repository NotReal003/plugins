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

use Closure;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use NetherGames\NGEssentials\player\permissions\Permissions;
use NetherGames\NGEssentials\player\PlayerData;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;

abstract class BaseCommand extends Command
{
    public const MIN_LAST_COMMAND_DIFF = 2;

    /**
     * @var Closure[]
     * @phpstan-var (Closure(Player $sender, string $commandLabel, array $args) : bool)[]
     */
    private static array $validators = [];

    /**
     * @var string[]
     */
    protected static array $trackCommands = [];

    /** @var MMOPlugin */
    protected MMOPlugin $owningPlugin;

    public function __construct(string $name, MMOPlugin $owningPlugin)
    {
        // default permission, this can still be overwritten down the line
        $this->setPermission("nethergames.player");
        $this->owningPlugin = $owningPlugin;

        parent::__construct($name);

        self::$trackCommands = array_merge(self::$trackCommands, ['inv', 'investigate']);
        $this->addValidator(function (Player $sender, string $commandLabel, array $args): bool {
            $plugin = $this->getOwningPlugin();

            $ess = $plugin->getEssentials();
            if ($ess->getPlayerData()->getBool($sender, PlayerData::TRANSFER)) {
                $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You cannot execute any commands while in transfer mode.");
            } elseif (!$plugin->getPlayerData()->getBool($sender, \libMMO\player\PlayerData::DATA_LOADED)) {
                $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Please wait until your data is fully loaded.");
            } elseif ($ess->getPlayerData()->getBool($sender, PlayerData::TRACK) && !in_array($commandLabel, self::$trackCommands)) {
                $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't run this command while you're in tracking mode. Use " . TextFormat::YELLOW . "/track off " . TextFormat::RED . "to exit tracking mode and run that command.");
            } elseif ($sender instanceof MMOPlayer && !$sender->hasPermission(Permissions::RANK_LEGEND) && $sender->getCommandTimer() <= self::MIN_LAST_COMMAND_DIFF) {
                $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Slow down! You are using this command too fast...");
            } else {
                return true;
            }

            return false;
        });
    }

    /**
     * @phpstan-param Closure(Player $sender, string $commandLabel, array $args) : bool $validator
     */
    public function addValidator(Closure $validator): void
    {
        Utils::validateCallableSignature(function (Player $sender, string $commandLabel, array $args): bool {
            return true;
        }, $validator);

        self::$validators[] = $validator;
    }

    public static function registerCommands(MMOPlugin $plugin): void
    {
        $commandMap = $plugin->getServer()->getCommandMap();

        $commandMap->register(AuctionHouseCommand::class, new AuctionHouseCommand($plugin));
        $commandMap->register(BountyCommand::class, new BountyCommand($plugin));
        $commandMap->register(ChallengeCommand::class, new ChallengeCommand($plugin));
        $commandMap->register(DepositCommand::class, new DepositCommand($plugin));
        $commandMap->register(EnchantListCommand::class, new EnchantListCommand($plugin));
        $commandMap->register(FeedCommand::class, new FeedCommand($plugin));
        $commandMap->register(FlyCommand::class, new FlyCommand($plugin));
        $commandMap->register(HealCommand::class, new HealCommand($plugin));
        $commandMap->register(KitCommand::class, new KitCommand($plugin));
        $commandMap->register(PayCommand::class, new PayCommand($plugin));
        $commandMap->register(RenameCommand::class, new RenameCommand($plugin));
        $commandMap->register(RepairCommand::class, new RepairCommand($plugin));
        $commandMap->register(SellCommand::class, new SellCommand($plugin));
        $commandMap->register(ShopCommand::class, new ShopCommand($plugin));
        $commandMap->register(TpaCommand::class, new TpaCommand($plugin));
        $commandMap->register(WithdrawCommand::class, new WithdrawCommand($plugin));
        $commandMap->register(BlockCommand::class, new BlockCommand($plugin));
        //$commandMap->register(TradeCommand::class, new TradeCommand($plugin));
        $commandMap->register(PrivateVaults::class, new PrivateVaults($plugin));
    }

    final public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if (!($sender instanceof MMOPlayer)) {
            $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Use this command in game");
            return true;
        }

        foreach (self::$validators as $validator) {
            if (!$validator($sender, $commandLabel, $args)) {
                return true;
            }
        }

        if ($this->executeCommand($sender, $commandLabel, $args)) {
            $sender->resetCommandTimer();

            return true;
        }

        return false;
    }

    /**
     * @return MMOPlugin
     */
    public function getOwningPlugin(): Plugin
    {
        return $this->owningPlugin;
    }

    public abstract function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool;
}