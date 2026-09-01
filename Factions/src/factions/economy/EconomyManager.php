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

namespace factions\economy;

use factions\utils\Database;
use libMMO\MMOPlugin;
use libMMO\player\PlayerData as MMOPlayerData;
use NetherGames\NGEssentials\NGEssentials;
use pocketmine\entity\Location;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use RuntimeException;
use SOFe\AwaitGenerator\Await;

class EconomyManager extends \libMMO\economy\EconomyManager
{
    public const MAX_MONEY_AMOUNT = 999999999999999;

    public const MODE_INCREASE = 0;
    public const MODE_DECREASE = 1;

    /** @var true[] */
    private array $transactionLock = [];

    public function spawnMoneyStorage(NGEssentials $ess): void
    {
        // NOOP. Do we really need ATMs in factions?
    }

    public function getATMLocation(): Location
    {
        throw new RuntimeException("Bank are disabled in factions.");

    }

    public function withdrawFromBank(Player $player, int $amount, ?callable $callable = null): void
    {
        throw new RuntimeException("Bank are disabled in factions.");
    }

    public function depositToBank(Player $player, int $amount, ?callable $callable = null): void
    {
        throw new RuntimeException("Bank are disabled in factions.");
    }

    public function increasePlayerMoney(string $playerName, int $amount, ?callable $callable = null, ?callable $onCancel = null, bool $ignoreLock = false): void
    {
        if ($amount <= 0) {
            throw new RuntimeException($playerName . ' amount was smaller than or equal to 0');
        }

        $this->callTransaction($playerName, $amount, self::MODE_INCREASE, $callable, $onCancel, $ignoreLock);
    }

    public function reducePlayerMoney(string $playerName, int $amount, ?callable $callable = null, ?callable $onCancel = null, bool $ignoreLock = false): void
    {
        if ($amount <= 0) {
            throw new RuntimeException($playerName . ' amount was smaller than or equal to 0');
        }

        $this->callTransaction($playerName, $amount, self::MODE_DECREASE, $callable, $onCancel, $ignoreLock);
    }

    private function callTransaction(string $playerName, int $amount, int $mode, ?callable $callable = null, ?callable $onCancel = null, bool $ignoreLock = false): void
    {
        Await::f2c(function () use ($playerName, $amount, $mode, $callable, $onCancel, $ignoreLock) {
            if (isset($this->transactionLock[$playerName . "-" . $mode]) && !$ignoreLock) {
                if ($onCancel !== null) {
                    $onCancel(true);
                }
            } else {
                if (!$ignoreLock) {
                    $this->transactionLock[$playerName . "-" . $mode] = true;
                }

                Database::executeSelect(Database::PLAYER_ECONOMY_TRANSACTION, [
                    'player' => $playerName,
                    'balance' => $amount,
                    'mode' => $mode
                ], yield, yield Await::REJECT);

                $rows = yield Await::ONCE;

                if (!$ignoreLock) {
                    unset($this->transactionLock[$playerName . "-" . $mode]);
                }

                if (count($rows) > 0) {
                    ['balance' => $balance, 'result' => $result] = $rows[0];

                    $this->getPlugin()->getPlayerData()->setValue($playerName, MMOPlayerData::PLAYER_MONEY, $balance, load: true);

                    if ($result === 0) {
                        if ($onCancel !== null) {
                            $onCancel(false);
                        }

                        $player = $this->getPlugin()->getServer()->getPlayerExact($playerName);
                        if ($player !== null && $player->isConnected()) {
                            if ($mode === self::MODE_INCREASE) {
                                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't have over " . number_format(self::MAX_MONEY_AMOUNT) . ' coins in total.');
                            } else {
                                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You don't have enough coins in your balance!");
                            }
                        }
                    } else {
                        if ($callable !== null) {
                            $callable();
                        }
                    }
                }
            }
        });
    }
}