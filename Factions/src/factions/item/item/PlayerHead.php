<?php
/**
 *   _ _ _     __  __ __  __  ____
 *  | (_) |   |  \/  |  \/  |/ __ \
 *  | |_| |__ | \  / | \  / | |  | |
 *  | | | '_ \| |\/| | |\/| | |  | |
 *  | | | |_) | |  | | |  | | |__| |
 *  |_|_|_.__/|_|  |_|_|  |_|\____/
 *
 * Copyright (C) 2016-2023 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder, Studgi
 */

namespace factions\item\item;

use factions\Factions;
use factions\utils\Database;
use Generator;
use GlobalLogger;
use libMMO\item\ItemStorage;
use libMMO\item\SingleCustomItem;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use pocketmine\item\Item;
use pocketmine\item\ItemUseResult;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use poggit\libasynql\result\SqlChangeResult;
use poggit\libasynql\result\SqlSelectResult;
use poggit\libasynql\SqlThread;
use SOFe\AwaitGenerator\Await;
use Throwable;

class PlayerHead extends SingleCustomItem
{
    public const DEDUCTION_PERCENTAGE = 15;

    public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems): ItemUseResult
    {
        $bountyTarget = $this->getNamedTag()->getString('player', '');
        if ($bountyTarget !== '') {
            if ($bountyTarget === $player->getName()) {
                $player->sendPopup(MMOPlugin::getPrefix() . TextFormat::RED . 'You cannot claim your own head!');
                return ItemUseResult::FAIL;
            }

            $xuid = $this->getNamedTag()->getString('xuid', '');
            $hasXuid = !empty($xuid);

            $target = $hasXuid ? $player->getXuid() : $player->getName();
            $victim = $hasXuid ? $xuid : $bountyTarget;

            $itemClone = clone $this;

            Await::f2c(function () use ($player, $bountyTarget, $target, $victim, $hasXuid): Generator {
                ItemStorage::isValidAndRemove($this, yield Await::RESOLVE_MULTI);

                /**
                 * @var int $code
                 * @var Item|null $item
                 */
                [$code, $item] = yield Await::ONCE;

                switch ($code) {
                    case ItemStorage::ITEM_VALIDATED:

                        if (!$hasXuid) {
                            // SELECT and UPDATE using xuid using player.
                            Database::getMySQLDatabase()->executeImplRaw([
                                "UPDATE player_data SET coins = coins - (@bounty := IF(coins < 1000, 0, (" . self::DEDUCTION_PERCENTAGE . " * coins / 100))) WHERE player = ?",
                                "UPDATE player_data SET coins = coins + @bounty WHERE player = ?",
                                "SELECT @bounty AS bounty"
                            ], [[$victim], [$target], []], [
                                SqlThread::MODE_CHANGE,
                                SqlThread::MODE_CHANGE,
                                SqlThread::MODE_SELECT,
                            ], yield, yield Await::REJECT);
                        } else {
                            // SELECT and UPDATE using xuid.
                            Database::getMySQLDatabase()->executeImplRaw([
                                "UPDATE player_data SET coins = coins - (@bounty := IF(coins < 1000, 0, (" . self::DEDUCTION_PERCENTAGE . " * coins / 100))) WHERE xuid = ?",
                                "UPDATE player_data SET coins = coins + @bounty WHERE xuid = ?",
                                "SELECT @bounty AS bounty"
                            ], [[$victim], [$target], []], [
                                SqlThread::MODE_CHANGE,
                                SqlThread::MODE_CHANGE,
                                SqlThread::MODE_SELECT,
                            ], yield, yield Await::REJECT);
                        }

                        /**
                         * @var SqlChangeResult $changeResult1
                         * @var SqlChangeResult $changeResult2
                         * @var SqlSelectResult $selectResult
                         */
                        [$changeResult1, $changeResult2, $selectResult] = yield Await::ONCE;

                        $bounty = $selectResult->getRows()[0]['bounty'] ?? 0;
                        $actualResult = $changeResult1->getAffectedRows() + $changeResult2->getAffectedRows();
                        if ($bounty > 0 && $actualResult > 0) {
                            $playerData = Factions::getInstance()->getPlayerData();
                            $playerData->loadMoneyBalance($player->getName());
                            $playerData->loadMoneyBalance($bountyTarget);

                            if (($playerInstance = Server::getInstance()->getPlayerExact($bountyTarget)) instanceof MMOPlayer) {
                                $playerInstance->sendMessage(MMOPlugin::getPrefix() . "$bounty coins from your balance was claimed with your head.");
                            }

                            if ($player->isConnected()) {
                                $player->sendMessage(MMOPlugin::getPrefix() . "You claimed $bountyTarget's head. " . ((int)$bounty) . " coins has been added to your balance.");
                            }
                        } elseif ($player->isConnected()) {
                            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'The player is low in balance, therefore the player\'s head is not collected.');
                        }
                        break;
                    case ItemStorage::ITEM_INVALID:
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "The player head is invalid, this incident has been reported.");
                        break;
                    case ItemStorage::ITEM_INVALID_ID:
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "The player head does not contain a valid id.");
                        break;
                    case ItemStorage::EXECUTION_FAILED:
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "There was an error when trying to claim the head.");
                        $player->getInventory()->addItem($item);
                        break;
                }
            }, catches: function (Throwable $error) use ($itemClone, $player, $bountyTarget) {
                GlobalLogger::get()->logException($error);

                if ($player->isConnected()) {
                    $player->sendMessage(Factions::getPrefix() . TextFormat::RED . "Failed to claim bounty for $bountyTarget, you have received the claimed head.");
                    $player->getInventory()->addItem($itemClone);
                }
            });

            return ItemUseResult::SUCCESS;
        }

        return ItemUseResult::NONE;
    }
}