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

namespace skyblock\forms;

use Closure;
use Generator;
use libforms\elements\Button;
use libforms\elements\ImageButton;
use libforms\elements\Toggle;
use libforms\FormManager;
use libMMO\MMOPlugin;
use libMMO\utils\AdventureSettingsObject;
use libMMO\utils\AwaitUtils;
use libMMO\utils\Permissions as MMOPermissions;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\ServerManager;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;
use skyblock\entities\island\IslandNPC;
use skyblock\islands\feature\boss\BossLevelup;
use skyblock\islands\Island;
use skyblock\islands\IslandManager;
use skyblock\player\PlayerData;
use skyblock\SkyBlock;
use skyblock\utils\Database;
use skyblock\utils\InvestigationManager;
use SOFe\AwaitGenerator\Await;
use function array_merge;
use function array_sum;
use function number_format;
use const PHP_INT_MAX;

class IslandForm
{
    public const WELCOME_FORM = 11;

    public static function generateStaticForms(SkyBlock $skyBlock): void
    {
        self::generateWelcomeForm($skyBlock);
    }

    public static function generateWelcomeForm(SkyBlock $skyBlock): void
    {
        $form = FormManager::createModalForm();

        if ($form !== null) {
            $form->setTitle('Welcome');
            $form->setContent('Welcome to NetherGames Skyblock!');

            $form->setButton1(new Button('Create an island', static function (Player $player) use ($skyBlock) {
                self::sendIslandCreationForm($player, $skyBlock);
            }));
            $form->setButton2(new Button("I'll create an island later"));
        }

        FormManager::saveStaticForm($form, self::WELCOME_FORM);
    }

    public static function sendIslandCreationForm(Player $player, SkyBlock $skyBlock): void
    {
        $form = FormManager::createSimpleForm($player);

        if ($form !== null) {
            $form->setTitle('Create an island');

            foreach (Island::SKY_BLOCK_DATA as $id => $data) {
                if (($permission = $data[Island::MAP_PERMISSION]) === '' || $player->hasPermission($permission)) {
                    $form->addButton(new Button(TextFormat::YELLOW . $data[Island::MAP_NAME] . TextFormat::EOL . TextFormat::GRAY . $data[Island::MAP_DESC], static function (Player $player) use ($skyBlock, $id) {
                        $skyBlock->getIslandManager()->createIsland($player, $id);
                    }));
                }
            }
            $form->addButton(new ImageButton(TextFormat::RED . 'Exit', ImageButton::IMAGE_TYPE_PATH, 'textures/blocks/barrier'));

            $form->sendForm();
        }
    }

