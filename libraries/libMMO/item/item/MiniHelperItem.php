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
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder
 */

namespace libMMO\item\item;

use Generator;
use libMMO\item\ItemStorage;
use libMMO\item\SingleCustomItem;
use libMMO\MMOPlugin;
use pocketmine\block\Block;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\item\ItemUseResult;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;

class MiniHelperItem extends SingleCustomItem
{
    public const JOB_TYPE = 'jobType';

    public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems): ItemUseResult
    {
        if (($customBlockData = $this->getCustomBlockData()) !== null) {
            $itemManager = MMOPlugin::getInstance()->getItemManager();

            if ($itemManager->canUseMiniHelper($player)) {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You can't place a mini-helper here.");
                return ItemUseResult::FAIL;
            }

            $blockData = clone $customBlockData;
            $jobType = $blockData->getInt(self::JOB_TYPE);
            $blockData->removeTag(self::JOB_TYPE);

            $itemClone = clone $this;
            $itemClone->count = $this->count;

            $placeLocation = Location::fromObject($blockReplace->getPosition()->add(0.5, 0, 0.5), $player->getWorld(), $player->getLocation()->getYaw());

            Await::f2c(function () use ($player, $jobType, $blockData, $itemClone, $itemManager, $placeLocation): Generator {
                ItemStorage::isValidAndRemove($this, yield Await::RESOLVE_MULTI);

                /**
                 * @var int $code
                 * @var Item|null $item
                 */
                [$code, $item] = yield Await::ONCE;

                switch ($code) {
                    case ItemStorage::ITEM_VALIDATED:
                        if (!$itemManager->addHelper($player, $jobType, $placeLocation, $blockData)) {
                            $origin = $itemClone->getNamedTag()->getString(ItemStorage::ORIGIN, '');

                            ItemStorage::createValidationId($itemClone, $origin, yield);
                            $item = yield Await::ONCE;

                            if (!$player->isConnected()) {
                                return;
                            }

                            $player->getInventory()->addItem($item);
                        }
                        break;
                    case ItemStorage::ITEM_INVALID:
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Your helper is invalid, this incident has been reported.");
                        break;
                    case ItemStorage::ITEM_INVALID_ID:
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Your helper is being used in other island.");
                        break;
                    case ItemStorage::EXECUTION_FAILED:
                        $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "There was an error when trying to add a helper.");
                        $player->getInventory()->addItem($item);
                        break;
                }
            });

            return ItemUseResult::SUCCESS;
        }

        return ItemUseResult::FAIL;
    }
}