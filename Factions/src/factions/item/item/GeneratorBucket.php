<?php
/**
 *        ______         _   _
 *       |  ____|       | | (_)
 *  __  _| |__ __ _  ___| |_ _  ___  _ __  ___
 *  \ \/ /  __/ _` |/ __| __| |/ _ \| '_ \/ __|
 *   >  <| | | (_| | (__| |_| | (_) | | | \__ \
 *  /_/\_\_|  \__,_|\___|\__|_|\___/|_| |_|___/
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
use factions\item\enum\GeneratorType;
use libasyncio\blocks\AsyncBlockManager;
use libasyncio\blocks\Selection;
use libMMO\item\SingleCustomItem;
use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\ItemUseResult;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;

class GeneratorBucket extends SingleCustomItem
{
    private GeneratorType $generatorType = GeneratorType::COBBLESTONE;

    /**
     * @return $this
     */
    public function setType(GeneratorType $type) : self{
        $this->generatorType = $type;
        return $this;
    }

    public function getType() : GeneratorType{ return $this->generatorType; }

    public function getGeneratorBlock(): Block
    {
        return match ($this->generatorType) {
            GeneratorType::COBBLESTONE => VanillaBlocks::COBBLESTONE(),
            GeneratorType::BEDROCK => VanillaBlocks::BEDROCK(),
            GeneratorType::OBSIDIAN => VanillaBlocks::OBSIDIAN(),
        };
    }

    public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems): ItemUseResult
    {
        if (Factions::isBadlands()) {
            return ItemUseResult::FAIL;
        }

        $world = $player->getWorld();
        $x = $blockReplace->getPosition()->getFloorX();
        $y = $blockReplace->getPosition()->getFloorY();
        $z = $blockReplace->getPosition()->getFloorZ();

        $blockReplaced = $this->getGeneratorBlock();
        $position = new Position($x, $y, $z, $world);

        $wm = $player->getServer()->getWorldManager();
        $wild = $wm->getWorldByName('wild');

        $faction = Factions::getInstance()->getPlayerData()->getFaction($player);
        if ($player->getWorld()->getId() !== $wild->getId() || (($claim = Factions::getInstance()->getClaimManager()->getClaimInPosition($position)) !== null && ($faction === null || $claim->getFactionId() !== $faction->getFactionId()))) {
            return ItemUseResult::FAIL;
        }

        $blockChanged = new Selection();
        while (true) {
            if ($position->getY() > 0 && ($block = $world->getBlockAt($position->getX(), $position->getY(), $position->getZ())) instanceof Air) {
                if (($claim = Factions::getInstance()->getClaimManager()->getClaimInPosition($block->getPosition())) !== null && ($faction === null || $claim->getFactionId() !== $faction->getFactionId())) {
                    break;
                }

                $blockChanged->add($position->getX(), $position->getY(), $position->getZ(), $blockReplaced);
                $position = $position->subtract(0, 1, 0);
            } else {
                break;
            }
        }

        $this->pop();

        AsyncBlockManager::executeSet($blockChanged, $world);

        return ItemUseResult::SUCCESS;
    }
}