    public static function sendIslandManager(Player $player, Island $island, SkyBlock $skyBlock): void
    {
        $form = FormManager::createSimpleForm($player);

        if ($form !== null) {
            $form->setTitle('Island Menu');

            $form->addButton(new Button(TextFormat::YELLOW . 'Preferences' . TextFormat::EOL . TextFormat::GRAY . 'Island settings', static function (Player $player) use ($island, $skyBlock) {
                self::sendPreferences($player, $island, $skyBlock);
            }));
            $form->addButton(new Button(TextFormat::YELLOW . 'Members' . TextFormat::EOL . TextFormat::GRAY . 'Manage island members & perms', static function (Player $player) use ($island, $skyBlock) {
                self::sendMembersForm($player, $island, $skyBlock);
            }));

            $upgradeLevel = $island->getXpLevelSpec()->getNextLevel();
            if ($upgradeLevel !== null) {
                if ($skyBlock->getIslandManager()->getIslandLevelManager()->getLevelupByWorld($player->getWorld()) === null) {
                    $form->addButton(new Button(TextFormat::YELLOW . 'Upgrades' . TextFormat::EOL . TextFormat::GRAY . 'Upgrade island to level ' . $upgradeLevel->getId() . ' (' . TextFormat::GREEN . '$' . number_format($upgradeLevel->getPrice()) . TextFormat::GRAY . ')',
                        static function (Player $player) use ($island, $upgradeLevel, $skyBlock) {
                            $form = FormManager::createModalForm($player);

                            if ($form !== null) {
                                $form->setTitle('Upgrade Island');

                                $lw = $upgradeLevel->getAreaLengthWidth();
                                if ($lw !== PHP_INT_MAX) {
                                    $form->setContent("You're upgrading your island to level " . $upgradeLevel->getId() . '. This will cost ' . TextFormat::GREEN . '$' . number_format($upgradeLevel->getPrice()) . TextFormat::RESET . ", and you'll be able to build in a " . $lw . ' x ' . $lw . ' area around the spawn after the upgrade.');
                                } else {
                                    $form->setContent("You're upgrading your island to level " . $upgradeLevel->getId() . '. This will cost ' . TextFormat::GREEN . '$' . number_format($upgradeLevel->getPrice()) . TextFormat::RESET . ", and you'll be able to build without restrictions.");
                                }
                                $form->setButton1(new Button(TextFormat::GREEN . 'Confirm', static function (Player $player) use ($skyBlock, $island, $upgradeLevel): void {

                                    $form = FormManager::createModalForm($player);

                                    if ($form !== null) {
                                        $form->setTitle('Confirm Upgrade Fight');

                                        $form->setContent('You and anyone currently on your island will be teleported to an arena and will fight a random boss. If you win, your island will be upgraded. If you lose, you will not be refunded your money (' . TextFormat::GREEN . '$' . number_format($upgradeLevel->getPrice()) . TextFormat::RESET . '). Each player only has one life and equipment dropped on death can be picked up by other teammates.');

                                        $form->setButton1(new Button(TextFormat::GREEN . 'Confirm', static function () use ($island, $player, $upgradeLevel, $skyBlock) {
                                            $skyBlock->getEconomyManager()->reducePlayerMoney($player->getName(), $upgradeLevel->getPrice(), static function () use ($island): void {
                                                new BossLevelup($island);
                                            });
                                        }));
                                        $form->setButton2(new Button(TextFormat::RED . 'Exit'));

                                        $form->sendForm();
                                    }

                                }));
                                $form->setButton2(new Button(TextFormat::RED . 'Cancel'));
                                $form->sendForm();
                            }
                        }
                    ));
                }
            }
            $form->addButton(new Button(TextFormat::YELLOW . 'Island NPC' . TextFormat::EOL . TextFormat::GRAY . 'Move to your location', static function (Player $player) use ($island) {
                /** @var World $world */
                $world = $island->getWorld();

                if ($player->getWorld() === $world) {
                    $set = false;

                    foreach ($world->getEntities() as $entity) {
                        if ($entity instanceof IslandNPC) {
                            if ($set) {
                                if (!$entity->isFlaggedForDespawn()) {
                                    $entity->flagForDespawn();
                                }
                            } else {
                                $entity->teleport($player->getLocation());
                                $player->sendMessage(TextFormat::GREEN . 'Your island NPC has been moved!');
                                $set = true;
                            }
                        }
                    }

                    if (!$set) {
                        $island->spawnNPC();
                    }
                } else {
                    $player->sendMessage(TextFormat::RED . 'You must be on your island to move the NPC.');
                }
            }));
            $form->addButton(new Button(TextFormat::YELLOW . 'Set Island Spawn' . TextFormat::EOL . TextFormat::GRAY . 'Set island spawn point', static function (Player $player) use ($island) {
                if ($player->getWorld() === $island->getWorld()) {
                    $island->setSpawnPosition($player->getLocation());
                    $player->sendMessage(TextFormat::GREEN . "Your island's spawn point has been set to your position!");
                } else {
                    $player->sendMessage(TextFormat::RED . 'You must be on your island to set its spawn position.');
                }
            }));
            $form->addButton(new Button(TextFormat::RED . 'Delete Island' . TextFormat::EOL . TextFormat::GRAY . 'Remove your island', static function (Player $player) use ($island, $skyBlock) {
                $form = FormManager::createModalForm($player);

                if ($form !== null) {
                    $form->setTitle('Confirm Island Removal');
                    $form->setContent('Are you sure that you want to delete your island? This action is irreversible.');

                    $form->setButton1(new Button(TextFormat::RED . 'Yes', static function (Player $player) use ($island, $skyBlock) {
                        if (($helperAmount = array_sum($island->getHelpers())) > 0) {
                            $player->sendMessage(TextFormat::RED . "You can't delete your island while you still have " . $helperAmount . ' helpers on it.');
                        } else {
                            $skyBlock->getIslandManager()->deleteIsland($island);
                            $skyBlock->getPlayerData()->setValue($player, PlayerData::HAS_ISLAND, false);

                            $skyBlock->getPlayerManager()->transferPlayer($player, ServerManager::GAME_TYPE_AGORA);
                        }
                    }));
                    $form->setButton2(new Button(TextFormat::GREEN . 'No', static function (Player $player) use ($island, $skyBlock) {
                        self::sendIslandManager($player, $island, $skyBlock);
                    }));

                    $form->sendForm();
                }
            }));
            $form->addButton(new ImageButton(TextFormat::RED . TextFormat::BOLD . 'Back', ImageButton::IMAGE_TYPE_PATH, 'textures/blocks/barrier', static function (Player $player) use ($skyBlock) {
                self::sendSkyBlockForm($player, $skyBlock);
            }));

            $form->sendForm();
        }
    }

