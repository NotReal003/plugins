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

namespace skyblock\challenges;

use libMMO\challenges\actions\BankStoreMoneyAction;
use libMMO\challenges\actions\BlockBreakAction;
use libMMO\challenges\actions\BountyCollectAction;
use libMMO\challenges\actions\CrateOpenAction;
use libMMO\challenges\actions\GraveCollectAction;
use libMMO\challenges\actions\ItemPickupAction;
use libMMO\challenges\actions\KillEntityAction;
use libMMO\challenges\actions\RepairItemAction;
use libMMO\challenges\Challenge;
use libMMO\challenges\ChallengeManager;
use libMMO\challenges\ChallengeSet;
use skyblock\item\CustomItemManager;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\Rarity;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\utils\TextFormat;
use skyblock\challenges\actions\BossKillAction;
use skyblock\challenges\actions\KillStreakAction;
use skyblock\challenges\actions\MineAction;
use skyblock\challenges\rewards\CrateKeyReward;
use skyblock\challenges\rewards\MoneyPouchReward;
use skyblock\challenges\rewards\RandomArmorReward;
use skyblock\challenges\rewards\RandomToolReward;
use skyblock\crates\CrateManager;

class SkyblockChallengeSet extends ChallengeSet
{
    public const KILL_BOSS = 12;
    public const MINE = 13;
    public const KILL_THANOS = 14;
    public const KILL_STREAK = 15;

