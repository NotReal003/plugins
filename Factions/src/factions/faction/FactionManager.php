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

namespace factions\faction;

use Closure;
use factions\faction\object\Faction;
use factions\faction\object\OfflineFaction;
use factions\Factions;
use factions\player\PlayerData;
use factions\utils\BaseClass;
use factions\utils\Database;
use factions\utils\EventEmitter;
use factions\utils\GroupManager;
use factions\utils\object\FactionLocation;
use factions\utils\Utils;
use Generator;
use JsonException;
use libforms\elements\Button;
use libforms\elements\ImageButton;
use libforms\elements\Input;
use libforms\elements\Label;
use libforms\elements\Toggle;
use libforms\FormManager;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use libMMO\utils\Permissions as MMOPermissions;
use libnetsys\protocol\NetSysSerializer;
use libnetsys\protocol\packets\datatype\PlayerLocationResponseEntry;
use libnetsys\protocol\packets\SharePacket;
use NetherGames\NGEssentials\entity\custom\HumanNPC;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\player\forms\Forms;
use NetherGames\NGEssentials\player\social\PlayerSocialInfo;
use NetherGames\NGEssentials\player\social\SocialManager;
use NetherGames\NGEssentials\player\Translator;
use NetherGames\NGEssentials\ServerManager;
use NetherGames\NGEssentials\utils\SkinUtils;
use pocketmine\entity\Skin;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use SOFe\AwaitGenerator\Await;
use Throwable;

class FactionManager extends BaseClass
{
    /** @var Faction[] */
    private array $factions = []; // loaded faction runtime.
    /** @var true[][] */
    private array $reference = [];
    /** @var int[][] */
    private array $invites = [];

    /** @var array */
    private array $factionNPCs = [];
    /** @var bool */
    private bool $isQuerying = false;