    public static function sendPreferences(Player $player, Island $island, SkyBlock $skyBlock): void
    {
        $form = FormManager::createCustomForm($player, static function (Player $player) use ($island, $skyBlock) {
            self::sendIslandManager($player, $island, $skyBlock);
        });

        if ($form !== null) {
            $form->setTitle('Preferences');

            $form->addElement(new Toggle('Enable PvP', $island->isPvPEnabled(), static function (Player $player, bool $value) use ($island) {
                if ($value) {
                    $player->sendMessage(TextFormat::GREEN . 'Enabled PvP on your island. Players will now be able to hit each other.');
                } else {
                    $player->sendMessage(TextFormat::RED . 'Disabled PvP on your island. Players will no longer be able to hit each other.');
                }
                $island->setPvPEnabled($value);
            }));
            $form->addElement(new Toggle('Private/Public Island', $island->isIslandPublic(), static function (Player $player, bool $value) use ($island, $skyBlock) {
                if ($value) {
                    $player->sendMessage(TextFormat::GREEN . 'Your island is now public. All other players will be able to visit your island.');
                } else {
                    $player->sendMessage(TextFormat::RED . 'Your island is now private. Other players excluding helpers will no longer be able to visit your island.');

                    $world = $island->getWorld();
                    if ($world instanceof World) {
                        foreach ($world->getPlayers() as $p) {
                            if (!$island->isMember($p) && !MMOPermissions::hasPermission($player)) {
                                $p->sendMessage(TextFormat::AQUA . $player->getName() . TextFormat::RED . ' has made their island private. You have been returned to the lobby.');
                                $skyBlock->getPlayerManager()->transferPlayer($p, ServerManager::GAME_TYPE_AGORA);
                            }
                        }
                    }
                }

                $island->setIslandPublic($value);
            }));

            $form->sendForm();
        }
    }

