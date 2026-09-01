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

namespace factions\player\bounty;

use factions\Factions;
use factions\utils\Database;
use Generator;
use libforms\elements\Button;
use libforms\elements\Input;
use libforms\elements\Label;
use libforms\FormManager;
use libMMO\MMOPlugin;
use libMMO\player\PlayerData;
use libnetsys\protocol\packets\datatype\PlayerLocationResponseEntry;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\player\social\PlayerSocialInfo;
use NetherGames\NGEssentials\player\social\SocialManager;
use NetherGames\NGEssentials\ServerManager;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;
use function array_keys;
use function str_contains;

class BountyHunter
{
    public const FILTER_NONE = 0;
    public const FILTER_HIGHEST_BOUNTIES = 1;
    public const FILTER_LOWEST_BOUNTIES = 2;
    const FILTER_DISPLAYS = [
        self::FILTER_NONE => 'None',
        self::FILTER_HIGHEST_BOUNTIES => 'Highest bounties first',
        self::FILTER_LOWEST_BOUNTIES => 'Lowest bounties first',
    ];

    /** @var array */
    public static array $localStorage = [];

    public function sendBountyHunter(Player $player, int $pageNumber = 1, int $filter = self::FILTER_NONE, array $filterData = [], ?InvMenu $invMenu = null): void
    {
        // Possible $filterData values
        // 'touch' -> For the touchscreen players (Mobile)
        // 'factionId' -> For the faction filters.
        // 'factionName' -> Well it is obvious?
        Await::f2c(function () use ($player, $filter, $pageNumber, $filterData, $invMenu): Generator {
            /** @var BountyEntry[] $players */
            $players = [];

            $query = 'SELECT player, streak, bounty, (SELECT faction_name FROM factions WHERE faction_id = (SELECT faction_id FROM faction_members WHERE faction_members.player_name = player_data.player)) as factionName FROM player_data WHERE bounty > 0';
            if ($filter === BountyHunter::FILTER_NONE) {
                $endQuery = '';
            } else if ($filter === BountyHunter::FILTER_HIGHEST_BOUNTIES) {
                $endQuery = 'ORDER BY bounty DESC';
            } else if ($filter === BountyHunter::FILTER_LOWEST_BOUNTIES) {
                $endQuery = 'ORDER BY bounty';
            } else {
                return;
            }

            $queryFacName = null;
            if (isset($filterData['factionId'])) {
                Database::executeSelectRaw('SELECT player_name FROM faction_members WHERE faction_id = ?', [$filterData['factionId']], yield, yield Await::REJECT);
                Database::executeSelectRaw('SELECT faction_name FROM factions WHERE faction_id = ?', [$filterData['factionId']], yield, yield Await::REJECT);

                /** @var array $queries */
                $queries = yield Await::ALL;

                $rows = $queries[0];
                $factionRows = $queries[1];

                if (count($rows) > 0) {
                    $query .= ' AND (';

                    $arguments = [];
                    foreach ($rows as ["player_name" => $playerName]) {
                        $query .= " player = ? OR";
                        $arguments[] = $playerName;
                    }

                    $query = substr($query, 0, strlen($query) - 3) . ')';

                    Database::executeSelectRaw($query . ' ' . $endQuery, $arguments, yield, yield Await::REJECT);
                    foreach (yield Await::ONCE as ["player" => $playerName, "bounty" => $bounty, "streak" => $killstreak, "factionName" => $factionName]) {
                        $players[$playerName] = new BountyEntry($playerName, $bounty, $killstreak, $factionName);
                    }
                }

                $queryFacName = $factionRows[0]['faction_name'];
            } else {
                Database::executeSelectRaw($query . ' ' . $endQuery, [], yield, yield Await::REJECT);

                /** @var array $rows */
                $rows = yield Await::ONCE;

                foreach ($rows as ["player" => $playerName, "bounty" => $bounty, "streak" => $killstreak, "factionName" => $factionName]) {
                    $players[$playerName] = new BountyEntry($playerName, $bounty, $killstreak, $factionName);
                }
            }

            if (empty($players)) {
                $player->sendMessage(Factions::getPrefix() . TextFormat::RED . 'Well this is awkward, there is no bounties set on any players. Try checking this again.');
                return;
            }

            SocialManager::requestPlayerInfos(array_keys($players), yield);

            /**
             * @param (?PlayerSocialInfo)[] $results
             * @phpstan-param array<string, PlayerSocialInfo|null> $results
             */
            $results = yield Await::ONCE;

            /** @var BountyEntry[] $onlinePlayers */
            $onlinePlayers = [];

            $serverUniqueId = NGEssentials::getInstance()->getServerManager()->getUniqueId(); // TODO: Implement something like credits algorithm to the player with highest bounties.

            /** @var ?PlayerSocialInfo $entry */
            foreach ($results as $playerName => $entry) {
                if ($entry !== null && str_contains($entry->location, ServerManager::FACTIONS)) {
                    $data = $players[$playerName];
                    unset($players[$playerName]);
                    $data->serverUniqueId = $entry->location;

                    $onlinePlayers[$playerName] = $data;
                }
            }

            // By default, we only sort entries by which is online first
            $useForms = isset($filterData['touch']);

            if ($filter === self::FILTER_NONE) {
                ksort($onlinePlayers);
                ksort($players);
            }

            $targets = array_merge($onlinePlayers, $players);
            $moreEntries = count($targets) > ($useForms ? 10 : 36);

            $totalPages = 1;
            if ($moreEntries) {
                $items = array_chunk($targets, ($useForms ? 10 : 36));
                $pageNumber = min($totalPages = count($items), $pageNumber);
                if ($pageNumber < 1) {
                    $pageNumber = 1;
                }

                $targets = $items[$pageNumber - 1];
            }

            if ($useForms) {
                if (($form = FormManager::createSimpleForm($player)) === null) {
                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Something went wrong while trying to create a form, try again later");
                    return;
                }

                $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . 'Players Bounties.');
                $form->setContent(TextFormat::YELLOW .
                    "Here are the total list of player's bounties in factions." . TextFormat::EOL . TextFormat::EOL .
                    TextFormat::GRAY . 'Current filter: ' . TextFormat::WHITE . (self::FILTER_DISPLAYS[$filter]) .
                    ((isset($filterData['factionId']) && $queryFacName !== null) ? TextFormat::EOL . TextFormat::GRAY . 'Faction: ' . TextFormat::WHITE . $queryFacName : ""));

                $form->addButton(new Button(TextFormat::DARK_GRAY . 'Set a bounty', function (Player $player) {
                    $this->openBountyForm($player);
                }));

                foreach ($targets as $bounty) {
                    $form->addButton(new Button(TextFormat::DARK_GRAY . $bounty->playerName . TextFormat::EOL . TextFormat::DARK_AQUA . '[' . number_format($bounty->bounty) . ' coins]', function (Player $player) use ($serverUniqueId, $pageNumber, $bounty, $filter, $filterData): void {
                        $factionName = $bounty->factionName === null ? TextFormat::RED . "No faction" : $bounty->factionName;

                        $isInServer = $serverUniqueId === $bounty->serverUniqueId ? TextFormat::GREEN . 'this server.' : $bounty->serverUniqueId . '.';

                        if (($form = FormManager::createModalForm($player)) === null) {
                            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Something went wrong while trying to create a form, try again later");
                            return;
                        }

                        $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . 'Player Bounties Info.');
                        $form->setContent('' .
                            TextFormat::GRAY . 'Player: ' . TextFormat::WHITE . $bounty->playerName . TextFormat::EOL .
                            TextFormat::GRAY . 'Bounty: ' . TextFormat::WHITE . number_format($bounty->bounty) . ' coins' . TextFormat::EOL .
                            TextFormat::GRAY . 'Kill streaks: ' . TextFormat::WHITE . $bounty->killStreaks . ' streaks' . TextFormat::EOL .
                            TextFormat::GRAY . 'Faction: ' . TextFormat::WHITE . $factionName . TextFormat::EOL . TextFormat::EOL .
                            TextFormat::GRAY . ($bounty->serverUniqueId === null ? TextFormat::RED . 'The player is currently offline.' : TextFormat::GREEN . 'Currently online in ' . $isInServer));
                        $form->setButton1(new Button(TextFormat::DARK_GRAY . 'Return back', function (Player $player) use ($pageNumber, $filter, $filterData): void {
                            $this->sendBountyHunter($player, $pageNumber, $filter, $filterData);
                        }));
                        $form->setButton2(new Button(TextFormat::RED . 'Close'));
                        $form->sendForm();
                    }));
                }

                if ($totalPages !== 1) {
                    $page = TextFormat::DARK_PURPLE . '[Page ' . TextFormat::DARK_AQUA . $pageNumber . TextFormat::DARK_PURPLE . ' of ' . TextFormat::DARK_AQUA . $totalPages . TextFormat::DARK_PURPLE . ']';
                    if ($pageNumber === 1) {
                        $form->addButton(new Button(TextFormat::DARK_GRAY . 'Next entry' . TextFormat::EOL . $page, function (Player $player) use ($pageNumber, $filter, $filterData): void {
                            $this->sendBountyHunter($player, $pageNumber + 1, $filter, $filterData);
                        }));
                    } else {
                        $form->addButton(new Button(TextFormat::DARK_GRAY . 'Previous entry' . TextFormat::EOL . $page, function (Player $player) use ($pageNumber, $filter, $filterData): void {
                            $this->sendBountyHunter($player, $pageNumber - 1, $filter, $filterData);
                        }));

                        if ($pageNumber < $totalPages) {
                            $form->addButton(new Button(TextFormat::DARK_GRAY . 'Next entry', function (Player $player) use ($pageNumber, $filter, $filterData): void {
                                $this->sendBountyHunter($player, $pageNumber + 1, $filter, $filterData);
                            }));
                        }
                    }
                }

                $form->addButton(new Button(TextFormat::DARK_GRAY . 'Switch filter', function (Player $player) use ($filter, $filterData): void {
                    $this->openFilterForm($player, $filter, $filterData);
                }));

                $form->sendForm();
            } else {
                $contents = [];
                foreach ($targets as $bounty) {
                    $factionName = $bounty->factionName === null ? TextFormat::RED . "No faction" : TextFormat::GRAY . $bounty->factionName;

                    $isInServer = $serverUniqueId === $bounty->serverUniqueId ? TextFormat::GREEN . TextFormat::BOLD . 'Online in this server.' . TextFormat::RESET . '    ' . TextFormat::YELLOW . TextFormat::RESET : TextFormat::YELLOW . TextFormat::BOLD . 'Online in ' . TextFormat::GRAY . $bounty->serverUniqueId;

                    $item = VanillaItems::PAPER();
                    $item->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GOLD . $bounty->playerName)->setLore([
                        '',
                        TextFormat::RESET . TextFormat::GRAY . 'Kill streaks: ' . TextFormat::WHITE . $bounty->killStreaks . ' streaks',
                        TextFormat::RESET . TextFormat::GRAY . 'Bounty: ' . TextFormat::WHITE . number_format($bounty->bounty) . ' coins',
                        TextFormat::RESET . TextFormat::GRAY . 'Faction: ' . TextFormat::WHITE . $factionName,
                        '',
                        TextFormat::RESET . ($bounty->serverUniqueId === null ? TextFormat::RED . TextFormat::BOLD . 'Currently offline.' . TextFormat::RESET . '        ' . TextFormat::YELLOW . TextFormat::RESET : $isInServer),
                    ]);
                    $contents[] = $item;
                }

                $created = false;
                if ($invMenu === null) {
                    $invMenu = InvMenu::create('libmmo:double');
                    $invMenu->setName('Bounty Hunter');
                    $created = true;
                }

                $filterItem = VanillaBlocks::HOPPER()->asItem()->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::DARK_GRAY . 'Change Filter')->setLore([
                    '',
                    TextFormat::RESET . TextFormat::YELLOW . 'Current filter: ' . TextFormat::WHITE . (self::FILTER_DISPLAYS[$filter]),
                    '',
                    TextFormat::RESET . TextFormat::GRAY . 'Click to switch filter.'
                ]);

