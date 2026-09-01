<?php

declare(strict_types=1);

namespace skyblock\utils;

use libforms\elements\Button;
use libforms\SimpleForm;
use libMMO\item\CustomItemRegistry;
use libMMO\item\item\MiniHelperItem;
use libMMO\MMOPlugin;
use libMMO\utils\AwaitUtils;
use NetherGames\NGEssentials\ServerManager;
use pocketmine\block\tile\Container;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;
use skyblock\block\SpawnerBlock;
use skyblock\forms\IslandForm;
use skyblock\islands\Island;
use skyblock\islands\IslandManager;
use skyblock\player\PlayerData;
use skyblock\SkyBlock;
use SOFe\AwaitGenerator\Await;
use function method_exists;

class InvestigationManager extends \libMMO\utils\InvestigationManager
{
    /**
     * Teleport to designated location whereas the player is simply investigating the island.
     *
     * @param Player $player
     * @param Island $island
     * @return void
     */
    public static function teleportToLocation(Player $player, Island $island): void
    {
        $island->setUnloadLock(false);

        $player->setInvisible();
        $player->setAllowFlight(true);
        $player->setFlying(true);

        SkyBlock::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player, $island): void {
            $island->setUnloadLock(true);

            if ($player->isConnected()) {
                // Briefly mark the visiting player on the island so the teleport is attributed to them,
                // then clear it again.
                $island->snooper = $player;
                $player->teleport($island->getSpawnPosition());
                $island->snooper = null;
            }
        }), 20);
    }

    protected function addButtons(SimpleForm $form, string $fullPlayerName): void
    {
        parent::addButtons($form, $fullPlayerName);

        $form->addButton(new Button(TextFormat::DARK_GRAY . 'Teleport to island' . TextFormat::EOL . TextFormat::DARK_AQUA . 'Go to their home', function (Player $player) use ($fullPlayerName) {
            Await::f2c(function () use ($player, $fullPlayerName) {
                $skyBlock = SkyBlock::getInstance();

                $skyBlock->getIslandManager()->loadIslandData($fullPlayerName, yield);

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

                IslandForm::sendIslandSummary($player, $island, $callback1, $callback2);

                /** @var int $id */
                [$id] = yield Await::ONCE;

                if ($id === 0) {
                    $skyBlock->getIslandManager()->getIslandLocation($fullPlayerName, yield Await::RESOLVE_MULTI);

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
                        IslandForm::sendIslandTransfer($player, $serverUniqueId, $fullPlayerName, $skyBlock, true);
                    } else if (!SkyBlock::getInstance()->isAgora()) {
                        $islandLoaded = $skyBlock->getIslandManager()->getIslandByOwner($fullPlayerName);

                        // Island is not loaded in this server and the server id is valid.
                        // We can spawn this island in the server here.
                        if ($islandLoaded === null) {
                            $player->sendMessage(MMOPlugin::getPrefix() . "Loading the island in this server, please wait.");

                            $skyBlock->getIslandManager()->loadIsland($fullPlayerName, yield Await::RESOLVE_MULTI);

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
                                    self::teleportToLocation($player, $island);
                                    break;
                            }
                        } else {
                            self::teleportToLocation($player, $islandLoaded);
                        }
                    } else {
                        $skyBlock->getPlayerData()->setValue($player, PlayerData::TARGET_ISLAND_ADMIN, $fullPlayerName);

                        $skyBlock->getPlayerManager()->transferPlayer($player, ServerManager::GAME_TYPE_SKYLAND, $serverUniqueId);
                    }
                } else {
                    $this->sendBaseForm($player, $fullPlayerName);
                }
            });
        }));

        $form->addButton(new Button(TextFormat::DARK_GRAY . 'Search current island' . TextFormat::EOL . TextFormat::DARK_AQUA . 'Current island', static function (Player $player) {
            foreach ($player->getWorld()->getLoadedChunks() as $chunk) {
                foreach ($chunk->getTiles() as $tile) {
                    if ($tile instanceof Container) {
                        $inv = $tile->getInventory();

                        $contents = [];
                        foreach ($inv->getContents() as $item) {
                            if ($item->getBlock() instanceof SpawnerBlock || $item->getTypeId() === CustomItemRegistry::MONEY_POUCH()->getTypeId() || $item instanceof MiniHelperItem) {
                                if (isset($contents[$item->getCustomName()])) {
                                    $contents[$item->getCustomName()] += $item->getCount();
                                } else {
                                    $contents[$item->getCustomName()] = $item->getCount();
                                }
                            }
                        }

                        if (count($contents) !== 0) {
                            $tilePos = $tile->getPosition();

                            if (method_exists($tile, 'getDefaultName')) {
                                foreach ($contents as $customName => $count) {
                                    $player->sendMessage("§aFound a " . $tile->getDefaultName() . " with " . $count . "x " . $customName . " at {$tilePos->getX()}:{$tilePos->getY()}:{$tilePos->getZ()}");
                                }
                            } else {
                                foreach ($contents as $customName => $count) {
                                    $player->sendMessage("§aFound an inventory with " . $count . "x " . $customName . " at {$tilePos->getX()}:{$tilePos->getY()}:{$tilePos->getZ()}");
                                }
                            }
                        }
                    }
                }
            }
        }));
    }

    protected function resetProgress(Player $staff, string $playerName): void
    {
        Await::f2c(function () use ($playerName, $staff) {
            Database::executeGenericRaw("DELETE FROM player_data WHERE player = ?", [$playerName], yield);

            yield Await::ONCE;

            $staff->sendMessage(SkyBlock::getPrefix() . TextFormat::RED . "Player progress has been wiped.");
        }, catches: Database::getFailClosure());
    }
}