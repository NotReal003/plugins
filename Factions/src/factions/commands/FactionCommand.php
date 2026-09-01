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

use factions\faction\claims\ClaimManager;
use factions\faction\object\Faction;
use factions\faction\vaults\FactionVault;
use factions\Factions;
use factions\utils\Database;
use factions\utils\Utils;
use Generator;
use libforms\elements\Button;
use libforms\elements\Input;
use libforms\elements\Label;
use libforms\FormManager;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use libMMO\player\PlayerData as MMOPlayerData;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\player\PlayerData;
use NetherGames\NGEssentials\player\Translator;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;
use function array_slice;
use function substr;

class FactionCommand extends BaseCommand
{
    public function __construct(Factions $owningPlugin)
    {
        parent::__construct('faction', $owningPlugin);

        $this->setDescription('Factions main command.');
        $this->setAliases(["f"]);
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        $plugin = $this->getOwningPlugin();
        $playerData = $plugin->getPlayerData();

        $faction = $playerData->getFaction($sender);
        $factionManager = $plugin->getFactionManager();
        $ess = $plugin->getEssentials();

        switch ($args[0] ?? "") {
            case "map":
                if ($sender->getWorld()->getFolderName() !== 'wild' || Factions::isBadlands()) {
                    $this->sendFailureMessage($sender, 'Please execute this command in wilderness.');
                    break;
                }

                $map = $factionManager->getMap($sender, 50, 11, (int)$sender->getLocation()->getYaw());
                foreach ($map as $line) {
                    $sender->sendMessage($line);
                }
                break;
            case "create":
                if ($faction !== null) {
                    $this->sendFailureMessage($sender, "You're already in a faction.");
                } else if (!isset($args[1])) {
                    $this->sendFailureMessage($sender, "Please specify a faction name.");
                } else if (strlen($args[1]) > 16) {
                    $this->sendFailureMessage($sender, 'Your faction name is too long.');
                } else if (!preg_match('/^[a-zA-Z0-9_]+$/', $args[1]) || !Utils::checkFactionName($args[1])) {
                    $this->sendFailureMessage($sender, 'Only alphanumeric characters are allowed.');
                } else {
                    $factionManager->createFaction($sender, trim(preg_replace("/ +/", " ", $args[1])));
                }
                break;
            case "disband":
                if ($faction === null) {
                    $this->sendFailureMessage($sender, 'You must be in a faction to do that.');
                } else if ($faction->getFactionRole($sender) !== Faction::LEADER) {
                    $this->sendFailureMessage($sender, 'You must be a faction leader to do that.');
                } else {
                    $factionManager->sendFactionDisbandForm($sender, $faction, false);
                }
                break;
            case "promote":
                if ($faction === null) {
                    $this->sendFailureMessage($sender, 'You must be in a faction to do that.');
                } else if ($faction->getFactionRole($sender) !== Faction::LEADER) {
                    $this->sendFailureMessage($sender, 'You must be a faction leader to do that.');
                } else if (empty($args[1])) {
                    $this->sendFailureMessage($sender, 'Please specify the player name.');
                } else if (($memberName = $faction->getMemberByPrefix($args[1])) === null) {
                    $this->sendFailureMessage($sender, "That player is not a member of your faction!");
                } else if (in_array($memberName, $faction->getOfficers())) {
                    $this->sendFailureMessage($sender, "That player already is an officer in your faction!");
                } else if ($faction->getLeader() === $memberName) {
                    $this->sendFailureMessage($sender, "That player is a leader you silly.");
                } else {
                    $faction->setMemberRole($memberName, Faction::OFFICER, true, true);

                    $this->sendMessage($sender, "You have promoted " . $memberName);
                    $this->getOwningPlugin()->getEventEmitter()->broadcastMessage($memberName, 'You have been promoted.');
                }
                break;
            case "demote":
                if ($faction === null) {
                    $this->sendFailureMessage($sender, 'You must be in a faction to do that.');
                } else if ($faction->getFactionRole($sender) !== Faction::LEADER) {
                    $this->sendFailureMessage($sender, 'You must be a faction leader to do that.');
                } else if (empty($args[1])) {
                    $this->sendFailureMessage($sender, 'Please specify the player name.');
                } else if (($memberName = $faction->getMemberByPrefix($args[1])) === null) {
                    $this->sendFailureMessage($sender, "That player is not a member of your faction!");
                } else if ($faction->getLeader() === $memberName) {
                    $this->sendFailureMessage($sender, "That player is a leader you silly.");
                } else if (!in_array($memberName, $faction->getOfficers())) {
                    $this->sendFailureMessage($sender, "That player is not an officer in your faction!");
                } else {
                    $faction->setMemberRole($memberName, Faction::MEMBER, true, true);

                    $this->sendMessage($sender, "You have demoted " . $memberName);
                    $this->getOwningPlugin()->getEventEmitter()->broadcastMessage($memberName, 'You have been demoted.');
                }
                break;
            case "rename":
                if ($faction === null) {
                    $this->sendFailureMessage($sender, 'You must be in a faction to do that.');
                } else if ($faction->getFactionRole($sender) !== Faction::LEADER) {
                    $this->sendFailureMessage($sender, 'You must be a leader to do that.');
                } else if (empty($args[1])) {
                    $this->sendFailureMessage($sender, 'Please specify a name for your faction.');
                } else {
                    $faction->updateFactionName($sender, trim(preg_replace("/ +/", " ", $args[1])));
                }
                break;
            case "vault":
                if ($faction === null) {
                    $this->sendFailureMessage($sender, 'You must be in a faction to do that.');
                } else if ($faction->getStrength() < 500) {
                    $this->sendFailureMessage($sender, "Your faction isn't strong enough to own a vault. At least 500 power is required to use one.");
                } else {
                    FactionVault::create($faction)->setLock($sender);
                }
                break;
            case "leader":
                if ($faction === null) {
                    $this->sendFailureMessage($sender, 'You must be in a faction to do that.');
                } else if ($faction->getFactionRole($sender) !== Faction::LEADER) {
                    $this->sendFailureMessage($sender, 'You must be a leader to do that.');
                } else if (empty($args[1])) {
                    $this->sendFailureMessage($sender, 'Please specify the player name.');
                } else if (($memberName = $faction->getMemberByPrefix($args[1])) === null) {
                    $this->sendFailureMessage($sender, "That player is not a member of your faction!");
                } else {
                    $form = FormManager::createModalForm($sender);
                    if ($form === null) {
                        break;
                    }

                    $form->setTitle(MMOPlugin::getPrefix() . TextFormat::DARK_GRAY . 'Leader Transfer Form');
                    $form->setContent(TextFormat::RESET . 'Are you sure to transfer ' . $faction->getFactionName() . ' leadership to ' . $memberName . '?');

                    $form->setButton1(new Button('Yes', function (Player $player) use ($faction, $memberName) {
                        $faction->setLeader($memberName, true);

                        $this->sendMessage($player, 'You transferred the faction leadership to ' . $memberName);
                        $this->getOwningPlugin()->getEventEmitter()->broadcastMessage($memberName, 'You are now the leader of ' . $faction->getFactionName() . '\'s faction.');
                    }));
                    $form->setButton2(new Button('No'));
                    $form->sendForm();
                }
                break;
            case "kick":
                if ($faction === null) {
                    $this->sendFailureMessage($sender, 'You must be in a faction to do that.');
                } else if ($faction->getFactionRole($sender) === Faction::MEMBER) {
                    $this->sendFailureMessage($sender, 'You must be an officer or a leader to do that.');
                } else if (empty($args[1])) {
                    $this->sendFailureMessage($sender, 'Please specify the player name.');
                } else if (($memberName = $faction->getMemberByPrefix($args[1])) === null) {
                    $this->sendFailureMessage($sender, "That player is not a member of your faction!");
                } else if ($faction->getLeader() === $memberName) {
                    $this->sendFailureMessage($sender, "That player is a leader you silly.");
                } else if ($faction->getFactionRole($args[1]) === Faction::OFFICER) {
                    $this->sendFailureMessage($sender, "You cannot kick another officer from your faction.");
                } else if ($memberName === $sender->getName()) {
                    $this->sendFailureMessage($sender, "Are you insane? You cannot kick yourself, use /f leave to leave your current faction.");
                } else {
                    $faction->removeMember($memberName, true);

                    $player = Server::getInstance()->getPlayerExact($memberName);
                    if ($player !== null) {
                        $this->sendFailureMessage($player, "You have been kicked from " . $faction->getFactionName());

                        $factionManager->collectGarbage($player, $faction);
                    } else {
                        $this->getOwningPlugin()->getEventEmitter()->broadcastMessage($memberName, TextFormat::RED . "You have been kicked from " . $faction->getFactionName());
                    }

                    $this->sendMessage($sender, "You have successfully kicked " . $memberName . " from your faction.");
                }
                break;
            case "leave":
                if ($faction === null) {
                    $this->sendFailureMessage($sender, 'You must be in a faction to do that.');
                } else if ($faction->getFactionRole($sender) === Faction::LEADER) {
                    $this->sendFailureMessage($sender, 'You cannot leave your faction, disband or change faction ownership to leave.');
                } else {
                    $faction->removeMember($sender->getName(), true);

                    $factionManager->collectGarbage($sender, $faction);

                    $this->sendMessage($sender, "You have left " . $faction->getFactionName());
                }
                break;
            case "invite":
                if ($faction === null) {
                    $this->sendFailureMessage($sender, 'You must be in a faction to do that.');
                } else if ($faction->getFactionRole($sender) === Faction::MEMBER) {
                    $this->sendFailureMessage($sender, 'You must be an officer or a leader to do that.');
                } else if (empty($args[1] ?? "") || !(($target = $ess->getPlayerManager()->getBestMatchingPlayer($args[1])) instanceof Player) || !$target->isConnected()) {
                    $this->sendFailureMessage($sender, "That player is doesn't seem to be exists.");
                } else if ($playerData->getFaction($target) !== null) {
                    $this->sendFailureMessage($sender, "That player is already in a faction!");
                } else if ($playerData->isFormBlocked($target)) {
                    $this->sendFailureMessage($sender, "You can't send a request to {$target->getName()} at the moment.");
                } else if ((count($faction->getMembers()) - 1) >= Faction::MAX_NORMAL_MEMBERS) {
                    $this->sendFailureMessage($sender, "Your faction has already reached maximum members in a faction.");
                } else {
                    $this->sendMessage($sender, 'You have sent an invite to ' . $target->getName());

                    $factionManager->invitePlayer($sender, $faction, $target);
                }
                break;
            case "home":
                if ($faction === null) {
                    $this->sendFailureMessage($sender, 'You must be in a faction to do that.');
                } else {
                    $faction->teleportToHome($sender);
                }
                break;
            case "sethome":
                if ($faction === null) {
                    $this->sendFailureMessage($sender, 'You must be in a faction to do that.');
                } else if ($faction->getFactionRole($sender) !== Faction::LEADER) {
                    $this->sendFailureMessage($sender, 'You must be a leader to do that.');
                } else if ($sender->getWorld()->getFolderName() !== "wild" || Factions::isBadlands()) {
                    $this->sendFailureMessage($sender, 'Please execute this command in wilderness.');
                } else {
                    $claimManager = $this->getOwningPlugin()->getClaimManager();
                    $claim = $claimManager->getClaimInPosition($sender->getPosition());

                    if ($claim === null || $claim->getFactionId() !== $faction->getFactionId()) {
                        $this->sendFailureMessage($sender, 'You must claim this area first before setting your faction home here.');
                    } else {
                        $faction->setHomeCoordinates($sender);
                    }
                }
                break;
            case "delhome":
                if ($faction === null) {
                    $this->sendFailureMessage($sender, 'You must be in a faction to do that.');
                } else if ($faction->getFactionRole($sender) !== Faction::LEADER) {
                    $this->sendFailureMessage($sender, 'You must be a leader to do that.');
                } else if ($faction->getHome() === null) {
                    $this->sendFailureMessage($sender, 'Your faction leader hasn\'t set a faction home.');
                } else {
                    $faction->unsetHomeCoordinates($sender);
                }
                break;
            case "info":
                $factionManager->loadFactionByName($args[1] ?? "", function (?Faction $search) use ($sender, $faction, $factionManager): void {
                    if ($search === null && $faction === null) {
                        $this->sendFailureMessage($sender, "That faction does not exists!");
                    } else if ($search === null) {
                        $factionManager->sendFactionForm($sender, $faction);
                    } else {
                        $factionManager->sendFactionForm($sender, $search);
                    }
                });
                break;
            case "claim":
                if ($faction === null) {
                    $this->sendFailureMessage($sender, 'You must be in a faction to do that.');
                } else if ($faction->getFactionRole($sender) === Faction::MEMBER) {
                    $this->sendFailureMessage($sender, 'You must be an officer or a leader to do that.');
                } else if ($faction->getStrength() < 250) {
                    $this->sendFailureMessage($sender, "Your faction isn't strong enough to claim. At least 250 power is required to claim an area.");
                } else if ($sender->getWorld()->getFolderName() !== 'wild' || Factions::isBadlands()) {
                    $this->sendFailureMessage($sender, 'Please execute this command in wilderness.');
                    break;
                } else {
                    $this->getOwningPlugin()->getClaimManager()->tryAndClaimPosition($faction, $sender->getPosition(), function (int $status) use ($sender): void {
                        switch ($status) {
                            case ClaimManager::CLAIM_ERROR:
                                $this->sendFailureMessage($sender, Translator::getTranslationPlayer($sender, 'db.error'));
                                break;
                            case ClaimManager::CLAIM_CLASHING_OWN:
                                $this->sendFailureMessage($sender, 'You already have claimed this territory.');
                                break;
                            case ClaimManager::CLAIM_CLASHING_FACTION:
                                $this->sendFailureMessage($sender, 'You cannot claim this area, it has been claimed by another faction.');
                                break;
                            case ClaimManager::CLAIM_CLASHING_WARZONE:
                                $this->sendFailureMessage($sender, 'You cannot claim this area, this is a warzone area.');
                                break;
                            case ClaimManager::CLAIM_LIMIT_REACHED:
                                $this->sendFailureMessage($sender, "Your faction has reached maximum number of claims in this world, grind at least 150 more power to claim " . ClaimManager::CLAIMS_PER_STRENGTH . " more territories.");
                                break;
                            case ClaimManager::CLAIM_OK:
                                $sender->sendMessage(MMOPlugin::getPrefix() . "You have successfully claimed this area.");
                                break;
                        }
                    });
                }
                break;
            case "overclaim":
                if ($faction === null) {
                    $this->sendFailureMessage($sender, 'You must be in a faction to do that.');
                } else if ($faction->getFactionRole($sender) === Faction::MEMBER) {
                    $this->sendFailureMessage($sender, 'You must be an officer or a leader to do that.');
                } else if ($sender->getWorld()->getFolderName() !== 'wild' || Factions::isBadlands()) {
                    $this->sendFailureMessage($sender, 'Please execute this command in wilderness.');
                    break;
                } else {
                    $claimManager = $this->getOwningPlugin()->getClaimManager();

                    $claim = $claimManager->getClaimInPosition($sender->getPosition());
                    if ($claim === null) {
                        $this->sendFailureMessage($sender, 'There are no claims at your position.');
                    } else if ($claim->getFactionId() === $faction->getFactionId()) {
                        $this->sendFailureMessage($sender, "You can't overclaim your own claim.");
                    } else {
                        Await::f2c(function () use ($claimManager, $sender, $faction, $claim) {
                            $claim->getFaction(yield);

                            /** @var Faction $claimedFaction */
                            $claimedFaction = yield Await::ONCE;

                            if ($claimedFaction->getHome() !== null && $claim->isInClaim($claimedFaction->getHome())) {
                                $this->sendFailureMessage($sender, "You can't overclaim another faction's home.");
                            } else if ($claimedFaction->getStrength() >= $faction->getStrength()) {
                                $this->sendFailureMessage($sender, "You can't overclaim a stronger faction's claim.");
                            } else {
                                $claimManager->overClaimArea($sender->getPosition(), $faction, yield);

                                $status = yield Await::ONCE;
                                switch ($status) {
                                    case ClaimManager::CLAIM_ERROR:
                                        $this->sendFailureMessage($sender, Translator::getTranslationPlayer($sender, 'db.error'));
                                        break;
                                    case ClaimManager::CLAIM_CLASHING_FACTION:
                                        $this->sendFailureMessage($sender, 'There are no claims at your position.');
                                        break;
                                    case ClaimManager::CLAIM_OK:
                                        $sender->sendMessage(MMOPlugin::getPrefix() . "You overclaimed {$faction->getFactionName()}'s claim.");
                                        break;
                                }
                            }
                        });
                    }
                }
                break;
            case "unclaim":
                if ($faction === null) {
                    $this->sendFailureMessage($sender, 'You must be in a faction to do that.');
                } else if ($faction->getFactionRole($sender) === Faction::MEMBER) {
                    $this->sendFailureMessage($sender, 'You must be an officer or a leader to do that.');
                } else if ($sender->getWorld()->getFolderName() !== 'wild' || Factions::isBadlands()) {
                    $this->sendFailureMessage($sender, 'Please execute this command in wilderness.');
                    break;
                } else {
                    $claimManager = $this->getOwningPlugin()->getClaimManager();

                    $claim = $claimManager->getClaimInPosition($sender->getPosition());
                    if ($claim === null || $claim->getFactionId() !== $faction->getFactionId()) {
                        $this->sendFailureMessage($sender, 'There are no claims at your position.');
                    } else if ($faction->getHomeClaim() === $claim) {
                        $this->sendFailureMessage($sender, "You can't unclaim your faction home.");
                    } else {
                        $claimManager->removeClaim($sender->getPosition(), function () use ($sender): void {
                            $this->sendMessage($sender, "You have unclaimed this area.");
                        });
                    }
                }
                break;
            case "top":
                switch ($args[1] ?? "") {
                    case "balance":
                    case "strength":
                        $isStrength = $args[1] === "strength";

                        Await::f2c(function () use ($sender, $isStrength): Generator {
                            if ($isStrength) {
                                Database::executeSelectRaw("SELECT faction_name, strength FROM factions ORDER BY strength DESC LIMIT 10", [], yield, yield Await::REJECT);
                                $rows = yield Await::ONCE;

                                $this->sendMessage($sender, "Top 10 Strongest Factions:");
                            } else {
                                Database::executeSelectRaw("SELECT faction_name, balance FROM factions ORDER BY balance DESC LIMIT 10", [], yield, yield Await::REJECT);
                                $rows = yield Await::ONCE;

                                $this->sendMessage($sender, "Top 10 Richest Factions:");
                            }

                            $message = "";
                            foreach ($rows as $place => $data) {
                                $message .= TextFormat::LIGHT_PURPLE . TextFormat::BOLD . ($place + 1) . '. ' . TextFormat::RESET;
                                $message .= $data['faction_name'] . TextFormat::GRAY . ' » ' . TextFormat::YELLOW;
                                $message .= $isStrength ? $data['strength'] : number_format($data['balance']);

                                $this->sendMessage($sender, $message);

                                $message = "";
                            }
                        }, catches: Database::getFailClosure());
                        break;
                    default:
                        $this->sendFailureMessage($sender, "Usage: /faction top <strength/balance>");
                        break;
                }
                break;
            case "claiminfo":
                if (Factions::isBadlands()) {
                    $this->sendFailureMessage($sender, 'Please execute this command in wilderness.');
                    break;
                }

                $claim = $this->getOwningPlugin()->getClaimManager()->getClaimInPosition($sender->getPosition());
                if ($claim === null) {
                    $this->sendMessage($sender, 'There are no faction claims at this position.');
                    break;
                }

                $claim->getFaction(function (Faction $faction) use ($sender) {
                    $this->sendMessage($sender, "This claim belongs {$faction->getFactionName()} with the strength of {$faction->getStrength()}.");
                });
                break;
            case 'c':
            case 'chat':
                if ($faction === null) {
                    $this->sendFailureMessage($sender, 'You must be in a faction to do that.');
                } else {
                    $leftoverArgs = array_slice($args, 2);
                    $sender->getServer()->dispatchCommand($sender, "chat " . ($args[1] ?? "faction") . (count($leftoverArgs) > 0 ? " " . implode(" ", $leftoverArgs) : ""));
                }

                break;
            case "ally":
                if ($faction === null) {
                    $this->sendFailureMessage($sender, 'You must be in a faction to do that.');
                    break;
                } else if ($faction->getFactionRole($sender) !== Faction::LEADER) {
                    $this->sendFailureMessage($sender, 'You must be a leader to do that.');
                    break;
                } else if (empty($args[1])) {
                    $this->sendFailureMessage($sender, "You must specify a faction name to ally another faction.");
                    break;
                } else if (count($faction->getAllies()) >= $faction->getMaxAlliesSize()) {
                    $this->sendFailureMessage($sender, "Your faction has reached the maximum amount of allies allowed.");
                    break;
                }

                $playerData = $this->getOwningPlugin()->getPlayerData();
                $target = $factionManager->getFaction($args[1]);

                if ($target === null) {
                    $this->sendFailureMessage($sender, $args[1] . " is not a faction.");
                    break;
                } else if ($target->getFactionId() === $faction->getFactionId()) {
                    $this->sendFailureMessage($sender, "You can't ally with your own faction.");
                    break;
                } else if ($target->isFactionAlly($faction->getFactionId())) {
                    $this->sendFailureMessage($sender, $target->getFactionName() . " is already an ally of your own faction.");
                    break;
                } else if (count($target->getAllies()) >= $target->getMaxAlliesSize()) {
                    $this->sendFailureMessage($sender, $target->getFactionName() . " has reached the maximum amount of allies allowed.");
                    break;
                }

                /** @var MMOPlayer|null $leader */
                $leader = Server::getInstance()->getPlayerExact($target->getLeader());
                if (!($leader instanceof MMOPlayer) || !$leader->isConnected()) {
                    $this->sendFailureMessage($sender, $target->getFactionName() . 's leader is currently offline.');
                    break;
                }

                $leaderName = NGEssentials::getInstance()->getPlayerManager()->getPlayerName($leader);
                $senderName = NGEssentials::getInstance()->getPlayerManager()->getPlayerName($sender);

                if (!$playerData->canExecuteCommand($sender)) {
                    $this->sendFailureMessage($sender, "To prevent spam, you can't send forms to $leaderName right now.");
                    break;
                } else if ($playerData->isFormBlocked($leader) || $leader->isCombatTimerActive()) {
                    $this->sendFailureMessage($sender, "You can't send a request to $leaderName at the moment.");
                    break;
                }
                $playerData->setCommandTime($sender);

                $this->sendMessage($sender, 'You have sent an ally invitation to ' . $target->getFactionName());
                $this->sendMessage($leader, "Incoming form request from $senderName in 5 seconds. Close the chat menu to ensure the form displays.");

                $this->getOwningPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($faction, $target, $leader, $sender, $playerData): void {
                    $form = FormManager::createModalForm($leader);
                    if ($form === null || !$leader->isConnected()) {
                        return;
                    }

                    if ($playerData->isFormBlocked($leader) || $leader->isCombatTimerActive()) {
                        $this->sendFailureMessage($sender, "You can't send a request to {$leader->getName()} at the moment.");
                        return;
                    }

                    $form->setTitle(MMOPlugin::getPrefix() . TextFormat::DARK_GRAY . 'Ally Form');
                    $form->setContent(TextFormat::RESET . $faction->getFactionName() . ' sent you an ally request! Would you like to ally with ' . $faction->getFactionName() . '?');
                    $form->setButton1(new Button('Accept', function (Player $leader) use ($faction, $target, $sender) {
                        $faction->addAllies($target, true, function (int $code) use ($faction, $target, $leader, $sender): void {
                            switch ($code) {
                                case Faction::ADD_ALLY_ERROR:
                                    $this->sendFailureMessage($sender, Translator::getTranslationPlayer($sender, 'db.error'));
                                    break;
                                case Faction::ADD_ALLY_LOCKED:
                                    $this->sendFailureMessage($sender, 'Unable to accept ally invitation at this time, try again later.');
                                    break;
                                case Faction::ADD_ALLY_EXISTS:
                                    $this->sendFailureMessage($leader, "You're already allied with that faction.");
                                    $this->sendFailureMessage($sender, "You're already allied with that faction.");
                                    break;
                                case Faction::ADD_ALLY_FULL:
                                    $this->sendFailureMessage($sender, "Your faction has reached the maximum amount of allies allowed.");
                                    $this->sendFailureMessage($leader, "Faction {$faction->getFactionName()} has reached the maximum amount of allies allowed.");
                                    break;
                                case Faction::ADD_ALLY_PARENT_FULL:
                                    $this->sendFailureMessage($leader, "Your faction has reached the maximum amount of allies allowed.");
                                    $this->sendFailureMessage($leader, "Faction {$target->getFactionName()} has reached the maximum amount of allies allowed.");
                                    break;
                                case Faction::ADD_ALLY_OK:
                                    $this->sendMessage($sender, 'You are now allied with ' . $target->getFactionName());
                                    $this->sendMessage($leader, 'You are now allied with ' . $faction->getFactionName());
                                    break;
                            }

                        });
                    }));
                    $form->setButton2(new Button('Decline', function (Player $player) use ($sender) {
                        $this->sendFailureMessage($player, "You declined {$sender->getName()}'s request.");
                        $this->sendFailureMessage($sender, $player->getName() . " declined your request.");
                    }));
                    $form->sendForm();
                }), 20 * 5);