    public static function sendMembersForm(Player $player, Island $island, SkyBlock $skyBlock): void
    {
        Await::f2c(function () use ($player, $island, $skyBlock) {
            if (($form = FormManager::createSimpleForm($player)) === null) {
                return;
            }

            $goBack = static function (Player $player) use ($island, $skyBlock) {
                self::sendMembersForm($player, $island, $skyBlock);
            };

            $socialManager = $skyBlock->getEssentials()->getPlayerManager()->getSocialManager();
            $friendsManager = $socialManager->getFriendsManager();

            $form->setTitle('Island Members');

            $form->addButton(new ImageButton(TextFormat::GREEN . 'Add friend', ImageButton::IMAGE_TYPE_FACE, $player->getName(), static function (Player $player) use ($friendsManager, $goBack) {
                $friendsManager->sendAddFriendMenu($player, $goBack);
            }));
            $form->addButton(new ImageButton(TextFormat::YELLOW . 'Manage friends', ImageButton::IMAGE_TYPE_FACE, $player->getName(), static function (Player $player) use ($friendsManager, $goBack) {
                $friendsManager->sendFriendList($player, onBack: $goBack);
            }));

            $parsedFriends = [];

            $query = 'SELECT player, xuid FROM player_data';
            if (count($friends = $friendsManager->getFriends($player)) > 0) {
                $query .= ' WHERE';

                $arguments = [];
                foreach ($friends as $playerName) {
                    $query .= " player LIKE ? OR";
                    $arguments[] = $playerName;
                }

                $query = substr($query, 0, strlen($query) - 3);

                Database::executeSelectRaw($query, $arguments, yield, yield Await::REJECT);

                $factionRows = yield Await::ONCE;

                foreach ($factionRows as ["player" => $pl, "xuid" => $xuid]) {
                    $parsedFriends[$pl] = $xuid;
                }
            }

            foreach ($parsedFriends as $member => $xuid) {
                $form->addButton(new ImageButton(TextFormat::YELLOW . $member, ImageButton::IMAGE_TYPE_FACE, $member, static function (Player $player) use ($island, $member, $xuid, $skyBlock) {
                    $form = FormManager::createCustomForm($player, static function (Player $player) use ($island, $skyBlock) {
                        self::sendMembersForm($player, $island, $skyBlock);
                    });

                    if ($form !== null) {
                        $form->setTitle($member . "'s Permissions");

                        $form->addElement(new Toggle('Interaction', $island->hasPermission($xuid, Island::PERMISSION_INTERACT), static function (Player $player, bool $value) use ($island, $xuid, $member) {
                            if ($value) {
                                $player->sendMessage(TextFormat::AQUA . $member . TextFormat::GREEN . ' can now interact with blocks and entities on your island.');
                            } else {
                                $player->sendMessage(TextFormat::AQUA . $member . TextFormat::RED . ' can no longer interact with blocks and entities on your island.');
                            }
                            $island->setPermission($xuid, Island::PERMISSION_INTERACT, $value);
                        }));
                        $form->addElement(new Toggle('Building', $island->hasPermission($xuid, Island::PERMISSION_BUILD), static function (Player $player, bool $value) use ($island, $xuid, $member) {
                            $islandId = $island->getWorld()?->getId();

                            if ($value) {
                                $player->sendMessage(TextFormat::AQUA . $member . TextFormat::GREEN . ' can now build on your island.');

                                if (($m = $player->getServer()->getPlayerExact($member)) !== null && $m->getWorld()->getId() === $islandId) {
                                    AdventureSettingsObject::getInstance()->setBuildingPermission($m, true);
                                }
                            } else {
                                $player->sendMessage(TextFormat::AQUA . $member . TextFormat::RED . ' can no longer build on your island.');

                                if (($m = $player->getServer()->getPlayerExact($member)) !== null && $m->getWorld()->getId() === $islandId) {
                                    AdventureSettingsObject::getInstance()->setBuildingPermission($m, false);
                                }
                            }

                            $island->setPermission($xuid, Island::PERMISSION_BUILD, $value);
                        }));
                        $form->addElement(new Toggle('Inventory Interaction', $island->hasPermission($xuid, Island::PERMISSION_INVENTORY), static function (Player $player, bool $value) use ($island, $xuid, $member) {
                            if ($value) {
                                $player->sendMessage(TextFormat::AQUA . $member . TextFormat::GREEN . ' can now use their inventory on your island.');
                            } else {
                                $player->sendMessage(TextFormat::AQUA . $member . TextFormat::RED . ' can no longer use their inventory on your island.');
                            }
                            $island->setPermission($xuid, Island::PERMISSION_INVENTORY, $value);
                        }));

                        $form->sendForm();
                    }
                }));
            }
            $form->addButton(new ImageButton(TextFormat::RED . TextFormat::BOLD . 'Exit', ImageButton::IMAGE_TYPE_PATH, 'textures/blocks/barrier', static function (Player $player) use ($island, $skyBlock) {
                self::sendIslandManager($player, $island, $skyBlock);
            }));

            if (!$player->isConnected()) {
                return;
            }

            $form->sendForm();
        }, catches: Database::getFailClosure());
    }

