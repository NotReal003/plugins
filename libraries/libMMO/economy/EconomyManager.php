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

namespace libMMO\economy;

use libMMO\challenges\ChallengeSet;
use libMMO\forms\EconomyForms;
use libMMO\MMOPlugin;
use libMMO\player\PlayerData;
use libMMO\utils\BaseClass;
use NetherGames\NGEssentials\entity\custom\EntityNPC;
use NetherGames\NGEssentials\NGEssentials;
use pocketmine\entity\Location;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use RuntimeException;
use function number_format;
use const DIRECTORY_SEPARATOR;

abstract class EconomyManager extends BaseClass
{
    // TODO: Rewrite how EconomyManager works, this requires a lot of changes and possibly a lot of testing
    public const ATM_RESOURCE_FOLDER = 'skins' . DIRECTORY_SEPARATOR . 'objects' . DIRECTORY_SEPARATOR . 'atm' . DIRECTORY_SEPARATOR;
    public const MAX_MONEY_AMOUNT = 999999999;

    /** @var true[] */
    private $transactionLock = [];

    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct($plugin);

        if (($ess = $this->getPlugin()->getEssentials()) !== null) {
            $this->spawnMoneyStorage($ess);
        }
    }

    public function spawnMoneyStorage(NGEssentials $ess): void
    {
        $plugin = $this->getPlugin();

        $ess->getEntityManager()->addEntity(new EntityNPC($this->getATMLocation(), TextFormat::BOLD . TextFormat::GOLD . 'Money Storage', 'ng:mmo_npc_money_storage', static function (Player $player) use ($plugin) {
            EconomyForms::sendATMMenu($player, -1, $plugin);
        }));
    }

    abstract public function getATMLocation(): Location;

    public function withdrawFromBank(Player $player, int $amount, ?callable $callable = null): void
    {
        $this->getPlugin()->getPlayerData()->loadValue($player->getName(), PlayerData::BANK_MONEY, function (int $bankMoney) use ($player, $amount, $callable) {
            if ($player->isConnected()) {
                if ($bankMoney >= $amount) {
                    $playerName = $player->getName();

                    if ($amount + $this->getPlugin()->getPlayerData()->getInt($player, PlayerData::PLAYER_MONEY) > self::MAX_MONEY_AMOUNT) {
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't have over $" . number_format(self::MAX_MONEY_AMOUNT) . ' in total into your purse.');
                        return;
                    }

                    if (!$this->getPlugin()->getPlayerManager()->canDoTransactions($player)) {
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't do transaction right now!");
                        return;
                    }

                    $this->setBankMoney($playerName, $newAmount = $bankMoney - $amount, function () use ($player, $playerName, $newAmount, $amount, $bankMoney, $callable): void {
                        if ($player->isConnected() && $this->getPlugin()->getPlayerManager()->canDoTransactions($player)) {
                            $this->increasePlayerMoney($playerName, $amount, function () use ($player, $newAmount, $amount, $callable): void {
                                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . 'You withdrew ' . TextFormat::GOLD . '$' . number_format($amount) . TextFormat::GREEN . ' from your bank balance.');

                                if ($callable !== null) {
                                    $callable($newAmount);
                                }
                            });

                            if ($amount > 500000000) {
                                $this->getPlugin()->getLoggerStream()->add('**SUSPICIOUS ACTIVITY** - ' . $player->getName() . ' withdrew ' . $amount . ' coins from their bank balance into their purse');
                            }
                        } else {
                            $this->setBankMoney($playerName, $bankMoney);
                        }
                    });
                } else {
                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You don't have enough money in your bank balance!");
                }
            }
        });
    }

    public function increasePlayerMoney(string $playerName, int $amount, ?callable $callable = null, ?callable $onCancel = null, bool $ignoreLock = false): void
    {
        if ($amount <= 0) {
            throw new RuntimeException($playerName . ' amount was smaller than or equal to 0');
        }

        $playerData = $this->getPlugin()->getPlayerData();
        $player = $this->getPlugin()->getServer()->getPlayerExact($playerName);

        if ($player === null || !$player->isConnected()) {
            if (isset($this->transactionLock[$playerName])) {
                return;
            }
            $this->transactionLock[$playerName] = true;

            $playerData->loadValue($playerName, PlayerData::BANK_MONEY, function (int $bankMoney) use ($playerName, $amount, $callable) {
                if ($bankMoney + $amount <= self::MAX_MONEY_AMOUNT) {
                    $this->setBankMoney($playerName, $bankMoney + $amount, $callable, true);
                } else {
                    $this->setBankMoney($playerName, self::MAX_MONEY_AMOUNT, $callable, true);
                }
            });
        } elseif (($totalAmount = $playerData->getInt($player, PlayerData::PLAYER_MONEY) + $amount) <= self::MAX_MONEY_AMOUNT) {
            $playerData->addInt($player, PlayerData::PLAYER_MONEY, $amount);

            if ($callable !== null) {
                $callable();
            }
        } else {
            if (isset($this->transactionLock[$playerName])) {
                return;
            }
            $this->transactionLock[$playerName] = true;

            $playerData->setValue($player, PlayerData::PLAYER_MONEY, self::MAX_MONEY_AMOUNT);

            $residue = $totalAmount - self::MAX_MONEY_AMOUNT;
            $playerData->loadValue($playerName, PlayerData::BANK_MONEY, function (int $bankMoney) use ($player, $residue, $callable) {
                if ($bankMoney + $residue <= self::MAX_MONEY_AMOUNT) {
                    $this->setBankMoney($player->getName(), $bankMoney + $residue, $callable, true);
                } else {
                    $this->setBankMoney($player->getName(), self::MAX_MONEY_AMOUNT, $callable, true);

                    if ($player->isConnected()) {
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't have over $" . number_format(self::MAX_MONEY_AMOUNT) . ' in total.');
                    }
                }
            });
        }
    }

    public function depositToBank(Player $player, int $amount, ?callable $callable = null): void
    {
        $this->getPlugin()->getPlayerData()->loadValue($player->getName(), PlayerData::BANK_MONEY, function (int $bankMoney) use ($player, $amount, $callable) {
            if ($player->isConnected()) {
                $playerName = $player->getName();

                if ($bankMoney + $amount > self::MAX_MONEY_AMOUNT) {
                    $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't have over $" . number_format(self::MAX_MONEY_AMOUNT) . ' in total into your bank.');
                    return;
                }

                $this->reducePlayerMoney($playerName, $amount, function () use ($player, $playerName, $amount, $bankMoney, $callable) {
                    $this->setBankMoney($playerName, $newAmount = $bankMoney + $amount, function () use ($player, $newAmount, $amount, $callable) {
                        if ($player->isConnected()) {
                            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . 'You deposited ' . TextFormat::GOLD . '$' . number_format($amount) . TextFormat::GREEN . ' into your bank balance.');

                            foreach ($this->getPlugin()->getPlayerChallengeManager()->getActiveChallenges($player) as $challenge) {
                                $challenge->increaseProgress($player, ChallengeSet::BANK_STORE_MONEY, null, $amount);
                            }
                        }

                        if ($amount > 500000000) {
                            $this->getPlugin()->getLoggerStream()->add('**SUSPICIOUS ACTIVITY** - ' . $player->getName() . ' deposited ' . $amount . ' coins from their purse into their bank balance');
                        }

                        if ($callable !== null) {
                            $callable($newAmount);
                        }
                    });
                });
            }
        });
    }

    public function reducePlayerMoney(string $playerName, int $amount, ?callable $callable = null, ?callable $onCancel = null, bool $ignoreLock = false): void
    {
        if ($amount <= 0) {
            throw new RuntimeException($playerName . ' amount was smaller than or equal to 0');
        }

        $playerData = $this->getPlugin()->getPlayerData();
        $player = $this->getPlugin()->getServer()->getPlayerExact($playerName);

        if ($player === null || !$player->isConnected()) {
            if (isset($this->transactionLock[$playerName])) {
                return;
            }
            $this->transactionLock[$playerName] = true;

            $playerData->loadValue($playerName, PlayerData::BANK_MONEY, function (int $bankMoney) use ($playerName, $amount, $callable) {
                if ($bankMoney >= $amount) {
                    $this->setBankMoney($playerName, $bankMoney - $amount, $callable, true);
                }
            });
        } elseif ($this->getPlugin()->getPlayerManager()->canDoTransactions($player)) {
            $pocketMoney = $playerData->getInt($player, PlayerData::PLAYER_MONEY);

            if ($pocketMoney >= $amount) {
                $playerData->addInt($player, PlayerData::PLAYER_MONEY, -$amount);

                if ($callable !== null) {
                    $callable();
                }
            } elseif ($onCancel !== null) {
                $onCancel();
            } else {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You don't have enough money in your purse!");
            }
        } else {
            if ($onCancel !== null) {
                $onCancel();
            }

            $player->sendMessage(TextFormat::RED . "You can't do transaction right now!");
        }
    }

    private function setBankMoney(string $playerName, int $value, ?callable $callable = null, bool $ignoreLock = false): void
    {
        if (!$ignoreLock) {
            if (isset($this->transactionLock[$playerName])) {
                return;
            }

            $this->transactionLock[$playerName] = true;
        }

        $playerData = $this->getPlugin()->getPlayerData();
        $playerData->setValue($playerName, PlayerData::BANK_MONEY, $value);
        $playerData->saveValue($playerName, PlayerData::BANK_MONEY, function (int $insertId, int $affectedRows) use ($playerName, $callable) {
            unset($this->transactionLock[$playerName]);

            if ($callable !== null) {
                $callable();
            }
        });
    }
}