                if (isset($filterData['factionId']) && $queryFacName !== null) {
                    $filterItem->setLore([
                        '',
                        TextFormat::RESET . TextFormat::YELLOW . 'Current filter: ' . TextFormat::WHITE . (self::FILTER_DISPLAYS[$filter]),
                        TextFormat::RESET . TextFormat::YELLOW . 'Faction: ' . TextFormat::WHITE . $queryFacName,
                        '',
                        TextFormat::RESET . TextFormat::GRAY . 'Click to switch filter.'
                    ]);
                }

                $invMenu->getInventory()->setContents($contents + [
                        45 => $this->getBalanceItem($player),
                        48 => $totalPages > 1 ? VanillaBlocks::WOOL()->setColor(DyeColor::RED())->asItem()->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::WHITE . 'Previous Page')->setLore([
                            '',
                            TextFormat::RESET . TextFormat::GRAY . 'You are currently on page ' . TextFormat::WHITE . $pageNumber . '/' . TextFormat::WHITE . $totalPages,
                            '',
                            TextFormat::RESET . TextFormat::GRAY . 'Click to navigate to the previous page.'
                        ]) : VanillaItems::AIR(),
                        49 => $filterItem,
                        50 => $totalPages > 1 ? VanillaBlocks::WOOL()->setColor(DyeColor::GREEN())->asItem()->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::WHITE . 'Next Page')->setLore([
                            '',
                            TextFormat::RESET . TextFormat::GRAY . 'You are currently on page ' . TextFormat::WHITE . $pageNumber . '/' . TextFormat::WHITE . $totalPages,
                            '',
                            TextFormat::RESET . TextFormat::GRAY . 'Click to navigate to the next page.'
                        ]) : VanillaItems::AIR(),
                        53 => VanillaItems::GOLD_NUGGET()->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GOLD . 'Set a bounty')->setLore([
                            '',
                            TextFormat::RESET . TextFormat::GRAY . 'Click to set a bounty for a player.'
                        ])
                    ]);

                if ($created) {
                    $invMenu->setListener(InvMenu::readonly(function (DeterministicInvMenuTransaction $transaction) use ($pageNumber, $filter, $filterData, $invMenu): void {
                        $player = $transaction->getPlayer();
                        $itemClicked = $transaction->getItemClicked();
                        $action = $transaction->getAction();

                        switch ($action->getSlot()) {
                            case 48:
                                if ($itemClicked->getTypeId() === ItemTypeIds::PAPER) {
                                    $this->sendBountyHunter($player, $pageNumber - 1, $filter, $filterData, $invMenu);
                                }
                                break;
                            case 49:
                                $this->openFilterForm($player, $filter, $filterData);
                                break;
                            case 50:
                                if ($itemClicked->getTypeId() === ItemTypeIds::PAPER) {
                                    $this->sendBountyHunter($player, $pageNumber + 1, $filter, $filterData, $invMenu);
                                }
                                break;
                            case 53:
                                $this->openBountyForm($player);
                                break;
                        }
                    }));

                    $invMenu->send($player);
                }
            }
        }, catches: Database::getFailClosure());
    }

    private function openFactionFilters(Player $player, int $filter, array $filterData): void
    {
        Await::f2c(function () use ($player, $filter, $filterData) {
            if (($form = FormManager::createCustomForm($player)) === null) {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Something went wrong while trying to create a form, try again later");
                return;
            }

            $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . "Factions Selection.");
            $form->addElement(new Label(TextFormat::YELLOW . "Please enter your specified faction name."));
            $form->addElement(new Input(TextFormat::GRAY . "Faction name", "", "", yield Await::RESOLVE_MULTI));
            $form->sendForm();

            /**
             * @var Player $player
             * @var string $data
             */
            [$player, $data] = yield Await::ONCE;

            Database::executeSelect(Database::GET_FACTIONS_SPECIFIC, ['factionName' => $data], yield);

            $rows = yield Await::ONCE;

            if (empty($rows)) {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'A faction with that name does not exists, please check again.');
            } else if (count($rows) === 1) {
                $filterData['factionId'] = $rows[0]['faction_id'];
                $filterData['factionName'] = $rows[0]['faction_name'];

                $this->sendBountyHunter($player, 1, $filter, $filterData);
            } else {
                if (($form = FormManager::createSimpleForm($player)) === null) {
                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Something went wrong while trying to create a form, try again later");
                    return;
                }

                $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . 'Factions Selection.');
                $form->setContent(TextFormat::YELLOW . 'Please select one of these factions from your search results.');
                foreach ($rows as ['faction_id' => $factionId, 'faction_name' => $factionName, 'strength' => $strength]) {
                    $form->addButton(new Button(TextFormat::DARK_GRAY . $factionName . TextFormat::EOL . TextFormat::DARK_AQUA . '[' . $strength . ' strength]', function (Player $player) use ($filter, $factionName, $factionId, $filterData): void {
                        $filterData['factionId'] = $factionId;
                        $filterData['factionName'] = $factionName;

                        $this->sendBountyHunter($player, 1, $filter, $filterData);
                    }));
                }

                $form->sendForm();
            }
        });
    }

    private function openFilterForm(Player $player, int $filter, array $filterData): void
    {
        $player->removeCurrentWindow();

        Factions::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player, $filter, $filterData): void {
            $form = FormManager::createSimpleForm($player);
            if ($form === null) {
                return;
            }

            $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . "Filter Bounties Form.");
            $form->setContent(TextFormat::GRAY .
                'Current filter: ' . TextFormat::WHITE . (self::FILTER_DISPLAYS[$filter] ?? 'Unknown') .
                ((isset($filterData['factionName']) ? TextFormat::EOL . TextFormat::GRAY . 'Faction: ' . TextFormat::WHITE . $filterData['factionName'] : "")));
            $form->addButton(new Button(TextFormat::DARK_GRAY . "Clear all filters", function (Player $player) use ($filterData): void {
                unset($filterData['factionId']);
                unset($filterData['factionName']);

                $this->sendBountyHunter($player, 1, self::FILTER_NONE, $filterData);
            }));
            $form->addButton(new Button(TextFormat::DARK_GRAY . "Highest Bounties first", function (Player $player) use ($filterData): void {
                $this->sendBountyHunter($player, 1, self::FILTER_HIGHEST_BOUNTIES, $filterData);
            }));
            $form->addButton(new Button(TextFormat::DARK_GRAY . "Lowest Bounties first", function (Player $player) use ($filterData): void {
                $this->sendBountyHunter($player, 1, self::FILTER_LOWEST_BOUNTIES, $filterData);
            }));
            $form->addButton(new Button(TextFormat::DARK_GRAY . "Filter by Faction Name", function (Player $player) use ($filter, $filterData): void {
                $this->openFactionFilters($player, $filter, $filterData);
            }));

            $form->sendForm();
        }), 5);
    }

    private function openBountyForm(Player $player): void
    {
        $player->removeCurrentWindow();

        Factions::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player): void {
            $form = FormManager::createCustomForm($player);
            if ($form === null) {
                return;
            }

            $title = TextFormat::YELLOW . 'Please enter the name of the player to set a bounty.';

            $form->setTitle(Factions::getPrefix() . TextFormat::DARK_GRAY . "Bounty Form.");
            $form->addElement(new Label($title));
            $form->addElement(new Input(TextFormat::GRAY . "Player's name", "ChickenDinner3344"));
            $form->addElement(new Input(TextFormat::GRAY . "Bounty", "1"));
            $form->setCallable(function (Player $player, ?array $data = null) {
                if ($data === null) {
                    $this->sendBountyHunter($player);
                } else {
                    if ($player->getNetworkSession()->getProtocolId() == ProtocolInfo::PROTOCOL_1_21_70) {
                        $playerName = $data[0];
                        $amount = (int)$data[1];
                    } else {
                        $playerName = $data[1];
                        $amount = (int)$data[2];
                    }

                    Server::getInstance()->dispatchCommand($player, 'bounty set "' . $playerName . '" ' . $amount);
                }
            });

            $form->sendForm();
        }), 5);
    }

    protected function getBalanceItem(Player $player): Item
    {
        return VanillaBlocks::SUNFLOWER()->asItem()
            ->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GOLD . 'Your Balance')
            ->setLore([
                '',
                TextFormat::RESET . TextFormat::AQUA . 'Coins: ' . TextFormat::WHITE . number_format(Factions::getInstance()->getPlayerData()->getInt($player, PlayerData::PLAYER_MONEY)),
            ]);
    }
}