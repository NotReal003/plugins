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

namespace libMMO\forms;

use Closure;
use libforms\elements\Button;
use libforms\elements\Dropdown;
use libforms\FormManager;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use NetherGames\NGEssentials\player\PlayerData;
use NetherGames\NGEssentials\utils\Utils;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils as PMUtils;
use function array_diff;

class TpaForm
{
    /**
     * @var Closure[]
     * @phpstan-var (Closure(Player $receiver, Player $requester) : bool)[]
     */
    private static array $tpaValidators = [];
    /**
     * @var Closure[]
     * @phpstan-var (Closure(Player $receiver, Player $requester) : bool)[]
     */
    private static array $taskValidators = [];

    /** @var array */
    protected static array $lastSentRequest = []; // Contains the last time a player sent a TPA request to a player.

    /** @var true[] */
    public static array $teleportCooldown = [];

    public static function addDefaultValidators(MMOPlugin $plugin): void
    {
        self::addValidators(static function (Player $receiver, Player $requester) use ($plugin): bool {
            $playerData = MMOPlugin::getInstance()->getPlayerData();
            $serverTick = (int)round(Server::getInstance()->getTick() / 20);
            /**
             * @var MMOPlayer $receiver
             * @var MMOPlayer $requester
             */
            $lastRequestSent = self::$lastSentRequest[$requester->getName()][$receiver->getName()] ?? [15, (int)round($serverTick - 15)];
            if (($waitTime = ($serverTick - $lastRequestSent[1])) < $lastRequestSent[0]) {
                $requester->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You have to wait " . TextFormat::YELLOW . ($lastRequestSent[0] - $waitTime) . "s" . TextFormat::RED . " to send another teleport request to this player!");
            } else if ($requester->isCombatTimerActive()) {
                $requester->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't teleport to someone while combat tagged.");
            } else if ($receiver->isCombatTimerActive()) {
                $requester->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't send teleport request to a combat tagged player.");
            } else if ($playerData->isFormBlocked($receiver) || ($playerData = $plugin->getEssentials()->getPlayerData())->getBool($receiver, PlayerData::TRACK) || $playerData->getBool($receiver, PlayerData::TRANSFER)) {
                $requester->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't send a teleport request to this player.");
            } else {
                return true;
            }

            return false;
        });
    }

    /**
     * Checks if the tpa request for this player is valid, you can use a static closure
     * and determine if the requester is able to run tpa command to the receiver.
     *
     * @param Closure $closure The validator to be checked for.
     * @param bool $executeOnTask Determine if this closure should run during the task.
     *
     * @phpstan-param Closure(Player $receiver, Player $requester) : bool $closure
     */
    public static function addValidators(Closure $closure, bool $executeOnTask = false): void
    {
        PMUtils::validateCallableSignature(function (Player $receiver, Player $requester): bool {
            return true;
        }, $closure);

        self::$tpaValidators[] = $closure;
        if ($executeOnTask) {
            self::$taskValidators[] = $closure;
        }
    }

    public static function isTpaRequestAvailable(Player $receiver, Player $requester): bool
    {
        foreach (self::$tpaValidators as $tpaClosure) {
            if (!$tpaClosure($receiver, $requester)) {
                return false;
            }
        }

        return true;
    }

    public static function sendTpaRequestForm(Player $player): void
    {
        $form = FormManager::createCustomForm($player);

        if ($form !== null) {
            $form->setTitle('Send Teleport Request');

            $usernames = Utils::getPlayersName(array_diff($player->getServer()->getOnlinePlayers(), [$player]));

            $form->addElement(new Dropdown('Select a player', $usernames, -1, static function (MMOPlayer $player, int $index) use ($usernames): void {
                if ($p = $player->getServer()->getPlayerExact($usernames[$index])) {
                    if (!self::isTpaRequestAvailable($p, $player)) {
                        return;
                    }

                    self::sendTpaRequestAcceptForm($p, $player);

                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . 'You sent a teleport request to ' . TextFormat::GOLD . $p->getName());
                } else {
                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "That player isn't online on this server.");
                }
            }));

            $form->sendForm();
        }
    }

    public static function sendTpaRequestAcceptForm(Player $receiver, Player $requester): bool
    {
        if (!self::isTpaRequestAvailable($receiver, $requester)) {
            return false;
        }

        $receiver->sendMessage(MMOPlugin::getPrefix() . "Incoming teleport request from {$requester->getName()} in 5 seconds. Close the chat menu to ensure the form displays.");

        self::$lastSentRequest[$requester->getName()][$receiver->getName()] = [15, (int)round(Server::getInstance()->getTick() / 20)];

        /**
         * @var MMOPlayer $receiver
         * @var MMOPlayer $requester
         */
        MMOPlugin::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($receiver, $requester): void {
            if ($requester->isCombatTimerActive()) {
                $requester->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't teleport to someone while in combat mode.");
                return;
            }

            if ($receiver->isCombatTimerActive()) {
                $requester->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "That player is in combat mode, please wait until the player is no longer in combat mode.");
                return;
            }

            foreach (self::$taskValidators as $validator) {
                if (!$validator($receiver, $requester)) {
                    return;
                }
            }

            $form = FormManager::createModalForm($receiver);

            if ($form !== null && $receiver->isConnected()) {
                $form->setTitle('Teleport Request');

                $form->setContent($requester->getName() . ' is requesting to teleport to you. Would you like to accept their request?');
                $form->setButton1(new Button(TextFormat::GREEN . 'Accept', function (Player $player) use ($requester): void {
                    /**
                     * @var MMOPlayer $requester
                     * @var MMOPlayer $player
                     */
                    if ($requester->isConnected()) {
                        if ($requester->isCombatTimerActive()) {
                            $requester->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't teleport to someone while combat tagged.");
                        } else if ($player->isCombatTimerActive()) {
                            $requester->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "That player is being combat tagged, please wait until the player no longer in combat mode.");
                        } else {
                            $playerName = $requester->getName();

                            self::$teleportCooldown[$playerName] = true;
                            MMOPlugin::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($playerName): void {
                                unset(self::$teleportCooldown[$playerName]);
                            }), 20 * 20);

                            $requester->sendMessage(MMOPlugin::getPrefix() . TextFormat::GOLD . $player->getName() . TextFormat::GREEN . ' has accepted your teleport request.');
                            $requester->teleport($player->getLocation());
                        }
                    } else {
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::AQUA . $requester->getName() . TextFormat::RED . ' is no longer on this server.');
                    }
                }));

                $form->setButton2(new Button(TextFormat::RED . 'Deny', static function (Player $player) use ($requester): void {
                    if (!$requester->isConnected()) {
                        return;
                    }

                    // Of course, if the player do not want to accept their tpa request, the player will have to wait
                    // 60 seconds until they could send another tpa request again to this player.
                    self::$lastSentRequest[$requester->getName()][$player->getName()] = [30, (int)round(Server::getInstance()->getTick() / 20)];

                    $requester->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "{$player->getName()} declined your teleport request.");
                }));

                $form->sendForm();
            }
        }), 5 * 20);

        return true;
    }
}