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

namespace skyblock\kit;

use libMMO\MMOPlugin;
use NetherGames\NGEssentials\entity\custom\EntityNPC;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function strtolower;

class KitManager extends \libMMO\kit\KitManager
{

    public function __construct(MMOPlugin $instance)
    {
        parent::__construct($instance);

        $this->addKit('Starter', 60 * 60, [
            VanillaItems::LEATHER_CAP(),
            VanillaItems::LEATHER_TUNIC(),
            VanillaItems::LEATHER_PANTS(),
            VanillaItems::LEATHER_BOOTS(),
            VanillaItems::WOODEN_SWORD(),
            VanillaItems::WOODEN_PICKAXE(),
            VanillaItems::WOODEN_AXE(),
            VanillaItems::WOODEN_SHOVEL(),
            VanillaItems::WOODEN_HOE(),
            VanillaBlocks::OAK_LOG()->asItem()->setCount(32),
            VanillaItems::GOLDEN_APPLE()->setCount(5),
            VanillaItems::WATER_BUCKET(),
            VanillaItems::LAVA_BUCKET(),
            VanillaBlocks::OAK_SAPLING()->asItem()->setCount(2)
        ]);
        $this->addKit('Ultra', 6 * 60 * 60, [
            VanillaItems::CHAINMAIL_HELMET(),
            VanillaItems::CHAINMAIL_CHESTPLATE(),
            VanillaItems::CHAINMAIL_LEGGINGS(),
            VanillaItems::CHAINMAIL_BOOTS(),
            VanillaItems::STONE_SWORD(),
            VanillaItems::STONE_PICKAXE(),
            VanillaItems::STONE_AXE(),
            VanillaItems::STONE_SHOVEL(),
            VanillaItems::STONE_HOE(),
            VanillaItems::GOLDEN_APPLE()->setCount(10),
            VanillaItems::ENCHANTED_GOLDEN_APPLE(),
            VanillaItems::WATER_BUCKET(),
            VanillaItems::LAVA_BUCKET(),
            VanillaBlocks::OAK_LOG()->asItem()->setCount(64),
            VanillaItems::ENDER_PEARL()->setCount(2)
        ], 'nethergames.vip.ultra', TextFormat::GOLD);
        $this->addKit('Emerald', 8 * 60 * 60, [
            VanillaItems::IRON_HELMET(),
            VanillaItems::IRON_CHESTPLATE(),
            VanillaItems::IRON_LEGGINGS(),
            VanillaItems::IRON_BOOTS(),
            VanillaItems::IRON_SWORD(),
            VanillaItems::IRON_PICKAXE(),
            VanillaItems::IRON_AXE(),
            VanillaItems::IRON_SHOVEL(),
            VanillaItems::IRON_HOE(),
            VanillaItems::GOLDEN_APPLE()->setCount(20),
            VanillaItems::ENCHANTED_GOLDEN_APPLE()->setCount(3),
            VanillaItems::WATER_BUCKET(),
            VanillaItems::LAVA_BUCKET(),
            VanillaBlocks::OAK_LOG()->asItem()->setCount(64),
            VanillaItems::ENDER_PEARL()->setCount(5)
        ], 'nethergames.vip.emerald', TextFormat::GREEN);
        $this->addKit('Legend', 12 * 60 * 60, [
            VanillaItems::DIAMOND_HELMET(),
            VanillaItems::DIAMOND_CHESTPLATE(),
            VanillaItems::DIAMOND_LEGGINGS(),
            VanillaItems::DIAMOND_BOOTS(),
            VanillaItems::DIAMOND_SWORD(),
            VanillaItems::DIAMOND_PICKAXE(),
            VanillaItems::DIAMOND_AXE(),
            VanillaItems::DIAMOND_SHOVEL(),
            VanillaItems::DIAMOND_HOE(),
            VanillaItems::GOLDEN_APPLE()->setCount(30),
            VanillaItems::ENDER_PEARL()->setCount(8),
            VanillaItems::ENCHANTED_GOLDEN_APPLE()->setCount(5),
        ], 'nethergames.vip.legend', TextFormat::AQUA);

        $entityManager = $instance->getEssentials()?->getEntityManager();
        $defaultWorld = $instance->getServer()->getWorldManager()->getDefaultWorld();
        if ($entityManager === null) {
            return;
        }

        $addKit = function (string $kit, Location $location) use ($entityManager): void {
            $entityManager->addEntity(new EntityNPC($location, TextFormat::BOLD . TextFormat::GOLD . $kit . ' Crate' . TextFormat::EOL . TextFormat::YELLOW . 'Click to open!', 'ng:skyblock_kit_' . strtolower($kit), function (Player $player) use ($kit): void {
                $player->getServer()->dispatchCommand($player, 'kit "' . $kit . '"');
            }));
        };

        $addKit('Starter', new Location(-39.5, 95, -12.5, $defaultWorld, 270, 0));
        $addKit('Ultra', new Location(-35.5, 95, -17.5, $defaultWorld, 270, 0));
        $addKit('Emerald', new Location(-27.5, 95, -17.5, $defaultWorld, 270, 0));
        $addKit('Legend', new Location(-23.5, 95, -12.5, $defaultWorld, 270, 0));
    }
}