    public static function sendSkyBlockForm(Player $player, SkyBlock $skyBlock): void
    {
        $island = $skyBlock->getIslandManager()->getIslandByOwner($player->getName());
        $hasIsland = $skyBlock->getPlayerData()->getBool($player, PlayerData::HAS_ISLAND);

        $form = FormManager::createSimpleForm($player);

        if ($form !== null) {
            $form->setTitle('Island Menu');

            if ($hasIsland) {
                if ($island === null) {
                    $form->addButton(new Button(TextFormat::YELLOW . 'Teleport to your island' . TextFormat::EOL . TextFormat::GRAY . 'Return home', static function (Player $player) use ($skyBlock) {
                        Await::f2c(function () use ($player, $skyBlock): Generator {
                            $skyBlock->getIslandManager()->getIslandLocation($player->getName(), yield Await::RESOLVE_MULTI);

                            /**
                             * @var string|null $serverUniqueId
                             * @var int $status
                             */
                            [$status, $serverUniqueId] = yield Await::ONCE;

                            if (!$player->isConnected()) {
                                return;
                            }

                            if ($skyBlock->isAgora()) {
                                self::sendIslandTransfer($player, $serverUniqueId, '', $skyBlock);
                            } elseif ($status === IslandManager::STATUS_CREATED_NOT_LOADED) {
                                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::YELLOW . 'Loading your island in this server, this may take time.');

                                $skyBlock->getIslandManager()->loadIsland($player->getName(), yield Await::RESOLVE_MULTI);

                                /** @var Island|null $island */
                                [$status, $island] = yield Await::ONCE;

                                if (!$player->isConnected()) {
                                    return;
                                }

                                if ($island === null) {
                                    if ($status === IslandManager::ISLAND_WORLD_LOST) {
                                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Your island were unable to be loaded, please report this issue to an administrator at ngmc.co/d');
                                    } else if ($status === IslandManager::ISLAND_LOADING_DISABLED) {
                                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "This service is temporarily disabled, try again later.");
                                    } else {
                                        $player->sendMessage(TextFormat::RED . 'An unexpected error occurred while teleporting to your island. Please try again later.');
                                    }

                                    $skyBlock->getPlayerManager()->transferPlayer($player, ServerManager::GAME_TYPE_AGORA);
                                } elseif ($player->isConnected()) {
                                    $player->teleport($island->getSpawnPosition());
                                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . 'You have been teleported back to your island.');
                                }
                            } elseif ($status === IslandManager::STATUS_NOT_CREATED || $skyBlock->getEssentials()->getServerManager()->getUniqueId() === $serverUniqueId) {
                                $player->sendMessage(TextFormat::GOLD . 'Please wait while your island is being loaded again.');
                            } else {
                                self::sendIslandTransfer($player, $serverUniqueId, '', $skyBlock);
                            }
                        });
                    }));
                } else {
                    $form->addButton(new Button(TextFormat::YELLOW . 'Teleport to your island' . TextFormat::EOL . TextFormat::GRAY . 'Return home', function (Player $player) use ($island) {
                        if ($island->getWorld() === null || !$island->getWorld()->isLoaded()) {
                            $player->sendMessage(TextFormat::RED . 'Your island is currently being loaded - please be patient!');
                        } else {
                            $player->teleport($island->getSpawnPosition());
                            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . 'You have been teleported back to your island.');
                        }
                    }));
                    $form->addButton(new Button(TextFormat::YELLOW . 'Your island' . TextFormat::EOL . TextFormat::GRAY . 'Manage your island', static function (Player $player) use ($island, $skyBlock) {
                        self::sendIslandManager($player, $island, $skyBlock);
                    }));
                }
            } else {
                $form->addButton(new Button(TextFormat::YELLOW . 'Create an island' . TextFormat::EOL . TextFormat::GRAY . 'Create an island for yourself', static function (Player $player) use ($skyBlock) {
                    self::sendIslandCreationForm($player, $skyBlock);
                }));
            }
            $form->addButton(new Button(TextFormat::YELLOW . 'Associated Islands' . TextFormat::EOL . TextFormat::GRAY . "Islands you're a member of", static function (Player $player) use ($skyBlock) {
                self::sendAssociatedIslands($player, $skyBlock);
            }));
            $form->addButton(new Button(TextFormat::YELLOW . 'Public Islands' . TextFormat::EOL . TextFormat::GRAY . 'All publicly listed islands', static function (Player $player) use ($skyBlock) {
                self::sendPublicIslandsForm($player, $skyBlock);
            }));
            $form->addButton(new ImageButton(TextFormat::RED . TextFormat::BOLD . 'Exit', ImageButton::IMAGE_TYPE_PATH, 'textures/blocks/barrier'));

            $form->sendForm();
        }
    }

    public static function sendIslandTransfer(Player $player, ?string $serverUniqueId, string $ownerName, SkyBlock $skyBlock, bool $isAdmin = false): void
    {
        $serverUniqueId = $serverUniqueId ?? '';

        if ($skyBlock->isAgora()) {
            if ($ownerName !== '') {
                if ($isAdmin) {
                    $skyBlock->getPlayerData()->setValue($player, PlayerData::TARGET_ISLAND_ADMIN, $ownerName);
                } else {
                    $skyBlock->getPlayerData()->setValue($player, PlayerData::TARGET_ISLAND, $ownerName);
                }
            }

            $skyBlock->getPlayerManager()->transferPlayer($player, ServerManager::GAME_TYPE_SKYLAND, $serverUniqueId);
        } else {
            $form = FormManager::createModalForm($player);

            if ($form !== null) {
                $form->setTitle('Transfer Server');

                if ($ownerName === '') {
                    $form->setContent('Your island is currently loaded on another server. Do you want to transfer there?');
                } else {
                    $form->setContent($ownerName . "'s island is loaded on another server. Do you want to transfer there?");
                }

                $form->setButton1(new Button(TextFormat::GREEN . 'Yes', static function (Player $player) use ($serverUniqueId, $ownerName, $skyBlock, $isAdmin) {
                    // Check if the server unique ids provided is this server unique ids.
                    // If yes, then we teleport the player to the island.
                    if ($serverUniqueId === NGEssentials::getInstance()->getServerManager()->getUniqueId()) {
                        $island = $skyBlock->getIslandManager()->getIslandByOwner($ownerName);

                        if ($island === null || $island->getWorld() === null || !$island->getWorld()->isLoaded()) {
                            $player->sendMessage(SkyBlock::getPrefix() . TextFormat::RED . "Looks like the island you are going into is no longer loaded in this server. Try again later.");
                        } else if ($isAdmin) {
                            InvestigationManager::teleportToLocation($player, $island);
                        } else {
                            $player->teleport($island->getSpawnPosition());
                        }
                    } else {
                        if ($ownerName !== '') {
                            if ($isAdmin) {
                                $skyBlock->getPlayerData()->setValue($player, PlayerData::TARGET_ISLAND_ADMIN, $ownerName);
                            } else {
                                $skyBlock->getPlayerData()->setValue($player, PlayerData::TARGET_ISLAND, $ownerName);
                            }
                        }

                        $skyBlock->getPlayerManager()->transferPlayer($player, ServerManager::GAME_TYPE_SKYLAND, $serverUniqueId);
                    }
                }));
                $form->setButton2(new Button(TextFormat::RED . 'No', static function (Player $player) use ($skyBlock) {
                    self::sendSkyBlockForm($player, $skyBlock);
                }));

                $form->sendForm();
            }
        }
    }

    public static function sendAssociatedIslands(Player $player, SkyBlock $skyBlock): void
    {
        $skyBlock->getIslandManager()->getFriendsWithIsland($player, static function (array $friends) use ($player, $skyBlock) {
            $onlineFriends = [];
            $offlineFriends = [];

            foreach ($friends as $friend) {
                if ($skyBlock->getIslandManager()->getIslandByOwner($friend) === null) {
                    $offlineFriends[] = $friend;
                } else {
                    $onlineFriends[] = $friend;
                }
            }

            $friends = array_merge($onlineFriends, $offlineFriends);

            if ($player->isConnected()) {
                $form = FormManager::createSimpleForm($player);

                if ($form !== null) {
                    $form->setTitle("Friends' Islands");

                    foreach ($friends as $ownerName) {
                        $form->addButton(new Button($ownerName . "'s Island", static function (Player $player) use ($ownerName) {
                            self::showIslandData($player, $ownerName);
                        }));
                    }
                    $form->addButton(new ImageButton(TextFormat::RED . TextFormat::BOLD . 'Back', ImageButton::IMAGE_TYPE_PATH, 'textures/blocks/barrier', static function (Player $player) use ($skyBlock) {
                        self::sendSkyBlockForm($player, $skyBlock);
                    }));

                    $form->sendForm();
                }
            }
        });
    }

    public static function sendIslandSummary(Player $player, Island $island, Closure $button1, Closure $button2): void
    {
        $form = FormManager::createModalForm($player);

        if ($form !== null) {
            $form->setTitle($island->getOwner() . "'s Island");

            $form->setContent(
                TextFormat::GREEN . 'Owner: ' . TextFormat::WHITE . $island->getOwner() . TextFormat::EOL .
                TextFormat::GREEN . 'XP Level: ' . TextFormat::WHITE . $island->getXpLevel() .
                ($island->isPvPEnabled() ? TextFormat::EOL . TextFormat::EOL . TextFormat::RED . 'PvP is enabled in this island.' : '')
            );

            $form->setButton1(new Button(TextFormat::GREEN . 'Visit', $button1));
            $form->setButton2(new Button(TextFormat::RED . 'Back', $button2));

            $form->sendForm();
        }
    }

    public static function sendPublicIslandsForm(Player $player, SkyBlock $skyBlock): void
    {
        Database::executeSelect(Database::GET_PUBLIC_ISLANDS, [], static function (array $rows) use ($player, $skyBlock) {
            if ($player->isConnected()) {
                $form = FormManager::createSimpleForm($player);

                if ($form !== null) {
                    $form->setTitle('Public Islands');

                    foreach ($rows as $row) {
                        $owner = $row['owner'];

                        $form->addButton(new ImageButton(TextFormat::YELLOW . $owner . "'s Island", ImageButton::IMAGE_TYPE_FACE, $owner, static function (Player $player) use ($owner) {
                            self::showIslandData($player, $owner);
                        }));
                    }

                    $form->addButton(new ImageButton(TextFormat::RED . TextFormat::BOLD . 'Back', ImageButton::IMAGE_TYPE_PATH, 'textures/blocks/barrier', static function (Player $player) use ($skyBlock) {
                        self::sendSkyBlockForm($player, $skyBlock);
                    }));

                    $form->sendForm();
                }
            }
        });
    }

    public static function sendWelcomeForm(Player $player): void
    {
        if (!$player->isConnected()) {
            return;
        }

        FormManager::sendStaticForm($player, self::WELCOME_FORM);
    }

    private static function showIslandData(Player $player, string $ownerName): void
    {
        Await::f2c(function () use ($player, $ownerName) {
            $skyBlock = SkyBlock::getInstance();

            $skyBlock->getIslandManager()->loadIslandData($ownerName, yield);

            /** @var Island|null $island */
            $island = yield Await::ONCE;

            if (!$player->isConnected()) {
                return;
            }

            if ($island === null) {
                $player->sendMessage(TextFormat::RED . 'The player does not own an island.');
                return;
            }

            [$callback1, $callback2] = AwaitUtils::createOrCallback(yield);

            self::sendIslandSummary($player, $island, $callback1, $callback2);

            /** @var int $id */
            [$id] = yield Await::ONCE;

            if ($id === 0) {
                $skyBlock->getIslandManager()->getIslandLocation($ownerName, yield Await::RESOLVE_MULTI);

                /**
                 * @var int $status
                 * @var string|null $serverUniqueId
                 */
                [$status, $serverUniqueId] = yield Await::ONCE;

                if (!$player->isConnected()) {
                    return;
                }

                if ($status === IslandManager::STATUS_NOT_CREATED) {
                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Are you sure that player has an island?");
                } else if ($status === IslandManager::STATUS_CREATED_AND_LOCKED) {
                    self::sendIslandTransfer($player, $serverUniqueId, $ownerName, $skyBlock);
                } else if (!SkyBlock::getInstance()->isAgora()) {
                    $islandLoaded = $skyBlock->getIslandManager()->getIslandByOwner($ownerName);

                    // Island is not loaded in this server and the server id is valid.
                    // We can spawn this island in the server here.
                    if ($islandLoaded === null) {
                        $player->sendMessage(MMOPlugin::getPrefix() . "Loading the island in this server, please wait.");

                        $skyBlock->getIslandManager()->loadIsland($ownerName, yield Await::RESOLVE_MULTI);

                        /**
                         * @var int $status
                         * @var Island $island
                         */
                        [$status, $island] = yield Await::ONCE;

                        if (!$player->isConnected()) {
                            return;
                        }

                        switch ($status) {
                            case IslandManager::ISLAND_ALREADY_LOADED:
                                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "The island has been loaded at another server, please try again.");
                                break;
                            case IslandManager::ISLAND_LOAD_ERROR:
                                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Island block storage is offline, this error is internal and no data were lost. Please retry in a few minutes.");
                                break;
                            case IslandManager::ISLAND_WORLD_ERROR:
                                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "The island you are going into is suffering from world corruption.");
                                break;
                            case IslandManager::ISLAND_LOADING_DISABLED:
                                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "This service is temporarily disabled, try again later.");
                                break;
                            case IslandManager::ISLAND_NOT_EXISTS:
                                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Are you sure that player has an island?");
                                break;
                            case IslandManager::ISLAND_WORLD_LOST:
                                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "The island you are going into is lost.");
                                break;
                            default:
                                $player->teleport($island->getSpawnPosition());
                                break;
                        }
                    } else {
                        $player->teleport($islandLoaded->getSpawnPosition());
                    }
                } else {
                    $skyBlock->getPlayerData()->setValue($player, PlayerData::TARGET_ISLAND, $ownerName);

                    $skyBlock->getPlayerManager()->transferPlayer($player, ServerManager::GAME_TYPE_SKYLAND, $serverUniqueId);
                }
            } else {
                self::sendAssociatedIslands($player, $skyBlock);
            }
        });
    }
}