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

namespace skyblock\crates;

use libMMO\crates\CrateListener;
use libMMO\crates\loottables\CrateLootTable;
use libMMO\crates\loottables\CrateLootTableEntry;
use skyblock\item\CustomItemManager;
use libMMO\item\CustomItemRegistry;
use libMMO\MMOPlugin;
use NetherGames\NGEssentials\entity\custom\EntityNPC;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\item\enchantment\Rarity;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\AnimateEntityPacket;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;

class CrateManager extends \libMMO\crates\CrateManager
{
    public const VOTE = 0;
    public const COMMON = 1;

    public const RARE = 2;
    public const MYTHIC = 3;

    public function __construct(MMOPlugin $instance)
    {
        parent::__construct($instance);

        $defaultLevel = $instance->getServer()->getWorldManager()->getDefaultWorld();

        $ultraKit = CustomItemManager::getKitItem('Ultra', TextFormat::GOLD);
        $emeraldKit = CustomItemManager::getKitItem('Emerald', TextFormat::GREEN);
        $legendKit = CustomItemManager::getKitItem('Legend', TextFormat::AQUA);

        $commonKeys = mt_rand(1, 1000);
        $voteKeys = mt_rand($commonKeys, 1500);
        $rareKeys = mt_rand($voteKeys, 3000);
        $mythicKeys = mt_rand($rareKeys, 5000);

        $commonLuck = mt_rand(1, 5);
        $voteLuck = mt_rand($commonLuck, 10);
        $rareLuck = mt_rand($voteLuck, 15);
        $mythicLuck = mt_rand($rareLuck, 20);

        $this->addLootTable(new CrateLootTable('Vote', self::VOTE, [
            new CrateLootTableEntry(VanillaBlocks::TRIPWIRE_HOOK()->asItem()->setCount(2)->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . 'Mythic Key')->setCustomBlockData(CompoundTag::create()->setTag("KeyTag", new CompoundTag())->setInt("KeyDataType", self::MYTHIC)), 1),
            new CrateLootTableEntry(VanillaBlocks::TRIPWIRE_HOOK()->asItem()->setCount(2)->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GOLD . 'Rare Key')->setCustomBlockData(CompoundTag::create()->setTag("KeyTag", new CompoundTag())->setInt("KeyDataType", self::RARE)), 3),
            new CrateLootTableEntry(clone $ultraKit, 5),
            new CrateLootTableEntry(CustomItemManager::getRandomEnchantedBook(Rarity::COMMON), 5),
            new CrateLootTableEntry(CustomItemManager::getRandomEnchantedBook(Rarity::RARE), 5),
            new CrateLootTableEntry(VanillaItems::POTATO()->setCount(8), 10),
            new CrateLootTableEntry(VanillaItems::CARROT()->setCount(8), 10),
            new CrateLootTableEntry(VanillaBlocks::SUGARCANE()->asItem()->setCount(8), 10),
            new CrateLootTableEntry(VanillaBlocks::NETHER_WART()->asItem()->setCount(8), 15),
            new CrateLootTableEntry(CustomItemManager::getPowerShard($voteKeys), 15),
            new CrateLootTableEntry(CustomItemManager::getLuckyShard($voteLuck), 15),
            new CrateLootTableEntry(CustomItemRegistry::MONEY_POUCH()->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::LIGHT_PURPLE . 'Money Pouch')->setLore([
                TextFormat::RESET . TextFormat::AQUA . 'Minimum amount: ' . TextFormat::WHITE . '$1',
                TextFormat::RESET . TextFormat::AQUA . 'Maximum amount: ' . TextFormat::WHITE . '$1,000'
            ])->setCustomBlockData(CompoundTag::create()
                ->setTag("MoneyPouch", new CompoundTag())
                ->setInt("Min", 1)
                ->setInt("Max", 1000)
            ), 20)
        ]));
        $this->registerCrateEntity('Vote', new Location(-39.5, 95, 5.5, $defaultLevel, 270, 0), 'blue');

        $this->addLootTable(new CrateLootTable('Common', self::COMMON, [
            new CrateLootTableEntry(CustomItemManager::getRandomEnchantedBook(Rarity::RARE), 5),
            new CrateLootTableEntry(VanillaBlocks::TRIPWIRE_HOOK()->asItem()->setCount(3)->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GOLD . 'Rare Key')->setCustomBlockData(CompoundTag::create()->setTag("KeyTag", new CompoundTag())->setInt("KeyDataType", self::RARE)), 3),
            new CrateLootTableEntry(VanillaBlocks::NETHER_WART()->asItem()->setCount(8), 7),
            new CrateLootTableEntry(CustomItemManager::getRandomEnchantedBook(Rarity::COMMON), 10),
            new CrateLootTableEntry(VanillaBlocks::TRIPWIRE_HOOK()->asItem()->setCount(5)->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::YELLOW . 'Common Key')->setCustomBlockData(CompoundTag::create()->setTag("KeyTag", new CompoundTag())->setInt("KeyDataType", self::COMMON)), 5),
            new CrateLootTableEntry(clone $ultraKit, 10),
            new CrateLootTableEntry(VanillaItems::POTATO()->setCount(10), 10),
            new CrateLootTableEntry(VanillaBlocks::NETHER_WART()->asItem()->setCount(10), 10),
            new CrateLootTableEntry(VanillaItems::CARROT()->setCount(10), 15),
            new CrateLootTableEntry(VanillaBlocks::SUGARCANE()->asItem()->setCount(10), 15),
            new CrateLootTableEntry(CustomItemManager::getPowerShard($commonKeys), 15),
            new CrateLootTableEntry(CustomItemManager::getLuckyShard($commonLuck), 15),
            new CrateLootTableEntry(CustomItemRegistry::MONEY_POUCH()->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::LIGHT_PURPLE . 'Money Pouch')->setLore([
                TextFormat::RESET . TextFormat::AQUA . 'Minimum amount: ' . TextFormat::WHITE . '$1',
                TextFormat::RESET . TextFormat::AQUA . 'Maximum amount: ' . TextFormat::WHITE . '$3,000'
            ])->setCustomBlockData(CompoundTag::create()
                ->setTag("MoneyPouch", new CompoundTag())
                ->setInt("Min", 1)
                ->setInt("Max", 3000)
            ), 20)
        ]));
        $this->registerCrateEntity('Common', new Location(-35.5, 95, 10.5, $defaultLevel, 180, 0), 'red');

        $this->addLootTable(new CrateLootTable('Rare', self::RARE, [
            new CrateLootTableEntry(VanillaBlocks::TRIPWIRE_HOOK()->asItem()->setCount(3)->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . 'Mythic Key')->setCustomBlockData(CompoundTag::create()->setTag("KeyTag", new CompoundTag())->setInt("KeyDataType", self::MYTHIC)), 1),
            new CrateLootTableEntry(VanillaBlocks::TRIPWIRE_HOOK()->asItem()->setCount(5)->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GOLD . 'Rare Key')->setCustomBlockData(CompoundTag::create()->setTag("KeyTag", new CompoundTag())->setInt("KeyDataType", self::RARE)), 3),
            new CrateLootTableEntry(CustomItemManager::getRandomEnchantedBook(Rarity::RARE), 5),
            new CrateLootTableEntry(VanillaBlocks::NETHER_WART()->asItem()->setCount(10), 7),
            new CrateLootTableEntry(CustomItemManager::getRandomEnchantedBook(Rarity::COMMON), 10),
            new CrateLootTableEntry(VanillaBlocks::TRIPWIRE_HOOK()->asItem()->setCount(5)->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::YELLOW . 'Common Key')->setCustomBlockData(CompoundTag::create()->setTag("KeyTag", new CompoundTag())->setInt("KeyDataType", self::COMMON)), 5),
            new CrateLootTableEntry(clone $emeraldKit, 10),
            new CrateLootTableEntry(VanillaItems::POTATO()->setCount(20), 10),
            new CrateLootTableEntry(VanillaItems::CARROT()->setCount(10), 10),
            new CrateLootTableEntry(VanillaBlocks::SUGARCANE()->asItem()->setCount(10), 10),
            new CrateLootTableEntry(CustomItemManager::getPowerShard($rareKeys), 15),
            new CrateLootTableEntry(CustomItemManager::getLuckyShard($rareLuck), 15),
            new CrateLootTableEntry(CustomItemRegistry::MONEY_POUCH()->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::LIGHT_PURPLE . 'Money Pouch')->setLore([
                TextFormat::RESET . TextFormat::AQUA . 'Minimum amount: ' . TextFormat::WHITE . '$1,000',
                TextFormat::RESET . TextFormat::AQUA . 'Maximum amount: ' . TextFormat::WHITE . '$5,000'
            ])->setCustomBlockData(CompoundTag::create()
                ->setTag("MoneyPouch", new CompoundTag())
                ->setInt("Min", 1000)
                ->setInt("Max", 5000)
            ), 20)
        ]));
        $this->registerCrateEntity('Rare', new Location(-27.5, 95, 10.5, $defaultLevel, 180, 0), 'green');

        $this->addLootTable(new CrateLootTable('Mythic', self::MYTHIC, [
            new CrateLootTableEntry(CustomItemManager::getRandomEnchantedBook(Rarity::MYTHIC), 3),
            new CrateLootTableEntry(VanillaBlocks::TRIPWIRE_HOOK()->asItem()->setCount(3)->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . 'Mythic Key')->setCustomBlockData(CompoundTag::create()->setTag("KeyTag", new CompoundTag())->setInt("KeyDataType", self::MYTHIC)), 1),
            new CrateLootTableEntry(VanillaBlocks::TRIPWIRE_HOOK()->asItem()->setCount(5)->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GOLD . 'Rare Key')->setCustomBlockData(CompoundTag::create()->setTag("KeyTag", new CompoundTag())->setInt("KeyDataType", self::RARE)), 3),
            new CrateLootTableEntry(CustomItemManager::getRandomEnchantedBook(Rarity::RARE), 7),
            new CrateLootTableEntry(VanillaBlocks::NETHER_WART()->asItem()->setCount(35), 7),
            new CrateLootTableEntry(clone $legendKit, 10),
            new CrateLootTableEntry(VanillaBlocks::SUGARCANE()->asItem()->setCount(30), 10),
            new CrateLootTableEntry(VanillaItems::POTATO()->setCount(30), 10),
            new CrateLootTableEntry(VanillaItems::CARROT()->setCount(30), 10),
            new CrateLootTableEntry(clone $emeraldKit, 15),
            new CrateLootTableEntry(CustomItemManager::getPowerShard($mythicKeys), 15),
            new CrateLootTableEntry(CustomItemManager::getLuckyShard($mythicLuck), 15),
            new CrateLootTableEntry(CustomItemRegistry::MONEY_POUCH()->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::LIGHT_PURPLE . 'Money Pouch')->setLore([
                TextFormat::RESET . TextFormat::AQUA . 'Minimum amount: ' . TextFormat::WHITE . '$20,000',
                TextFormat::RESET . TextFormat::AQUA . 'Maximum amount: ' . TextFormat::WHITE . '$40,000'
            ])->setCustomBlockData(CompoundTag::create()
                ->setTag("MoneyPouch", new CompoundTag())
                ->setInt("Min", 20000)
                ->setInt("Max", 40000)
            ), 20)
        ]));
        $this->registerCrateEntity('Mythic', new Location(-23.5, 95, 5.5, $defaultLevel, 90, 0), 'purple');
    }

    private function registerCrateEntity(string $crate, Location $location, string $crateColor): void
    {
        $entityManager = $this->getPlugin()->getEssentials()?->getEntityManager();
        if ($entityManager === null) {
            return;
        }

        /** @var EntityNPC|null $npc */
        $npc = null;
        $world = $location->getWorld();
        $entityManager->addEntity($npc = new EntityNPC($location, TextFormat::BOLD . TextFormat::GOLD . $crate . ' Crate' . TextFormat::EOL . TextFormat::YELLOW . 'Click to open!', 'ng:skyblock_crate_' . $crateColor, function(Player $player) use ($crate, &$npc, $world): void {
            if (($lootTable = $this->getLootTable($crate)) !== null) {
                CrateListener::sendCrate($player, $lootTable);

                NetworkBroadcastUtils::broadcastPackets($world->getPlayers(), [
                    AnimateEntityPacket::create('animation.ng.skyblock.crate.open', 'animation.ng.skyblock.crate.idle', '', 0, '', 0, [$npc->getId()])
                ]);
            }
        }));
    }

    public function getRandomCrates(Player $player): int
    {
        $chance = random_int(1, 10);
        $crate = CrateManager::COMMON;
        if ($chance === 1) {
            $crate = CrateManager::RARE;
        } elseif ($chance === 2) {
            $crate = CrateManager::MYTHIC;
        }

        return $crate;
    }
}