    /**
     * @param MMOPlugin $instance
     */
    public function __construct(MMOPlugin $instance)
    {
        parent::__construct($instance);

        $instance->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (): void {
            if ($this->isQuerying) {
                return;
            }

            $this->isQuerying = true;
            Await::f2c(function (): Generator {
                Database::executeSelect(Database::TOP_FACTIONS, [], yield, yield Await::REJECT);

                $rows = yield Await::ONCE;
                $npcList = $this->getFactionNPC();

                $npcId = 0;
                foreach ($rows as ['faction_name' => $factionName, 'leader' => $leader, 'strength' => $strength]) {
                    if (!isset($npcList[$npcId])) {
                        break;
                    } else if ($npcList[$npcId][0] === $factionName) {
                        goto skipRecheck;
                    }

                    /** @var HumanNPC $npc */
                    $npc = $npcList[$npcId][1];
                    $npcName = match ($npcId) {
                        0 => TextFormat::BOLD . TextFormat::YELLOW . "1st Place " . TextFormat::RESET . TextFormat::GRAY . "- " . TextFormat::AQUA . $factionName . " - " . TextFormat::YELLOW . "$strength  " . TextFormat::EOL . TextFormat::GRAY . "Leader: " . TextFormat::WHITE . $leader,
                        1 => TextFormat::BOLD . TextFormat::GOLD . "2nd Place " . TextFormat::RESET . TextFormat::GRAY . "- " . TextFormat::AQUA . $factionName . " - " . TextFormat::YELLOW . "$strength " . TextFormat::EOL . TextFormat::GRAY . "Leader: " . TextFormat::WHITE . $leader,
                        2 => TextFormat::BOLD . TextFormat::RED . "3rd Place " . TextFormat::RESET . TextFormat::GRAY . "- " . TextFormat::AQUA . $factionName . " - " . TextFormat::YELLOW . "$strength " . TextFormat::EOL . TextFormat::GRAY . "Leader: " . TextFormat::WHITE . $leader,
                        3 => TextFormat::YELLOW . "#4 " . TextFormat::AQUA . "$factionName - " . TextFormat::YELLOW . "$strength " . TextFormat::EOL . TextFormat::GRAY . "Leader: " . TextFormat::WHITE . " $leader",
                        4 => TextFormat::YELLOW . "#5 " . TextFormat::AQUA . "$factionName - " . TextFormat::YELLOW . "$strength " . TextFormat::EOL . TextFormat::GRAY . "Leader: " . TextFormat::WHITE . " $leader",
                        default => null
                    };
                    if ($npcName === null) {
                        break;
                    }

                    SkinUtils::getSkin($leader, yield);

                    /** @var Skin $skin */
                    $skin = yield Await::ONCE;

                    $npc->setUsername($npcName);
                    $npc->setSkin($skin);
                    $npc->setCallable(static function (Player $player) use ($factionName): void {
                        $player->getServer()->dispatchCommand($player, 'f info ' . $factionName);
                    });
                    $npc->updateNametag();
                    $npc->updateMetadata();

                    $npcList[$npcId] = [$factionName, $npc];

                    skipRecheck:
                    $npcId++;
                }

                $defaultSkin = new Skin('Standard_Custom', SkinUtils::getTextureFromResources('skins' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'steve.png'));
                while ($npcId < 5) {
                    if (!isset($npcList[$npcId])) {
                        break;
                    }

                    /** @var HumanNPC $npc */
                    $npc = $npcList[$npcId][1];
                    $npcName = match ($npcId) {
                        0 => TextFormat::BOLD . TextFormat::YELLOW . "1st Place " . TextFormat::RESET . TextFormat::GRAY . "- " . TextFormat::RED . "No Record - " . TextFormat::YELLOW . "0  " . TextFormat::EOL . TextFormat::GRAY . "Leader: " . TextFormat::WHITE . 'Unknown',
                        1 => TextFormat::BOLD . TextFormat::GOLD . "2nd Place " . TextFormat::RESET . TextFormat::GRAY . "- " . TextFormat::RED . "No Record - " . TextFormat::YELLOW . "0 " . TextFormat::EOL . TextFormat::GRAY . "Leader: " . TextFormat::WHITE . 'Unknown',
                        2 => TextFormat::BOLD . TextFormat::RED . "3rd Place " . TextFormat::RESET . TextFormat::GRAY . "- " . TextFormat::RED . "No Record - " . TextFormat::YELLOW . "0 " . TextFormat::EOL . TextFormat::GRAY . "Leader: " . TextFormat::WHITE . 'Unknown',
                        3 => TextFormat::YELLOW . "#4 " . TextFormat::RED . "No Record - " . TextFormat::YELLOW . "0 " . TextFormat::EOL . TextFormat::GRAY . "Leader: " . TextFormat::WHITE . " Unknown",
                        4 => TextFormat::YELLOW . "#5 " . TextFormat::RED . "No Record - " . TextFormat::YELLOW . "0 " . TextFormat::EOL . TextFormat::GRAY . "Leader: " . TextFormat::WHITE . " Unknown",
                    };

                    $npc->setUsername($npcName);
                    $npc->setSkin($defaultSkin);
                    $npc->setCallable(static function (Player $player) use ($npcId): void {
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Oops, you were here too soon, create a faction to claim #' . ($npcId + 1) . ' place :D');
                    });
                    $npc->updateNametag();
                    $npc->updateMetadata();

                    $npcList[$npcId] = [null, $npc];
                    $npcId++;
                }

                $this->setFactionNPCs($npcList);
            });
        }), 10 * 60 * 20); // Every 10 minutes
    }

    /**
     * @return array
     */
    public function getFactionNPC(): array
    {
        return $this->factionNPCs;
    }

    /**
     * @param array $factionNPCs
     */
    public function setFactionNPCs(array $factionNPCs): void
    {
        $this->factionNPCs = $factionNPCs;
    }

    // ------------------------------------------------- FORMS SYSTEMS -------------------------------------------------

    public function sendFactionMenu(Player $player): void
    {
        $form = FormManager::createSimpleForm($player);

        if (!$player->isConnected() || $form === null) {
            $player->sendMessage(Factions::getPrefix() . TextFormat::RED . 'Something went wrong while trying to open a form, try again later.');
            return;
        }

        $goBack = function (Player $player): void {
            $this->sendFactionMenu($player);
        };

        $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . "Faction's menu");
        if ($this->isInFaction($player)) {
            $factionId = $this->getPlugin()->getPlayerData()->getInt($player, PlayerData::FACTION_ID);
            $faction = $this->getFaction($factionId);

            if ($faction === null) {
                $player->sendMessage(Factions::getPrefix() . TextFormat::RED . 'Something went wrong while trying to open a form, try again later.');
                return;
            }

            $form->addButton(new ImageButton(TextFormat::DARK_GRAY . "Faction manager", ImageButton::IMAGE_TYPE_FACE, $faction->getLeader(), function (Player $player) use ($faction) {
                $this->sendFactionForm($player, $faction);
            }));
        } else {
            $form->addButton(new Button(TextFormat::DARK_GRAY . "Create faction" . TextFormat::EOL . TextFormat::DARK_AQUA . "Create your own faction.", function (Player $player) use ($goBack): void {
                $this->sendCreateFactionForm($player, $goBack);
            }));

            $form->addButton(new Button(TextFormat::DARK_GRAY . 'View invites' . TextFormat::EOL . TextFormat::DARK_AQUA . 'Current invites: ' . TextFormat::YELLOW . count($this->getInvites($player)), function (Player $player) use ($goBack) {
                $this->sendInviteForm($player, $goBack);
            }));
        }

        $form->addButton(new Button(TextFormat::DARK_GRAY . "Search faction", function (Player $player) {
            $this->searchFactionForm($player);
        }));

        $form->addButton(new Button(TextFormat::DARK_GRAY . "Faction's guidebook", function (Player $player) {
            $this->sendFactionHelpMenu($player);
        }));

        $form->sendForm();
    }

    public function sendInviteForm(Player $player, Closure $goBack): void
    {
        $form = FormManager::createSimpleForm($player);

        if (!$player->isConnected() || $form === null) {
            return;
        }

        $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . 'Faction Invites');

        foreach ($this->getInvites($player) as $invite) {
            if (($faction = $this->getFaction($invite)) === null) {
                continue;
            }

            $form->addButton(new Button(TextFormat::DARK_GRAY . $faction->getFactionName() . TextFormat::EOL . TextFormat::GRAY . 'Join the ' . $faction->getFactionName() . ' faction.', function (Player $player) use ($faction) {
                Await::f2c(function () use ($player, $faction) {
                    if (count($faction->getMembers()) >= $faction->getMaxFactionSize()) {
                        $player->sendMessage(TextFormat::RED . 'That faction is currently full.');
                    } else {
                        $faction->addMember($player, Faction::MEMBER, true, true, yield);
                        $result = yield Await::ONCE;

                        if (!$player->isConnected()) {
                            return;
                        }

                        switch ($result) {
                            case Faction::ADD_MEMBER_LOCKED:
                                $player->sendMessage(TextFormat::RED . 'The faction cannot accept any invitations now, try again later.');
                                return;
                            case Faction::ADD_MEMBER_FULL:
                                $player->sendMessage(TextFormat::RED . 'That faction is currently full.');
                                break;
                            case Faction::ADD_MEMBER_EXISTS:
                                $player->sendMessage(TextFormat::RED . 'You are already in this faction!');
                                break;
                            case Faction::ADD_MEMBER_OK:
                                if (!isset($this->reference[$faction->getFactionId()])) {
                                    // The faction was de-referenced, well lets load the faction back into memory shall we?

                                    $this->loadFactionByPlayer($player, yield);
                                    $faction = yield Await::ONCE;

                                    // This can be an indicator that the player is offline.
                                    if ($faction === null) {
                                        return;
                                    }
                                } else {
                                    $this->reference[$faction->getFactionId()][$player->getName()] = true;
                                }

                                $player->sendMessage('§6Welcome to the §b' . $faction->getFactionName() . ' §6faction!');
                                break;
                            default:
                                $player->sendMessage(TextFormat::RED . 'An unexpected error occurred. Please try again later.');
                                break;
                        }

                        $this->removeInvites($player->getName());
                    }
                });
            }));
        }
        $form->addButton(new ImageButton(TextFormat::RED . TextFormat::BOLD . 'Back', ImageButton::IMAGE_TYPE_PATH, 'textures/blocks/barrier', $goBack));

        $form->sendForm();
    }

    public function sendCreateFactionForm(Player $player, Closure $goBack, bool $repeated = false): void
    {
        $form = FormManager::createCustomForm($player);

        if (!$player->isConnected() || $form === null) {
            return;
        }

        $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . "Create faction");
        $form->addElement(new Label(($repeated ? TextFormat::RED : TextFormat::YELLOW) . "Please make sure your faction name does not exceed 16 characters limit."));
        $form->addElement(new Input("Enter faction name:", 'Your faction name', callable: function (Player $player, string $factionName) use ($goBack): void {
            Await::f2c(function () use ($player, $factionName, $goBack): Generator {
                $factionName = TextFormat::clean($factionName);

                if (strlen($factionName) > 16) {
                    $this->sendCreateFactionForm($player, $goBack, true);
                    return;
                }

                $this->createFaction($player, trim(preg_replace("/[ ]+/", " ", $factionName)), yield);
                $faction = yield Await::ONCE;

                MMOPlugin::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(yield), 20);
                yield Await::ONCE;

                if ($faction === null) {
                    $this->sendCreateFactionForm($player, $goBack);
                } else {
                    $this->sendFactionForm($player, $faction);
                }
            });
        }));
        $form->setCloseClosure($goBack);

        $form->sendForm();
    }

    public function searchFactionForm(Player $player, string $lastFaction = ''): void
    {
        $form = FormManager::createCustomForm($player);

        if (!$player->isConnected() || $form === null) {
            return;
        }

        $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . "Search faction");
        if (!empty($lastFaction)) {
            $form->addElement(new Label(TextFormat::RED . "The faction named " . TextFormat::YELLOW . $lastFaction . TextFormat::RED . " does not exists."));
        }

        $form->addElement(new Input("Enter faction name:", 'Other\'s faction name', callable: function (Player $player, string $factionName) {
            Await::f2c(function () use ($player, $factionName): Generator {
                $factionName = TextFormat::clean($factionName);

                $this->loadFactionByName($factionName, yield);
                $faction = yield Await::ONCE;

                MMOPlugin::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(yield), 20);
                yield Await::ONCE;

                if (!$player->isConnected()) {
                    return;
                }

                if ($faction === null) {
                    $this->searchFactionForm($player, $factionName);
                } else {
                    self::sendFactionForm($player, $faction);
                }
            });
        }));

        $form->sendForm();
    }

    /**
     * The main menu of the factions form, this form will allow players to:
     * - Change their faction name
     * - Change their message of the day
     * - Disband/leave their own faction.
     * - Open faction members info.
     * - Teleport to their faction home.
     *
     * @param Player $player
     * @param Faction $faction
     */
    public function sendFactionForm(Player $player, Faction $faction): void
    {
        $goBack = function (Player $player) use ($faction) {
            $this->sendFactionForm($player, $faction);
        };

        Await::f2c(function () use ($player, $faction, $goBack): Generator {
            SocialManager::requestPlayerInfos($faction->getMembers(), yield);

            /**
             * @param (?PlayerSocialInfo)[] $results
             * @phpstan-param array<string, PlayerSocialInfo|null> $results
             */
            $entries = yield Await::ONCE;

            $form = FormManager::createSimpleForm($player);
            if (!$player->isConnected() || $form === null) {
                return;
            }

            $onlineCounter = 0;
            foreach ($entries as $entry) {
                if ($entry !== null) {
                    $onlineCounter++;
                }
            }

            $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . "Faction Manager");
            $form->setContent("Faction: " . TextFormat::GREEN . $faction->getFactionName() . TextFormat::RESET . TextFormat::EOL . "Strength: " . TextFormat::GREEN . $faction->getStrength());

            $form->addButton(new Button(TextFormat::DARK_GRAY . 'Open faction info' . TextFormat::EOL . TextFormat::DARK_AQUA . 'Show faction\'s current info', static function (Player $player) use ($faction, $entries, $goBack): void {
                self::sendFactionInfoForm($player, $faction, $entries, $goBack);
            }));

            $form->addButton(new Button(TextFormat::DARK_GRAY . 'Faction members' . TextFormat::EOL . TextFormat::DARK_AQUA . 'Online: ' . TextFormat::GREEN . $onlineCounter . TextFormat::DARK_AQUA . ' | Members: ' . TextFormat::GREEN . count($entries) . '/' . $faction->getMaxFactionSize(), function (Player $player) use ($faction, $entries, $goBack): void {
                $this->sendMembersMenu($player, $faction, $entries, $goBack);
            }));

            if ($faction->isMember($player->getName()) || MMOPermissions::hasElevatedPermission($player)) {
                if ($faction->getHome() !== null) {
                    $form->addButton(new Button(TextFormat::DARK_GRAY . 'Teleport to home' . TextFormat::EOL . TextFormat::DARK_AQUA . 'Teleport back to faction home.', function (Player $player) use ($faction): void {
                        $faction->teleportToHome($player);
                    }));
                }

                // Allow admin to disband this faction IF they are not a member of this faction.
                $force = !$faction->isMember($player->getName()) && MMOPermissions::hasElevatedPermission($player);
                $playerRank = $faction->getFactionRole($player);

                if ($playerRank === Faction::LEADER || $force) {
                    $form->addButton(new Button(TextFormat::DARK_GRAY . 'Rename faction' . TextFormat::EOL . TextFormat::DARK_AQUA . 'Rename your faction name', static function (Player $player) use ($faction, $force, $goBack): void {
                        self::sendFactionRenameForm($player, $faction, $force, $goBack);
                    }));
                    $form->addButton(new Button(TextFormat::DARK_GRAY . 'Edit permissions' . TextFormat::EOL . TextFormat::DARK_AQUA . 'Edit player permissions', function (Player $player) use ($goBack, $faction, $entries): void {
                        $this->sendEditFactionPermissions($player, $faction, $entries, $goBack);
                    }));
                    $form->addButton(new Button(TextFormat::DARK_GRAY . 'Settings' . TextFormat::EOL . TextFormat::DARK_AQUA . 'Edit faction settings', function (Player $player) use ($goBack, $faction): void {
                        $this->sendFactionSettings($player, $faction, $goBack);
                    }));
                    $form->addButton(new Button(TextFormat::RED . 'Disband the faction', function (Player $player) use ($goBack, $faction, $force): void {
                        $this->sendFactionDisbandForm($player, $faction, $force, $goBack);
                    }));
                } else {
                    $form->addButton(new Button(TextFormat::RED . 'Leave faction', function (Player $player) use ($goBack, $faction): void {
                        $this->sendFactionLeaveForm($player, $faction, $goBack);
                    }));
                }
            }

            $form->sendForm();
        });
    }

    public function sendFactionSettings(Player $player, Faction $faction, ?Closure $onBack = null): void
    {
        $form = FormManager::createCustomForm($player);

        if (!$player->isConnected() || $form === null) {
            return;
        }

        $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . 'Factions Settings');
        $form->addElement(new Input (TextFormat::YELLOW . "Message of the day", '', $faction->getMotd(), function (Player $player, string $motd) use ($faction): void {
            if ($motd === '') {
                $player->sendMessage(Factions::getPrefix() . TextFormat::GREEN . 'Your faction MOTD has been reset.');
                $faction->setMotd($motd, true);
            } else {
                $filter = $this->getPlugin()->getEssentials()->getPlayerManager()->getChatManager()->getFilter();
                if (!$filter->checkAdvertising($player, $motd)) {
                    return;
                }

                if (strlen($motd) > 40) {
                    $player->sendMessage(Factions::getPrefix() . TextFormat::RED . "Your faction motd cannot exceed more than 40 characters.");
                    return;
                }

                $filter->checkSwearing($player, $motd, function () use ($player, $motd, $faction) {
                    $faction->setMotd($motd, true);

                    if ($player->isConnected()) {
                        $player->sendMessage(Factions::getPrefix() . TextFormat::GREEN . 'Your faction MOTD has been set.');
                    }
                });
            }
        }));
        $form->addElement(new Label(TextFormat::GOLD . "Perform an auto-kick if the player stays offline for specific amount of days."));
        $form->addElement(new Input(TextFormat::YELLOW . "Auto-kick days", '', "{$faction->getAutoKickDays()}", function (Player $player, $data) use ($faction): void {
            if (!is_numeric($data)) {
                $player->sendMessage(Factions::getPrefix() . TextFormat::RED . "Auto-kick days must be an integer.");
                return;
            }

            if ((int)$data > 100) {
                $player->sendMessage(MMOPlugin::getPrefix() . "Auto-kick days cannot be larger than 100 days");
                return;
            }

            $faction->setAutoKickDays((int)$data);

            $player->sendMessage(MMOPlugin::getPrefix() . "Auto-kick days is now set to " . $data . " days");
        }));

        $form->addElement(new Label(TextFormat::GOLD . "Perform an auto-kick if the member of your factions received more deaths in a day."));
        $form->addElement(new Input(TextFormat::YELLOW ."Auto-kick deaths", '', "{$faction->getAutoKickDeaths()}", function (Player $player, $data) use ($faction): void {
            if (!is_numeric($data)) {
                $player->sendMessage(Factions::getPrefix() . TextFormat::RED . "Auto-kick deaths must be an integer.");
                return;
            }

            if ((int)$data > 100) {
                $player->sendMessage(MMOPlugin::getPrefix() . "Auto-kick deaths cannot be larger than 100 deaths/day");
                return;
            }

            $faction->setAutoKickDeaths((int)$data);

            $player->sendMessage(MMOPlugin::getPrefix() . "Auto-kick deaths is now set to " . $data . " deaths/day");
        }));

        $form->setCallable($onBack);

        $form->sendForm();
    }

    public function sendEditFactionPermissions(Player $player, Faction $faction, array $entries, ?Closure $onBack = null): void
    {
        $form = FormManager::createSimpleForm($player);

        if (!$player->isConnected() || $form === null) {
            return;
        }

        $goBack = function (Player $player) use ($onBack, $faction, $entries) {
            $this->sendEditFactionPermissions($player, $faction, $entries, $onBack);
        };

        $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . 'Set Players Permissions');
        $form->setContent("Select a player to continue:");

        $onlineOfficers = [];
        $onlineMembers = [];
        $offlineOfficers = $faction->getOfficers();
        $offlineMembers = $faction->getMembers(true);

        /** @var ?PlayerSocialInfo $entry */
        foreach ($entries as $playerName => $entry) {
            $online = $entry !== null;
            $rank = $faction->getFactionRole($playerName);

            if ($rank === Faction::LEADER) {
                if ($online) {
                    $text = TextFormat::DARK_GRAY . $faction->getLeader() . TextFormat::EOL . TextFormat::DARK_AQUA . 'Leader' . TextFormat::GRAY . ' | ' . TextFormat::GREEN . 'Online';
                } else {
                    $text = TextFormat::DARK_GRAY . $faction->getLeader() . TextFormat::EOL . TextFormat::DARK_AQUA . 'Leader' . TextFormat::GRAY . ' | ' . TextFormat::RED . 'Offline';
                }

                $form->addButton(new ImageButton($text, ImageButton::IMAGE_TYPE_FACE, $faction->getLeader(), function (Player $player) use ($goBack) {
                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You cant edit your own permissions, you're faction leader.");

                    $this->getPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player, $goBack): void {
                        $goBack($player);
                    }), 20);
                }));
            } else if ($online) {
                if ($rank === Faction::OFFICER && ($key = array_search($playerName, $offlineOfficers)) !== false) {
                    $onlineOfficers[] = $offlineOfficers[$key];

                    unset($offlineOfficers[$key]);
                } else if (($key = array_search($playerName, $offlineMembers)) !== false) {
                    $onlineMembers[] = $offlineMembers[$key];

                    unset($offlineMembers[$key]);
                }
            }
        }

        foreach ($onlineOfficers as $officer) {
            $form->addButton(new ImageButton(TextFormat::DARK_GRAY . $officer . TextFormat::EOL . TextFormat::DARK_AQUA . 'Officer' . TextFormat::GRAY . ' | ' . TextFormat::GREEN . 'Online', ImageButton::IMAGE_TYPE_FACE, $officer, function (Player $player) use ($officer, $faction, $goBack) {
                $this->sendPlayerPermissionForm($player, $faction, $officer, $goBack);
            }));
        }

        foreach ($offlineOfficers as $officer) {
            $form->addButton(new ImageButton(TextFormat::DARK_GRAY . $officer . TextFormat::EOL . TextFormat::DARK_AQUA . 'Officer' . TextFormat::GRAY . ' | ' . TextFormat::RED . 'Offline', ImageButton::IMAGE_TYPE_FACE, $officer, function (Player $player) use ($officer, $faction, $goBack) {
                $this->sendPlayerPermissionForm($player, $faction, $officer, $goBack);
            }));
        }

        foreach ($onlineMembers as $member) {
            $form->addButton(new ImageButton(TextFormat::DARK_GRAY . $member . TextFormat::EOL . TextFormat::GREEN . 'Online', ImageButton::IMAGE_TYPE_FACE, $member, function (Player $player) use ($member, $faction, $goBack) {
                $this->sendPlayerPermissionForm($player, $faction, $member, $goBack);
            }));
        }

        foreach ($offlineMembers as $member) {
            $form->addButton(new ImageButton(TextFormat::DARK_GRAY . $member . TextFormat::EOL . TextFormat::RED . 'Offline', ImageButton::IMAGE_TYPE_FACE, $member, function (Player $player) use ($member, $faction, $goBack) {
                $this->sendPlayerPermissionForm($player, $faction, $member, $goBack);
            }));
        }

        $form->addButton(new ImageButton(TextFormat::RED . TextFormat::BOLD . 'Back', ImageButton::IMAGE_TYPE_PATH, 'textures/blocks/barrier', $onBack));
        $form->sendForm();
    }


    public function sendPlayerPermissionForm(Player $player, Faction $faction, string $member, callable $goBack): void
    {
        $form = FormManager::createCustomForm($player);

        if (!$player->isConnected() || $form === null) {
            return;
        }

        $form->setCallable($goBack);
        $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . "$member's Permissions");

        Await::f2c(function () use ($player, $form, $faction, $member): Generator {
            $form->addElement(new Toggle('Strength Draining/Gaining', $faction->hasPermission($member, Faction::ALLOW_STRENGTH_MODIFIER), yield Await::RESOLVE_MULTI, true));
            $form->addElement(new Toggle('Base Building', $faction->hasPermission($member, Faction::ALLOW_BASE_BUILD), yield Await::RESOLVE_MULTI, true));
            $form->addElement(new Toggle('Base Interaction', $faction->hasPermission($member, Faction::ALLOW_BASE_INTERACTION), yield Await::RESOLVE_MULTI, true));
            $form->addElement(new Toggle('Teleport to Base', $faction->hasPermission($member, Faction::ALLOW_TELEPORT_BASE), yield Await::RESOLVE_MULTI, true));
            $form->addElement(new Toggle('Withdraw Balance', $faction->hasPermission($member, Faction::ALLOW_ECONOMY_WITHDRAWAL), yield Await::RESOLVE_MULTI, true));

            $form->sendForm();

            [[1 => $drain], [1 => $build], [1 => $interact], [1 => $teleport], [1 => $withdraw]] = yield Await::ALL;

            $flags = 0;
            if ($drain) $flags |= Faction::ALLOW_STRENGTH_MODIFIER;
            if ($build) $flags |= Faction::ALLOW_BASE_BUILD;
            if ($interact) $flags |= Faction::ALLOW_BASE_INTERACTION;
            if ($teleport) $flags |= Faction::ALLOW_TELEPORT_BASE;
            if ($withdraw) $flags |= Faction::ALLOW_ECONOMY_WITHDRAWAL;

            $faction->updatePermission($player, $member, $flags);
        });
    }

    public function sendEditFactionMotd(Player $player, Faction $faction, ?Closure $goBack = null): void
    {
        $form = FormManager::createCustomForm($player, $goBack);

        if (!$player->isConnected() || $form === null) {
            return;
        }

        $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . 'Set Faction MOTD');

        $form->addElement(new Label('Your faction MOTD is displayed to your faction members everytime they join the server.'));
        $form->addElement(new Input('Enter a MOTD:', $faction->getMotd() === '' ? 'Hi faction members!' : $faction->getMotd(), $faction->getMotd(), function (Player $player, string $motd) use ($faction): void {
            if ($motd === '') {
                $player->sendMessage(Factions::getPrefix() . TextFormat::GREEN . 'Your faction MOTD has been reset.');
                $faction->setMotd($motd, true);
            } else {
                $filter = $this->getPlugin()->getEssentials()->getPlayerManager()->getChatManager()->getFilter();
                if (!$filter->checkAdvertising($player, $motd)) {
                    return;
                }

                if (strlen($motd) > 40) {
                    $player->sendMessage(Factions::getPrefix() . TextFormat::RED . "Your faction motd cannot exceed more than 40 characters.");
                    return;
                }

                $filter->checkSwearing($player, $motd, function () use ($player, $motd, $faction) {
                    $faction->setMotd($motd, true);

                    if ($player->isConnected()) {
                        $player->sendMessage(Factions::getPrefix() . TextFormat::GREEN . 'Your faction MOTD has been set.');
                    }
                });
            }
        }));

        $form->sendForm();
    }

    /**
     * @param Player $player
     * @param Faction $faction
     * @param bool $force
     * @param Closure|null $goBack
     * @return void
     */
    public function sendFactionDisbandForm(Player $player, Faction $faction, bool $force, ?Closure $goBack = null): void
    {
        $form = FormManager::createCustomForm($player, $goBack);

        if ($player->isConnected() && $form !== null) {
            $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . 'Disband Faction');

            $factionName = $faction->getFactionName();

            $form->addElement(new Label('Are you sure you want to disband the ' . $factionName . ' faction? §cThis action is irreversible!'));
            $form->addElement(new Input('Type your faction name to confirm', $factionName, '', function (Player $player, string $input) use ($faction, $force): void {
                if ($input === $faction->getFactionName()) {
                    $this->disbandFaction($player, $faction, $force);
                } else {
                    $player->sendMessage(TextFormat::RED . TextFormat::RED . 'The faction name is incorrect. You must type the faction name exactly as it is named (case-sensitive).');
                }
            }));

            $form->sendForm();
        }
    }

    /**
     * @param Player $player
     * @param Faction $faction
     * @param Closure $goBack
     */
    public function sendFactionLeaveForm(Player $player, Faction $faction, Closure $goBack): void
    {
        $form = FormManager::createModalForm($player);

        if ($player->isConnected() && $form !== null) {
            $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . 'Leave Faction');

            $form->setContent('Are you sure you want to leave the ' . $faction->getFactionName() . ' faction?');

            $form->setButton1(new Button(TextFormat::GREEN . 'Yes', function (Player $player) use ($faction) {
                $player->sendMessage(Factions::getPrefix() . TextFormat::GREEN . 'You left the ' . TextFormat::AQUA . $faction->getFactionName() . TextFormat::GREEN . ' faction.');

                $faction->removeMember($player->getName(), true);
                $this->collectGarbage($player, $faction);

                $this->sendFactionMessage($faction, '§b' . $player->getName() . ' §6left the faction.');
            }));
            $form->setButton2(new Button(TextFormat::RED . 'No', $goBack));

            $form->sendForm();
        }
    }

    /**
     * @param Player $player
     * @param Faction $faction
     * @param (?PlayerSocialInfo)[] $entries
     * @param Closure $onBack
     */
    public function sendMembersMenu(Player $player, Faction $faction, array $entries, Closure $onBack): void
    {
        $form = FormManager::createSimpleForm($player);

        if ($player->isConnected() && $form !== null) {
            $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . 'Faction Members');

            $onlineOfficers = [];
            $onlineMembers = [];
            $offlineOfficers = $faction->getOfficers();
            $offlineMembers = $faction->getMembers(true);

            $goBack = function (Player $player) use ($onBack, $faction, $entries) {
                $this->sendMembersMenu($player, $faction, $entries, $onBack);
            };

            /** @var ?PlayerSocialInfo $entry */
            foreach ($entries as $playerName => $entry) {
                $online = $entry !== null;
                $rank = $faction->getFactionRole($playerName);

                if ($rank === Faction::LEADER) {
                    if ($online) {
                        $text = TextFormat::DARK_GRAY . $faction->getLeader() . TextFormat::EOL . TextFormat::DARK_AQUA . 'Leader' . TextFormat::GRAY . ' | ' . TextFormat::GREEN . 'Online';
                    } else {
                        $text = TextFormat::DARK_GRAY . $faction->getLeader() . TextFormat::EOL . TextFormat::DARK_AQUA . 'Leader' . TextFormat::GRAY . ' | ' . TextFormat::RED . 'Offline';
                    }

                    $form->addButton(new ImageButton($text, ImageButton::IMAGE_TYPE_FACE, $faction->getLeader(), function (Player $player) use ($faction, $goBack) {
                        $this->sendMemberMenu($player, $faction, $faction->getLeader(), Faction::LEADER, $goBack);
                    }));
                } else if ($online) {
                    if ($rank === Faction::OFFICER && ($key = array_search($playerName, $offlineOfficers)) !== false) {
                        $onlineOfficers[] = $offlineOfficers[$key];

                        unset($offlineOfficers[$key]);
                    } else if (($key = array_search($playerName, $offlineMembers)) !== false) {
                        $onlineMembers[] = $offlineMembers[$key];

                        unset($offlineMembers[$key]);
                    }
                }
            }

            foreach ($onlineOfficers as $officer) {
                $form->addButton(new ImageButton(TextFormat::DARK_GRAY . $officer . TextFormat::EOL . TextFormat::DARK_AQUA . 'Officer' . TextFormat::GRAY . ' | ' . TextFormat::GREEN . 'Online', ImageButton::IMAGE_TYPE_FACE, $officer, function (Player $player) use ($officer, $faction, $goBack) {
                    $this->sendMemberMenu($player, $faction, $officer, Faction::OFFICER, $goBack);
                }));
            }

            foreach ($offlineOfficers as $officer) {
                $form->addButton(new ImageButton(TextFormat::DARK_GRAY . $officer . TextFormat::EOL . TextFormat::DARK_AQUA . 'Officer' . TextFormat::GRAY . ' | ' . TextFormat::RED . 'Offline', ImageButton::IMAGE_TYPE_FACE, $officer, function (Player $player) use ($officer, $faction, $goBack) {
                    $this->sendMemberMenu($player, $faction, $officer, Faction::OFFICER, $goBack);
                }));
            }

            foreach ($onlineMembers as $member) {
                $form->addButton(new ImageButton(TextFormat::DARK_GRAY . $member . TextFormat::EOL . TextFormat::GREEN . 'Online', ImageButton::IMAGE_TYPE_FACE, $member, function (Player $player) use ($member, $faction, $goBack) {
                    $this->sendMemberMenu($player, $faction, $member, Faction::MEMBER, $goBack);
                }));
            }

            foreach ($offlineMembers as $member) {
                $form->addButton(new ImageButton(TextFormat::DARK_GRAY . $member . TextFormat::EOL . TextFormat::RED . 'Offline', ImageButton::IMAGE_TYPE_FACE, $member, function (Player $player) use ($member, $faction, $goBack) {
                    $this->sendMemberMenu($player, $faction, $member, Faction::MEMBER, $goBack);
                }));
            }

            $form->addButton(new ImageButton(TextFormat::RED . TextFormat::BOLD . 'Back', ImageButton::IMAGE_TYPE_PATH, 'textures/blocks/barrier', $onBack));

            $form->sendForm();
        }
    }

    public function sendMemberMenu(Player $player, Faction $faction, string $member, int $memberRank, callable $onBack): void
    {
        $form = FormManager::createSimpleForm($player);

        if ($player->isConnected() && $form !== null) {
            $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . 'Faction Member: ' . $member);

            // Administrators will have the ability to control player's faction.
            $force = !$faction->isMember($player->getName()) && MMOPermissions::hasElevatedPermission($player);

            if ($faction->isMember($player->getName()) || $force) {
                $playerRank = $faction->getFactionRole($player);

                if ($playerRank === Faction::LEADER && $faction->getLeader() !== $member || $force) {
                    // Faction promotion
                    $form->addButton(new Button(TextFormat::DARK_GRAY . 'Promote to Leader' . TextFormat::EOL . TextFormat::GRAY . 'Make them the faction leader.', function (Player $player) use ($faction, $member) {
                        $faction->setLeader($member, true);

                        $this->sendFactionMessage($faction, '§b' . $member . ' §ahas been promoted to faction leader.');
                    }));

                    if ($memberRank >= Faction::OFFICER) {
                        $form->addButton(new Button(TextFormat::DARK_GRAY . 'Demote to Member' . TextFormat::EOL . TextFormat::GRAY . 'Make them a faction member.', function (Player $player) use ($faction, $member) {
                            $faction->setMemberRole($member, Faction::MEMBER, true);

                            $this->sendFactionMessage($faction, '§b' . $member . ' §ahas been demoted to a faction member.');
                        }));
                    } elseif ($memberRank === Faction::MEMBER) {
                        $form->addButton(new Button(TextFormat::DARK_GRAY . 'Promote to Officer' . TextFormat::EOL . TextFormat::GRAY . 'Make them a faction officer.', function (Player $player) use ($faction, $member) {
                            $faction->setMemberRole($member, Faction::OFFICER, true);

                            $this->sendFactionMessage($faction, '§b' . $member . ' §ahas been promoted a faction officer.');
                        }));
                    }
                }

                // Kick player from this faction.
                if (($playerRank === Faction::LEADER || ($playerRank === Faction::OFFICER && $memberRank === Faction::MEMBER)) && $member !== $player->getName() || $force) {
                    $form->addButton(new Button(TextFormat::DARK_GRAY . 'Kick' . TextFormat::EOL . TextFormat::GRAY . 'Remove them from the faction.', function (Player $player) use ($faction, $member, $force) {
                        $faction->removeMember($member, true);

                        if (($memberInstance = $player->getServer()->getPlayerExact($member)) instanceof Player) {
                            $memberInstance->sendMessage(Factions::getPrefix() . TextFormat::RED . 'You have been kicked from the ' . $faction->getFactionName() . ($force ? '' : ' faction by §b' . $player->getName()) . '§c.');

                            $this->collectGarbage($memberInstance, $faction);
                        }

                        if (!$force) {
                            $this->sendFactionMessage($faction, '§b' . $player->getName() . ' §6kicked §c' . $member . ' §6from the faction.');
                        }
                    }));
                }
            }

            $form->addButton(new Button(TextFormat::DARK_GRAY . 'Stats' . TextFormat::EOL . TextFormat::GRAY . 'Show player statistics.', function (Player $player) use ($faction, $member, $memberRank, $onBack) {
                Forms::sendStats($player, $member, function (Player $player) use ($faction, $member, $memberRank, $onBack): void {
                    $this->sendMemberMenu($player, $faction, $member, $memberRank, $onBack);
                });
            }));

            $form->addButton(new ImageButton(TextFormat::RED . TextFormat::BOLD . 'Back', ImageButton::IMAGE_TYPE_PATH, 'textures/blocks/barrier', $onBack));

            $form->sendForm();
        }
    }

    public static function sendFactionRenameForm(Player $player, Faction $faction, bool $force, Closure $goBack): void
    {
        if ($faction->getFactionRole($player) !== Faction::LEADER && !$force) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'You must be a leader to do that.');
            return;
        }

        $form = FormManager::createCustomForm($player);
        if ($player->isConnected() && $form === null) {
            return;
        }

        $form->setTitle("Faction Rename Form");
        $form->addElement(new Input("Faction name:", $faction->getFactionName(), '', static function (Player $player, string $data) use ($faction): void {
            if (empty($data)) {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Faction name cannot be empty!");
            } else {
                $faction->updateFactionName($player, trim(preg_replace("/ +/", " ", $data)));
            }
        }, true));
        $form->setCloseClosure($goBack);

        $form->sendForm();
    }

    public static function sendFactionInfoForm(Player $player, Faction $faction, array $entries, Closure $goBack): void
    {
        Await::f2c(function () use ($player, $faction, $entries, $goBack): Generator {
            Database::executeSelect(Database::FACTION_RANKING, ['faction_id' => $faction->getFactionId()], yield, yield Await::REJECT);
            $result = yield Await::ONCE;

            $form = FormManager::createCustomForm($player, $goBack);
            if ($player->isConnected() && $form === null) {
                return;
            }

            $totalFactions = $result[0]['total_rows'];
            $currentRank = $result[0]['ranking'] ?? $totalFactions;

            $members = array_diff($faction->getMembers(), array_merge($faction->getOfficers(), [$faction->getLeader()]));
            $officers = Utils::niceArrayString($faction->getOfficers());

            $alliesNameMod = [];
            foreach ($faction->getAllies() as $allies) {
                $alliesNameMod[] = $allies->getFactionName();
            }
            $alliesName = Utils::niceArrayString($alliesNameMod);

            $officersCount = Utils::niceNumber(count($faction->getOfficers()));
            $membersCount = Utils::niceNumber(count($members));
            $alliesCount = Utils::niceNumber(count($faction->getAllies()));

            $members = !empty($members) ? Utils::niceArrayString($members) : TextFormat::RED . 'None.';
            $officers = !empty($officers) ? $officers : TextFormat::RED . 'None.';
            $allies = !empty($alliesName) ? $alliesName : TextFormat::RED . 'None.';

            $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . "Faction information");
            $form->addElement(new Label("Faction Ranking: " . TextFormat::GREEN . $currentRank . TextFormat::GRAY . '/' . TextFormat::YELLOW . $totalFactions));
            $form->addElement(new Label("Faction Name: " . TextFormat::GREEN . $faction->getFactionName()));
            $form->addElement(new Label("Strength: " . TextFormat::GREEN . $faction->getStrength()));
            $form->addElement(new Label("Leader: " . TextFormat::GREEN . $faction->getLeader()));
            $form->addElement(new Label("Officers ($officersCount): " . TextFormat::YELLOW . $officers));
            $form->addElement(new Label("Members ($membersCount): " . TextFormat::YELLOW . $members));
            $form->addElement(new Label("Allies ($alliesCount): " . TextFormat::YELLOW . $allies));

            $onlineMembers = [];

            /** @var ?PlayerSocialInfo $entry */
            foreach ($entries as $playerName => $entry) {
                if ($entry !== null && str_contains($entry->location, ServerManager::FACTIONS)) {
                    $onlineMembers[] = TextFormat::GREEN . $playerName . TextFormat::WHITE;
                }
            }

            $motd = !empty($faction->getMotd()) ? $faction->getMotd() : '';
            $online = !empty($onlineMembers) ? Utils::niceArrayString($onlineMembers) : TextFormat::RED . 'None';

            $niceCounter = Utils::niceNumber(count($onlineMembers));

            $form->addElement(new Label("Online ($niceCounter): " . $online));
            if (!empty($motd)) {
                $form->addElement(new Label("Motd: " . TextFormat::GRAY . $motd));
            }

            $form->sendForm();
        });
    }

    public function sendFactionHelpMenu(Player $player): void
    {
        // Guideline divided into:
        // - Faction Commands
        // - Faction Guideline
        // - Faction Enchantments
        // - Faction Potions
        // - Faction Statistics

        $form = FormManager::createCustomForm($player);
        if ($form === null || !$player->isConnected()) {
            return;
        }

        // TODO: Refactor this code.
        $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . 'Factions command');
        $label = TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'ally ' . TextFormat::GRAY . '<faction>' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'claim' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'claiminfo' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'create ' . TextFormat::GRAY . '<name>' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'demote ' . TextFormat::GRAY . '<player>' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'deposit ' . TextFormat::GRAY . '<amount>' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'disband' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'home' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'map' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'info' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'setinfo ' . TextFormat::GRAY . '<info>' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'invite ' . TextFormat::GRAY . '<player>' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'kick ' . TextFormat::GRAY . '<player>' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'leader ' . TextFormat::GRAY . '<player>' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'leave' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'overclaim' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'promote ' . TextFormat::GRAY . '<player>' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'help' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'sethome' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'top ' . TextFormat::GRAY . '<strength/balance>' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'unally ' . TextFormat::GRAY . '<faction>' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'unclaim' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'vault' . TextFormat::EOL .
            TextFormat::GRAY . ' - ' . TextFormat::WHITE . '/faction ' . TextFormat::YELLOW . 'withdraw ' . TextFormat::GRAY . '<amount>';

        $form->addElement(new Label($label));
        $form->sendForm();
    }

    // -------------------------------------------------- INVITE CORES -------------------------------------------------

    public function removeInvite(string $invited, int $factionId): void
    {
        $this->invites[$invited] = array_diff($this->invites[$invited], [$factionId]);

        if (count($this->invites[$invited]) === 0) {
            $this->removeInvites($invited);
        }
    }

    /**
     * Invite $invited into sender's faction
     *
     * @param Player $inviter
     * @param Faction $faction
     * @param Player $invited
     */
    public function invitePlayer(Player $inviter, Faction $faction, Player $invited): void
    {
        if ($this->isInFaction($invited)) {
            $inviter->sendMessage('§b' . $invited->getName() . ' §cis already in a faction!');
        } elseif ($this->isInvitedByFaction($faction, $invited)) {
            $inviter->sendMessage('§cYou have already invited §b' . $invited->getName() . '§c to ' . $faction->getFactionName() . '.');
        } elseif (count($faction->getMembers()) >= $faction->getMaxFactionSize()) {
            $inviter->sendMessage('§cYou can\'t invite more than §6' . $faction->getMaxFactionSize() . ' §cto ' . $faction->getFactionName() . '!');
        } else {
            $this->invites[$invited->getName()][] = $faction->getFactionId();

            $inviter->sendMessage('§aInvited §b' . $invited->getName() . ' §ato §b' . $faction->getFactionName() . '§a.');
            $invited->sendMessage('§b' . $inviter->getName() . ' §6has invited you to join the §b' . $faction->getFactionName() . '§6 faction! Open §bFactions Menu §6to accept.');
        }
    }

    /**
     * Check if player is invited by a specific faction
     *
     * @param Faction $inviter
     * @param Player $invited
     *
     * @return bool
     */
    public function isInvitedByFaction(Faction $inviter, Player $invited): bool
    {
        if ($this->isInvited($invited)) {
            return in_array($inviter->getFactionId(), $this->invites[$invited->getName()], true);
        }

        return false;
    }

    /**
     * Check if the player is invited or not
     *
     * @param Player $invited
     *
     * @return bool
     */
    public function isInvited(Player $invited): bool
    {
        return isset($this->invites[$invited->getName()]);
    }

    public function removeInvites(string $invited): void
    {
        unset($this->invites[$invited]);
    }

    public function getInvites(Player $invited): array
    {
        return $this->invites[$invited->getName()] ?? [];
    }

    // ------------------------------------------------- FACTION CORES -------------------------------------------------

    // Cores summary:
    // - getFaction
    // - isInFaction
    // - loadFactionByPlayer
    // - loadFactionPlayerOffline
    // - loadFactionByName
    // - loadFactionById
    // - createFaction
    // - disbandFaction
    // - collectGarbage

    public function getFaction(int|string $factionId): ?Faction
    {
        if (is_string($factionId)) {
            $results = array_filter($this->factions, function (Faction $faction) use ($factionId): bool {
                return strcasecmp($faction->getFactionName(), $factionId) === 0;
            });

            return count($results) > 0 ? $results[array_key_first($results)] : null;
        }

        return $this->factions[$factionId] ?? null;
    }

    public function isInFaction(Player|string $player): bool
    {
        return $this->getPlugin()->getPlayerData()->getInt($player, PlayerData::FACTION_ID) > 0;
    }

    /**
     * Get allies of a given faction id, it will search for any allies in loaded faction entries
     * and return all of them at once.
     *
     * @param int $factionId
     * @return Faction[]
     */
    public function getFactionAlly(int $factionId): array
    {
        return array_filter($this->factions, function (Faction $faction) use ($factionId): bool {
            return $faction->isFactionAlly($factionId) && $faction->getFactionId() !== $factionId;
        });
    }

    /**
     * This function will be attempting to load the player faction and store them into the server's
     * memory, the method used were taken from guild's loading/unloading system.
     *
     * @param Player $player
     * @param Closure|null $onComplete
     * @return void
     */
    public function loadFactionByPlayer(Player $player, ?Closure $onComplete = null): void
    {
        Await::f2c(function () use ($player): Generator {
            Database::executeSelect(Database::GET_PLAYER_FACTION_ID, [
                "player" => $player->getName()
            ], yield, yield Await::REJECT);

            $rows = yield Await::ONCE;
            $faction = null;

            $hasFaction = count($rows) > 0;
            if ($hasFaction && !isset($this->factions[$rows[0]['faction_id']])) {
                $this->loadFactionById($rows[0]['faction_id'], yield);

                /** @var Faction|null $faction */
                $faction = yield Await::ONCE;
            } else if ($hasFaction) {
                $faction = $this->factions[$rows[0]['faction_id']];
            }

            if ($faction !== null && $player->isConnected()) {
                $playerData = $this->getPlugin()->getPlayerData();
                $playerData->setValue($player, PlayerData::FACTION_ID, $faction->getFactionId());

                if (!isset($this->factions[$faction->getFactionId()])) {
                    $this->factions[$faction->getFactionId()] = $faction;
                }

                $this->reference[$faction->getFactionId()][$player->getName()] = true;

                return $faction;
            }

            return null;
        }, $onComplete, function (Throwable $error) use ($onComplete) {
            $this->getPlugin()->getLogger()->logException($error);

            if ($onComplete !== null) {
                $onComplete(null);
            }
        });
    }

    /**
     * Attempt to load a faction by the player's name. If there is no faction linked to this player,
     * then the faction is non-existence. The faction will be going to return the object
     * existed in the server's memory if available.
     *
     * @param string $playerName
     * @param Closure|null $onComplete
     * @return void
     */
    public function loadFactionPlayerOffline(string $playerName, ?Closure $onComplete = null): void
    {
        Await::f2c(function () use ($playerName): Generator {
            Database::executeSelect(Database::GET_PLAYER_FACTION_ID, [
                'player' => $playerName
            ], yield, yield Await::REJECT);

            $rows = yield Await::ONCE;
            $faction = null;

            $hasFaction = count($rows) > 0;
            if ($hasFaction && !isset($this->factions[$rows[0]['faction_id']])) {
                $this->loadFactionById($rows[0]['faction_id'], yield);

                /** @var Faction|null $faction */
                $faction = yield Await::ONCE;
            } else if ($hasFaction) {
                $faction = $this->factions[$rows[0]['faction_id']];
            }

            return $faction;
        }, $onComplete, function (Throwable $error) use ($onComplete) {
            $this->getPlugin()->getLogger()->logException($error);

            if ($onComplete !== null) {
                $onComplete(null);
            }
        });
    }

    /**
     * Attempt to load a faction by its name, the faction name must exist in order to return
     * a faction entry, null if it is non-existence. The faction will be going to return the object
     * existed in the server's memory if available.
     *
     * @param string $factionName
     * @param Closure|null $onComplete
     * @return void
     */
    public function loadFactionByName(string $factionName, ?Closure $onComplete = null): void
    {
        Await::f2c(function () use ($factionName): Generator {
            Database::executeSelect(Database::GET_FACTIONS_BY_NAME, [
                'faction_name' => $factionName
            ], yield, yield Await::REJECT);

            $rows = yield Await::ONCE;
            $faction = null;

            $hasFaction = count($rows) > 0;
            if ($hasFaction && !isset($this->factions[$rows[0]['faction_id']])) {
                $this->loadFactionById($rows[0]['faction_id'], yield);

                /** @var Faction|null $faction */
                $faction = yield Await::ONCE;
            } else if ($hasFaction) {
                $faction = $this->factions[$rows[0]['faction_id']];
            }

            return $faction;
        }, $onComplete, function (Throwable $error) use ($onComplete) {
            $this->getPlugin()->getLogger()->logException($error);

            if ($onComplete !== null) {
                $onComplete(null);
            }
        });
    }

    /**
     * This function will always load the faction data off the database and will
     * not be using the referenced object created in this server.
     *
     * @param int $factionId
     * @param Closure $onComplete
     *
     * @phpstan-param Closure(Faction|null) : void $onComplete
     */
    public function loadFactionById(int $factionId, Closure $onComplete): void
    {
        Await::f2c(function () use ($factionId): Generator {
            $start = microtime(true);

            // Heavily optimized queries, now it will only execute these 3 queries in order
            // to load the faction id.
            Database::executeSelect(Database::GET_FACTION_METADATA, [
                'faction_id' => $factionId,
            ], yield, yield Await::REJECT);
            Database::executeSelect(Database::GET_FACTIONS_MEMBER, [
                'faction_id' => $factionId
            ], yield, yield Await::REJECT);
            Database::executeSelect(Database::GET_FACTIONS_ALLIES, [
                'faction_id' => $factionId,
            ], yield, yield Await::REJECT);

            $result = yield Await::ALL;

            $rows1 = $result[0];
            $rows2 = $result[1];
            $rows3 = $result[2];

            if (empty($rows1)) {
                return null;
            }

            [
                'faction_name' => $name,
                'leader' => $leader,
                'home_coords' => $faction_home,
                'motd' => $motd,
                'strength' => $strength,
                'balance' => $balance,
                'permissions' => $permissions,
                'auto_kick_days' => $autoKickDays,
                'auto_kick_deaths' => $autoKickDeaths
            ] = $rows1[0];

            $homeLocation = null;

            if (!empty($faction_home)) {
                try {
                    $homes = json_decode($faction_home, true, 512, JSON_THROW_ON_ERROR);
                    $homeLocation = new FactionLocation($homes[0], $homes[1], $homes[2], $homes[3], $homes[4]);
                } catch (JsonException) {
                }
            }

            $faction = new Faction($this->getPlugin(), $factionId, $name, $leader, $homeLocation, $motd, $balance, $strength, json_decode($permissions, true), $autoKickDays, $autoKickDeaths);

            // Members data
            foreach ($rows2 as ['faction_role' => $role, 'player_name' => $plName]) {
                $faction->addMember($plName, $role);
            }

            // Allies data
            foreach ($rows3 as ['faction_id' => $factionAllyId, 'allied_name' => $factionName]) {
                $faction->addAllies(new OfflineFaction($factionAllyId, $factionName));
            }

            $this->getPlugin()->getLogger()->info("Faction id $factionId is loaded into server, took " . round((microtime(true) - $start) * 1000, 2) . "ms to execute.");

            return $faction;
        }, $onComplete, function (Throwable $error) use ($onComplete) {
            $this->getPlugin()->getLogger()->logException($error);
            $onComplete(null);
        });
    }

    /**
     * Attempt to create a faction for the player.
     *
     * @param Player $player
     * @param string $factionName
     * @param Closure|null $onComplete
     *
     * @phpstan-param Closure(Faction|null) : void $onComplete
     */
    public function createFaction(Player $player, string $factionName, ?Closure $onComplete = null): void
    {
        Await::f2c(function () use ($player, $factionName): Generator {
            $factionName = TextFormat::clean($factionName);
            if (strlen($factionName) > 16) {
                $player->sendMessage(TextFormat::RED . "Your faction name can't be more than 16 characters long.");
                return null;
            }

            $this->checkFactionName($player, $factionName, yield);
            yield Await::ONCE;

            Database::executeSelect(Database::GET_FACTIONS_BY_NAME, [
                'faction_name' => $factionName,
            ], yield, yield Await::REJECT);

            $rows = yield Await::ONCE;
            if (count($rows) > 0) {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . $factionName . " is an existing faction.");
                return null;
            }

            Database::executeInsert(Database::FACTION_CREATE, [
                'faction_name' => $factionName,
                'leader' => $player->getName(),
            ], yield Await::RESOLVE_MULTI, yield Await::REJECT);

            [$factionId] = yield Await::ONCE;

            Database::executeInsert(Database::ADD_FACTION_PLAYER, [
                'faction_id' => $factionId,
                'player_name' => $player->getName(),
                'faction_role' => Faction::LEADER,
            ], yield, yield Await::REJECT);

            yield Await::ONCE;

            if ($player->isConnected()) {
                $this->getPlugin()->getPlayerData()->setValue($player, PlayerData::FACTION_ID, $factionId);

                $faction = new Faction($this->getPlugin(), $factionId, $factionName, $player->getName());

                if (!isset($this->factions[$faction->getFactionId()])) {
                    $this->factions[$faction->getFactionId()] = $faction;
                }

                $this->reference[$faction->getFactionId()][$player->getName()] = true;

                $pmManager = $this->getPlugin()->getPlayerManager();
                $pmManager->updateFactionScoreboard($player);

                GroupManager::updateNameTag($player);

                $player->sendMessage(MMOPlugin::getPrefix() . 'You have created the ' . $factionName . ' faction.');

                return $faction;
            }

            return null;
        }, $onComplete, function (Throwable $error) use ($player) {
            $this->getPlugin()->getLogger()->logException($error);

            if ($player->isConnected()) {
                $player->sendMessage(Translator::getTranslationPlayer($player, 'db.error'));
            }
        });
    }

    /**
     * Attempt to disband a faction then we notify other servers that
     * this faction is now invalid. This faction must be loaded in the server first
     * before they can continue this operation.
     *
     * @param Player $player
     * @param Faction $faction
     * @param bool $force
     */
    public function disbandFaction(Player $player, Faction $faction, bool $force = false): void
    {
        if ($faction->isDatabaseLocked()) {
            $player->sendMessage(TextFormat::RED . "Unable to disband faction now, please wait until previous operation to complete.");
            return;
        }

        if (!isset($this->factions[$faction->getFactionId()]) && !$force) {
            $player->sendMessage(TextFormat::RED . "Unable to disband faction, data is not loaded from database.");
            return;
        }

        $factionId = $faction->getFactionId();

        $players = [];
        foreach ($faction->getMembers() as $member) {
            $target = Server::getInstance()->getPlayerExact($member);

            if ($target instanceof Player) {
                $this->collectGarbage($target, $faction);

                if ($faction->getLeader() !== $target->getName()) {
                    $target->sendMessage('§b' . $faction->getLeader() . ' §6has disbanded the §b' . $faction->getFactionName() . ' §6faction.');
                }

                $players[] = $target;
            }

            $faction->removeMember($member, updateTags: true);
        }

        $scoreboard = $this->getPlugin()->getEssentials()->getServerData()->getScoreBoard();
        $scoreboard->setLines($players, [12 => '', 13 => '', 14 => '', 15 => ''], true);

        if (!Factions::isBadlands()) {
            $this->getPlugin()->getClaimManager()->purgeClaims($factionId);
        }

        unset($this->factions[$factionId]);
        Await::f2c(function () use ($faction, $player): Generator {
            $faction->removeAllyDisband(yield);

            /** @var int[] $allies */
            $allies = yield Await::ONCE;

            foreach ($allies as $allyId) {
                // Resynchronize allies strength again, this doesn't decrease them, instead it will update their strength.
                $allyFaction = $this->getPlugin()->getFactionManager()->getFaction($allyId);

                $allyFaction?->subtractFromStrength(0);
                $allyFaction?->removeAlly($faction);
            }

            Database::executeChangeRaw("DELETE FROM factions WHERE faction_id = ?", [
                $faction->getFactionId()
            ], yield, yield Await::REJECT);

            yield Await::ONCE;

            $player->sendMessage(Factions::getPrefix() . TextFormat::RED . 'Faction ' . $faction->getFactionName() . ' has been disbanded.');

            $this->getPlugin()->getEventEmitter()->broadcastEvent($faction, EventEmitter::EVENT_FACTION_DISBAND, $allies);
        }, catches: Database::getFailClosure());
    }

    /**
     * Player referenced garbage collector, designed to release all factions referenced to the given player
     * after/when the player creating a faction, joining a faction and leaving a faction. The result value of
     * this reference should and always be 0.
     *
     * @param Player $player
     * @param Faction $faction
     */
    public function collectGarbage(Player $player, Faction $faction): void
    {
        unset($this->reference[$faction->getFactionId()][$player->getName()]);

        if (count($this->reference[$faction->getFactionId()]) <= 0) {
            $this->getPlugin()->getLogger()->warning("Cyclic reference for faction " . $faction->getFactionId() . " has exhausted, removing from cache");

            foreach ($this->invites as $invited => $invites) {
                if (in_array($faction->getFactionId(), $invites, true)) {
                    $player = Server::getInstance()->getPlayerExact($invited);

                    if ($player->isConnected()) {
                        $player->sendMessage(TextFormat::RED . "Your invite from the " . $faction->getFactionName() . " faction has expired.");
                    }

                    $this->removeInvite($invited, $faction->getFactionId());
                }
            }

            unset($this->factions[$faction->getFactionId()]);
            unset($this->reference[$faction->getFactionId()]);
        }
    }

    // ------------------------------------------------- FACTION CORES -------------------------------------------------

    /**
     * Check the faction name for restricted terms
     *
     * @param Player $player
     * @param string $name
     * @param callable $onValid
     *
     * @return void
     */
    public function checkFactionName(Player $player, string $name, callable $onValid): void
    {
        foreach (['owner', '0wner', '0wn3r', 'admin', '4dmin', '4dm1n', 'mod', 'm0d', 'crew', 'cr3w', 'ultra', 'ultr4', 'legend', 'l3gend', 'leg3nd', 'l3g3nd', 'titan', 't1tan', 'tit4n', 't1t4n'] as $term) {
            if (stripos($name, $term) !== false) {
                $player->sendMessage(Translator::getTranslationPlayer($player, 'staff.impersonating'));
                return;
            }
        }

        $this->getPlugin()->getEssentials()->getPlayerManager()->checkName($player, $name, $onValid);
    }

    /**
     * Send a message to all faction members, including/excluding allies.
     *
     * @param Faction|int $faction
     * @param string $message
     * @param bool $allies
     */
    public function sendFactionMessage(Faction|int $faction, string $message, bool $allies = false): void
    {
        if ($faction instanceof Faction) {
            $faction = $faction->getFactionId();
        }

        $this->getPlugin()->getEventEmitter()->broadcastChatMessages($message, $faction, $allies);
    }

    public function getMap(MMOPlayer $player, int $width, int $height, int $inDegrees): array
    {
        $claimManager = $this->getPlugin()->getClaimManager();
        $pos = $player->getPosition();
        $centerPs = new Position($pos->getFloorX(), 0, $pos->getFloorZ(), $pos->getWorld());

        $centerFaction = 'Wilderness';
        if (($claim = $claimManager->getClaimInPosition($pos)) !== null) {
            $centerFaction = $claim->getFactionName();
        }

        $halfWidth = (int)floor($width / 2);
        $halfHeight = (int)floor($height / 2);

        $bottomRightPs = new Position($centerPs->getFloorX() + abs($halfWidth), 0, $centerPs->getFloorZ() + abs($halfHeight), $centerPs->getWorld());
        $asciiCompass = Utils::getASCIICompass($inDegrees, TextFormat::RED, TextFormat::GRAY);
        $height--;
        $fList = [];
        $chrIdx = 0;
        $overflown = false;
        $colors = [TextFormat::RED, TextFormat::GOLD, TextFormat::GREEN, TextFormat::LIGHT_PURPLE, TextFormat::YELLOW];

        $map = [];
        for ($dz = 0; $dz < $height; $dz++) {
            $row = '';
            for ($dx = 0; $dx < $width; $dx++) {
                if ($dz === $halfHeight && $dx === $halfWidth) {
                    $row = TextFormat::AQUA . '+' . $row;
                    continue;
                }
                if (!$overflown && $chrIdx >= strlen(Utils::getMapBlock())) {
                    $overflown = true;
                }
                $hereFaction = null;
                $pos = new Position($bottomRightPs->x + (-$dx), 0, $bottomRightPs->z + (-$dz), $bottomRightPs->getWorld());
                if (($claim = $claimManager->getClaimInPosition($pos)) !== null) {
                    $hereFaction = $claim->getFactionName();
                }
                $contains = in_array($hereFaction, $fList, true);
                if ($hereFaction === null) {
                    $row = TextFormat::GRAY . '-' . $row;
                } elseif (!$contains && $overflown) {
                    $row = TextFormat::WHITE . '-' . $row;
                } else {
                    if (!$contains) {
                        $color = $colors[$chrIdx++];
                        $fList[$color] = $hereFaction;
                    } else {
                        $color = array_search($hereFaction, $fList, true);
                    }
                    $row = $color . '-' . $row;
                }
            }
            $line = $row;
            $OPlayer = TextFormat::AQUA . '+';
            if ($dz === 9) {
                $line = substr($row, 0 * strlen($OPlayer)) . '  ' . $asciiCompass[0];
            }
            if ($dz === 8) {
                $line = substr($row, 0 * strlen($OPlayer)) . '  ' . $asciiCompass[1];
            }
            if ($dz === 7) {
                $line = substr($row, 0 * strlen($OPlayer)) . '  ' . $asciiCompass[2];
            }
            if ($dz === 5) {
                $line = substr($row, 0 * strlen($OPlayer)) . '  §7- §7 Wilderness';
            }
            if ($dz === 4) {
                $line = substr($row, 0 * strlen($OPlayer)) . '  §c- §c Claimed Land';
            }
            if ($dz === 3) {
                $line = substr($row, 0 * strlen($OPlayer)) . '  §b+ §b You';
            }
            $map[] = $line;
        }

        $map = array_reverse($map);
        $map = array_merge([TextFormat::DARK_PURPLE . '______________________.' . TextFormat::DARK_GRAY . '[' . TextFormat::LIGHT_PURPLE . $centerFaction . TextFormat::DARK_GRAY . ']' . TextFormat::DARK_PURPLE . '.______________________'], $map);

        $fRow = '';
        foreach ($fList as $color => $faction) {
            $fRow .= $color . Utils::getMapBlock() . ': ' . $faction . ' ';
        }
        if ($overflown) {
            $fRow .= TextFormat::EOL . MMOPlugin::getPrefix() . TextFormat::RED . "There are too many factions on this map!";
        }

        $fRow = trim($fRow);
        $map[] = $fRow;
        return $map;
    }
}