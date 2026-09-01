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

namespace libMMO\player;

use Closure;
use Generator;
use libMMO\challenges\PlayerChallengeManager;
use libMMO\challenges\RunningChallenge;
use libMMO\event\EntityArmorChangeEvent;
use libMMO\MMOPlugin;
use libMMO\utils\AwaitUtils;
use libMMO\utils\BaseClass;
use libMMO\utils\Database;
use libMMO\utils\Utils;
use NetherGames\NGEssentials\NGEssentials;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\InventoryListener;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;
use SOFe\AwaitGenerator\Await;

abstract class PlayerManager extends BaseClass
{
    public static function getPlayerAlike(string $player, Closure $onComplete): void
    {
        // For query like this, please make sure 'SELECT player FROM player_data WHERE player LIKE :player_name;' is set in mysql.sql.
        Database::executeSelect(Database::PLAYER_SELECT_ALIKE, ['player_name' => $player], static function (array $rows) use ($onComplete) {
            if (count($rows) === 0) {
                $onComplete([]);
            } else if (count($rows) === 1) {
                $onComplete([$rows[0]['player']]);
            } else {
                $players = [];

                foreach ($rows as ['player' => $player]) {
                    $players[] = $player;
                }

                $onComplete($players);
            }
        });
    }

    public function setupPlayer(Player $player, bool $newPlayer = false): void
    {
        $this->sendScoreboard($player);
        $this->loadOfflineMessages($player);

        $playerData = $this->getPlugin()->getPlayerData();
        if (!$newPlayer) {
            $player->getArmorInventory()->getListeners()->add(new CallbackInventoryListener(
                function (Inventory $inventory, int $slot, Item $oldItem): void {
                    /** @var ArmorInventory $inventory */
                    (new EntityArmorChangeEvent($inventory->getHolder(), $oldItem, $inventory->getItem($slot), $slot))->call();
                },
                function (Inventory $inventory, array $oldItems): void {
                    foreach ($oldItems as $slot => $oldItem) {
                        /** @var ArmorInventory $inventory */
                        (new EntityArmorChangeEvent($inventory->getHolder(), $oldItem, $inventory->getItem($slot), $slot))->call();
                    }
                }
            ));

            /** @var MMOPlayer $player */
            $player->loadInventory($playerData->getString($player, PlayerData::PLAYER_INVENTORY));
            $player->getXpManager()->setCurrentTotalXp($playerData->getInt($player, PlayerData::XP));

            $player->getInventory()->getListeners()->add(new class implements InventoryListener {
                public function onSlotChange(Inventory $inventory, int $slot, Item $oldItem): void
                {
                    $item = $inventory->getItem($slot);

                    if (Utils::isReadOnlyItem($item)) {
                        $inventory->setItem($slot, VanillaItems::AIR());
                    }
                }

                public function onContentChange(Inventory $inventory, array $oldContents): void
                {
                    foreach ($inventory->getContents() as $slot => $item) {
                        if (Utils::isReadOnlyItem($item)) {
                            $inventory->setItem($slot, VanillaItems::AIR());
                        }
                    }
                }
            });

            $this->getPlugin()->getInvestigationManager()->processInventory($player);

            $playerChallengeManager = $this->getPlugin()->getPlayerChallengeManager();
            $serverChallengeManager = $this->getPlugin()->getChallengeManager();
            foreach ($playerData->getArray($player, PlayerData::PROGRESS) as $challengeId => $challengeData) {
                $challenge = $serverChallengeManager->getChallenge($challengeId);
                if ($challenge === null) {
                    continue;
                }
                $runningChallenge = RunningChallenge::fromArray($challenge, $challengeData);
                if (!$runningChallenge->isWithinTime()) {
                    continue; // challenge has not yet started or it has expired
                }
                $playerChallengeManager->addChallenge($player, $runningChallenge);
            }
            $dailyChallenges = $serverChallengeManager->getDailyChallenges();
            shuffle($dailyChallenges);
            for ($i = 0; $i < PlayerChallengeManager::MAX_DAILY_CHALLENGES; $i++) {
                $challenge = array_shift($dailyChallenges);
                if ($challenge === null) {
                    continue;
                }
                if (!$playerChallengeManager->addChallenge($player, new RunningChallenge($challenge))) {
                    break;
                }
            }

            $playerData->unsetValue($player, PlayerData::PLAYER_INVENTORY);
            $playerData->unsetValue($player, PlayerData::XP);
            $playerData->unsetValue($player, PlayerData::PROGRESS);
        }
    }

    public function loadOfflineMessages(Player $player): void
    {
        Await::f2c(function () use ($player): Generator {
            Database::executeSelectRaw("SELECT message FROM offline_storage WHERE player = ?", [
                $player->getName()
            ], yield, yield Await::REJECT);

            $result = yield Await::ONCE;

            if (!empty($result) && $player->isConnected()) {
                Database::executeChangeRaw("DELETE FROM offline_storage WHERE player = ?", [
                    $player->getName()
                ]);

                foreach ($result as ['message' => $message]) {
                    $player->sendMessage(MMOPlugin::getPrefix() . $message);
                }
            }
        }, catches: Database::getFailClosure());
    }

    abstract public function sendScoreboard(Player $player): void;

    public function transferPlayer(Player $player, string $gameType, ?string $serverUniqueId = ''): void
    {
        $serverUniqueId = $serverUniqueId ?? '';

        /** @var MMOPlayer $player */
        if ($player->isCombatTimerActive()) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't transfer to another server while combat tagged.");
            return;
        }

        Await::f2c(function () use ($player, $gameType, $serverUniqueId): Generator {
            AwaitUtils::waitPlayerSpawned($player, yield);
            yield Await::ONCE;

            /** @var NGEssentials $ess */
            $ess = $this->getPlugin()->getEssentials();

            if (!empty($serverUniqueId)) {
                $server = $ess->getServerManager()->getServer($serverUniqueId);
                if ($server === null) {
                    $player->sendMessage(TextFormat::RED . "$serverUniqueId is currently offline");
                } else {
                    $ess->getPlayerManager()->transferPlayer($player, $server);
                }
            } else {
                $ess->getPlayerManager()->transferPlayer($player, $ess->getServerManager()->getServerType(), $gameType);
            }
        });
    }

    public function canDoTransactions(Player $player): bool
    {
        return $this->canFly($player) && !$this->getPlugin()->getEssentials()->getPlayerData()->getBool($player, \NetherGames\NGEssentials\player\PlayerData::TRANSFER);
    }

    public function canFly(Player $player, ?World $world = null): bool
    {
        return true;
    }

    abstract public function updateChallengeScoreboard(Player $player): void;

    abstract public function updateMoneyScoreboard(Player $player): void;

    abstract public function updateBankScoreboard(Player $player, int $amount): void;

    abstract public function updateBountyScoreboard(string $playerName, int $bounty): void;
}