    public function setup(ChallengeManager $manager): void
    {
        $manager->addChallenge(
            new Challenge(1, 'Starting', 'Getting Started on Skyblock', [
                new MoneyPouchReward(500),
                VanillaBlocks::OAK_SAPLING()->asItem()->setCount(10)
            ], [
                new BlockBreakAction(VanillaBlocks::COBBLESTONE(), 32)
            ])
        );

        $manager->addChallenge(new Challenge(2, 'Banking', "Let's do some banking!", [
            new MoneyPouchReward(300)
        ], [
            new BankStoreMoneyAction(500)
        ]));

        $manager->addChallenge(new Challenge(3, 'Farming', 'Start making a farm!', [
            new MoneyPouchReward(500),
            VanillaItems::POTATO()->setCount(10),
            VanillaItems::CARROT()->setCount(10),
            VanillaBlocks::SUGARCANE()->asItem()->setCount(10)
        ], [
            new ItemPickupAction(25, VanillaItems::POTATO()),
            new ItemPickupAction(25, VanillaItems::CARROT()),
            new ItemPickupAction(25, VanillaBlocks::SUGARCANE()->asItem())
        ]));

        $manager->addChallenge(new Challenge(4, 'Tree Chopper', "Cut down a few trees, won't do much harm right?", [
            new MoneyPouchReward(300),
            VanillaItems::DIAMOND_AXE()
        ], [
            new BlockBreakAction(VanillaBlocks::OAK_LOG(), 32)
        ]));

        $manager->addChallenge(new Challenge(5, 'Livestock', 'Slay a few animals!', [
            new MoneyPouchReward(600),
            VanillaItems::DIAMOND_SWORD()
        ], [
            new KillEntityAction(15, EntityIds::CHICKEN),
            new KillEntityAction(15, EntityIds::RABBIT)
        ]));

        $sword = VanillaItems::DIAMOND_SWORD();
        $sword->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 2));
        $manager->addChallenge(new Challenge(6, 'Livestock 2', 'Slay some more animals!', [
            new MoneyPouchReward(25000),
            $sword
        ], [
            new KillEntityAction(15, EntityIds::COW),
            new KillEntityAction(15, EntityIds::PIG)
        ]));

        $sword = VanillaItems::DIAMOND_SWORD();
        $sword->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 3));
        $manager->addChallenge(new Challenge(7, 'Livestock 3', 'Slay some Iron Golems!', [
            new MoneyPouchReward(40000),
            $sword
        ], [
            new KillEntityAction(15, EntityIds::IRON_GOLEM)
        ]));

        $manager->addChallenge(new Challenge(8, 'Every man for themselves', "You're the next warrior!", [
            new MoneyPouchReward(500),
            CustomItemManager::getKitItem('Legend', TextFormat::AQUA)
        ], [
            new GraveCollectAction(5)
        ]));

        $manager->addChallenge(new Challenge(9, 'Farming 2', "Let's farm a bit more...", [
            new MoneyPouchReward(1000),
            VanillaItems::DIAMOND_HOE()
        ], [
            new ItemPickupAction(64, VanillaItems::POTATO()),
            new ItemPickupAction(64, VanillaItems::CARROT()),
            new ItemPickupAction(64, VanillaBlocks::SUGARCANE()->asItem())
        ]));

        $manager->addChallenge(new Challenge(11, 'Bounty Hunter', "Let's start the hunt!", [
            new MoneyPouchReward(2000),
            CustomItemManager::getKitItem('Legend', TextFormat::AQUA)
        ], [
            new BountyCollectAction(5)
        ]));

        $manager->addChallenge(new Challenge(12, 'Feeling lucky?', 'Test your luck on some crates!', [
            new CrateKeyReward(5, CrateManager::COMMON),
            new CrateKeyReward(3, CrateManager::RARE),
            new CrateKeyReward(1, CrateManager::MYTHIC)
        ], [
            new CrateOpenAction(20)
        ]));


        $manager->addChallenge(new Challenge(13, 'Boss Slayer', 'Defeat the bosses (multiplayer allowed)', [
            new MoneyPouchReward(1500),
            new CrateKeyReward(1, CrateManager::MYTHIC)
        ], [
            new BossKillAction(3)
        ]));

        $manager->addChallenge(new Challenge(14, 'Miner', 'Mine some ores!', [
            new MoneyPouchReward(1000),
            VanillaItems::DIAMOND_PICKAXE()
        ], [
            new MineAction(VanillaBlocks::COAL_ORE(), 10),
            new MineAction(VanillaBlocks::IRON_ORE(), 5),
            new MineAction(VanillaBlocks::GOLD_ORE(), 5)
        ]));
        $pick = VanillaItems::DIAMOND_PICKAXE();
        $pick->addEnchantment(new EnchantmentInstance(VanillaEnchantments::EFFICIENCY(), 3));
        $manager->addChallenge(new Challenge(15, 'Miner 2', 'Mine more ores!', [
            new MoneyPouchReward(2000),
            $pick
        ], [
            new MineAction(VanillaBlocks::COAL_ORE(), 20),
            new MineAction(VanillaBlocks::IRON_ORE(), 10),
            new MineAction(VanillaBlocks::GOLD_ORE(), 10),
            new MineAction(VanillaBlocks::DIAMOND_ORE(), 5)
        ]));

        $manager->addChallenge(new Challenge(16, 'Blacksmith', 'Repair some items!', [
            CustomItemManager::getRandomEnchantedBook(Rarity::COMMON)->setCount(2),
        ], [
            new RepairItemAction(3)
        ]));

        $manager->addChallenge(new Challenge(17, 'Blacksmith 2', 'Repair more items!', [
            CustomItemManager::getRandomEnchantedBook(Rarity::RARE),
        ], [
            new RepairItemAction(10)
        ]));

        $manager->addChallenge(new Challenge(18, 'Blacksmith 3', 'Repair even more items!', [
            CustomItemManager::getRandomEnchantedBook(Rarity::MYTHIC),
        ], [
            new RepairItemAction(20)
        ]));

        /* // Add this when thanos become available.
        $manager->addChallenge(new Challenge(19, 'I am inevitable', 'Kill Thanos', [
            CustomItemManager::getRandomEnchantedBook(Rarity::MYTHIC),
            CustomItemManager::getRandomEnchantedBook(Rarity::RARE),
            MiniHelperItem::createItem(mt_rand(0, 2)),
        ], [
            new ThanosKillAction(1)
        ]));
        */

        $manager->addChallenge(new Challenge(20, 'Miner', 'Mine 2000 cobblestone', [
            CustomItemManager::getRandomEnchantedBook(Rarity::RARE)
        ], [
            new BlockBreakAction(VanillaBlocks::COBBLESTONE(), 2000)
        ], true));

        $manager->addChallenge(new Challenge(21, 'Inventory collector', "Collect 15 player's inventories", [
            new RandomArmorReward()
        ], [
            new GraveCollectAction(15)
        ], true));

        $manager->addChallenge(new Challenge(22, 'Harvester', 'Farm 200 carrots and 200 potatoes', [
            new RandomToolReward()
        ], [
            new BlockBreakAction(VanillaBlocks::CARROTS(), 100),
            new BlockBreakAction(VanillaBlocks::POTATOES(), 100)
        ], true));

        $manager->addChallenge(new Challenge(23, 'Murderer', 'Get a 7 kill streak', [
            new MoneyPouchReward(30000)
        ], [
            new KillStreakAction(7)
        ], true));

    }
}