                break;
            case "unally":
                if ($faction === null) {
                    $this->sendFailureMessage($sender, 'You must be in a faction to do that.');
                } else if ($faction->getFactionRole($sender) !== Faction::LEADER) {
                    $this->sendFailureMessage($sender, 'You must be a leader to do that.');
                } else if (empty($args[1])) {
                    $this->sendFailureMessage($sender, 'Please specify a faction name in order to unally a faction.');
                } elseif (!$faction->isFactionAlly($args[1])) {
                    $this->sendFailureMessage($sender, $args[1] . " is not an ally of your faction.");
                } else {
                    $offlineFaction = $faction->getAllyInfo($args[1]);

                    $faction->removeAlly($offlineFaction, true, function (int $code) use ($sender, $offlineFaction) {
                        switch ($code) {
                            case Faction::REMOVE_ALLY_NOT_EXISTS:
                                $this->sendFailureMessage($sender, $offlineFaction->getFactionName() . " is not an ally of your faction.");
                                break;
                            case Faction::REMOVE_ALLY_ERROR:
                                $this->sendFailureMessage($sender, Translator::getTranslationPlayer($sender, 'db.error'));
                                break;
                            case Faction::REMOVE_ALLY_LOCKED:
                                $this->sendFailureMessage($sender, 'Unable to accept ally invitation at this time, try again later.');
                                break;
                            case Faction::REMOVE_ALLY_OK:
                                $this->sendFailureMessage($sender, "You have unallied with " . $offlineFaction->getFactionName());
                                break;
                        }
                    });
                }
                break;
            case "deposit":
                if ($faction === null) {
                    $this->sendFailureMessage($sender, 'You must be in a faction to do that.');
                } else {
                    $form = FormManager::createCustomForm($sender);
                    if ($form === null) {
                        break;
                    }
                    $form->setTitle(MMOPlugin::getPrefix() . TextFormat::DARK_GRAY . 'Deposit Form');
                    $form->addElement(new Label('Faction balance: ' . TextFormat::GOLD . number_format($faction->getBalance()) . ' coins'));
                    $form->addElement(new Label('Your balance: ' . TextFormat::GOLD . number_format($playerData->getInt($sender, MMOPlayerData::PLAYER_MONEY)) . ' coins'));
                    $form->addElement(new Input('Enter an amount to deposit into the faction:', '0', '', static function (Player $player, string $input) use ($faction) {
                        if (!is_numeric($input)) {
                            $player->sendMessage(TextFormat::RED . "That's an invalid number.");
                            return;
                        }

                        $input = (int)$input;

                        if ($input > 0) {
                            $faction->depositBalance($player, $input);
                        } else {
                            $player->sendMessage(TextFormat::RED . "That's an invalid number.");
                        }
                    }));
                    $form->sendForm();
                }
                break;
            case "withdraw":
                if ($faction === null) {
                    $this->sendFailureMessage($sender, 'You must be in a faction to do that.');
                } else if (!$faction->hasPermission($sender, Faction::ALLOW_ECONOMY_WITHDRAWAL)) {
                    $this->sendFailureMessage($sender, 'You must be an officer or a leader to do that.');
                } else {
                    $form = FormManager::createCustomForm($sender);
                    if ($form === null) {
                        break;
                    }
                    $form->setTitle(MMOPlugin::getPrefix() . TextFormat::DARK_GRAY . 'Withdraw Form');
                    $form->addElement(new Label('Faction balance: ' . TextFormat::GOLD . number_format($faction->getBalance()) . ' coins'));
                    $form->addElement(new Input('Enter an amount to withdraw from your faction:', '0', '', static function (Player $player, string $input) use ($faction) {
                        if (!is_numeric($input)) {
                            $player->sendMessage(TextFormat::RED . "That's an invalid number.");
                            return;
                        }

                        $input = (int)$input;

                        if ($input > 0) {
                            $faction->withdrawBalance($player, $input);
                        } else {
                            $player->sendMessage(TextFormat::RED . "That's an invalid number.");
                        }
                    }));
                    $form->sendForm();
                }
                break;
            case "setinfo":
                if ($faction === null) {
                    $this->sendFailureMessage($sender, 'You must be in a faction to do that.');
                } else if ($faction->getFactionRole($sender) === Faction::MEMBER) {
                    $this->sendFailureMessage($sender, 'You must be an officer or a leader to do that.');
                } else {
                    $factionManager->sendEditFactionMotd($sender, $faction);
                }
                break;
            default:
                $factionManager->sendFactionMenu($sender);
                break;
        }

        return true;
    }
}