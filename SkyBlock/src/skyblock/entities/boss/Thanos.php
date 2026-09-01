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

namespace skyblock\entities\boss;

use libMMO\player\PlayerData;
use libMMO\utils\Utils;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\Rarity;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use skyblock\item\CustomItemManager;
use skyblock\challenges\SkyblockChallengeSet;
use skyblock\SkyBlock;
use function is_numeric;

class Thanos extends Boss
{

    protected const SNAP_COOLDOWN = 60 * 6 * 20;
    protected const MAX_DISTANCE = 8;
    protected const DESPAWN_TIME = 60 * 30 * 20;
    protected const HEAL_TIME = 60 * 10 * 20;
    protected const MINIMUM_DAMAGE = 40; //MINIMUM AMOUNT OF DAMAGE NEEDED TO GET A REWARD FROM THE LOW REWARD TIER LIST

    public const HIGH_TIER_REWARD = 0;
    public const LOW_TIER_REWARD = 1;
    /** @var int */
    protected int $snapCooldown = 0;
    /** @var int */
    protected int $despawnTimer = 0;
    /** @var int */
    protected int $healTimer = 0;
    /** @var array<string, float> */
    protected array $damagers = [];

    public function __construct(Location $location, Skin $skin)
    {
        $this->bossId = self::THANOS;
        $this->speed = 0.14;
        $this->spawnMinion = false;
        $this->spawnHealth = 800;
        $this->damage = 13;

        parent::__construct($location, $skin);
    }

    public function attack(EntityDamageEvent $source): void
    {
        parent::attack($source);

        if ($source instanceof EntityDamageByEntityEvent) {
            $damager = $source->getDamager();
            if ($damager instanceof Player) {
                if (isset($this->damagers[$damager->getName()])) {
                    $this->damagers[$damager->getName()] += $source->getFinalDamage();
                } else {
                    $this->damagers[$damager->getName()] = $source->getFinalDamage();
                }
            }
        }
    }

    public function entityBaseTick(int $tickDiff = 1): bool
    {
        if (++$this->snapCooldown >= self::SNAP_COOLDOWN) {
            $this->snapCooldown = 0;
            $this->snapFingers();
        }

        if (++$this->despawnTimer >= self::DESPAWN_TIME) {
            $this->flagForDespawn();
        }

        if (++$this->healTimer >= self::HEAL_TIME) {
            $heal = (int)round($this->spawnHealth / 10);
            $health = $this->getHealth();
            if ($health + $heal >= $this->spawnHealth) {
                $heal = $this->spawnHealth - $health;
            }

            $this->setHealth($health + $heal);
        }

        return parent::entityBaseTick($tickDiff);
    }

    protected function snapFingers(): void
    {
        /** @var Player[] $participants */
        $participants = [];
        foreach ($this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(15, 15, 15)) as $entity) {
            if ($entity instanceof Player) {
                $participants[] = $entity;
            }
        }


        $count = (int)floor(count($participants) / 2);

        for ($i = 0; $i >= $count; $i++) {
            if (count($participants) === 0) {
                break;
            }

            $key = array_rand($participants);
            $participant = $participants[$key];
            $event = new EntityDamageByEntityEvent(
                $this,
                $participant, EntityDamageEvent::CAUSE_ENTITY_ATTACK,
                ($this->damage + ($this->damage * ($this->getBossLevel() * self::BOSS_DAMAGE_LEVEL_MULTIPLIER)))
            );
            $participant->attack($event);

            unset($participants[$key]);
        }
    }

    public function kill(): void
    {
        arsort($this->damagers);
        $i = 1;
        $server = Server::getInstance();
        $data = SkyBlock::getInstance()->getPlayerData();
        foreach ($this->damagers as $username => $damage) {
            if ($p = $server->getPlayerExact($username)) {
                $reward = null;
                if ($i <= 3) {
                    $reward = $this->getRandomTierReward(self::HIGH_TIER_REWARD);
                    foreach (SkyBlock::getInstance()->getPlayerChallengeManager()->getActiveChallenges($p) as $challenge) {
                        $challenge->increaseProgress($p, SkyblockChallengeSet::KILL_THANOS);
                    }
                } elseif ($damage >= self::MINIMUM_DAMAGE) {
                    $reward = $this->getRandomTierReward(self::LOW_TIER_REWARD);
                }

                if ($reward !== null) {
                    $contents = $data->getArray($p, PlayerData::REWARDS);
                    $contents[] = Utils::zlibEncodeItem($reward);
                    $data->setValue($p, PlayerData::REWARDS, $contents);
                    $p->sendMessage(TextFormat::GREEN . 'You won ' . TextFormat::GOLD . $reward->getCount() . 'x ' . $reward->getName() . TextFormat::GREEN . ' for dealing ' . $damage . ' damage to Thanos!');
                }
                $i++;
            }
        }

        $lobby = $this->getWorld()->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation();
        foreach ($this->getWorld()->getPlayers() as $p) {
            $p->teleport($lobby);
        }

        $this->damagers = [];
        parent::kill();
    }

    protected function getRandomTierReward(int $tier): Item
    {
        /** @var array{chance:int, name:Item} $rewards */
        $rewards = [];

        switch ($tier) {
            case self::HIGH_TIER_REWARD:
                $thanosGauntlet = VanillaItems::DIAMOND_SWORD();
                $thanosGauntlet->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 3));
                $thanosGauntlet->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FIRE_ASPECT(), 2));
                $thanosGauntlet->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3));
                $thanosGauntlet->setCustomName('Thanos Gauntlet');
                //$rewards[] = [5, MiniHelperItem::createItem(mt_rand(0, 2))];
                $rewards[] = [20, CustomItemManager::getKitItem('Legend', TextFormat::AQUA)];
                $rewards[] = [27, CustomItemManager::getRandomEnchantedBook(Rarity::COMMON)];
                $rewards[] = [25, CustomItemManager::getRandomEnchantedBook(Rarity::RARE)];
                $rewards[] = [5, CustomItemManager::getRandomEnchantedBook(Rarity::MYTHIC)];
                $rewards[] = [15, CustomItemManager::getKitItem('Emerald', TextFormat::GREEN)];
                $rewards[] = [3, $thanosGauntlet];
                break;
            case self::LOW_TIER_REWARD:
                $rewards[] = [25, VanillaItems::CARROT()->setCount(64)];
                $rewards[] = [25, VanillaBlocks::SUGARCANE()->asItem()->setCount(64)];
                $rewards[] = [25, VanillaItems::POTATO()->setCount(64)];
                //$rewards[] = [20, (new MoneyPouch())->setAmount(mt_rand(30000, 50000))]; // NO, you can't do it this way.
                $rewards[] = [10, CustomItemManager::getKitItem('Emerald', TextFormat::GREEN)];
                $rewards[] = [5, CustomItemManager::getKitItem('Ultra', TextFormat::GOLD)];
                $rewards[] = [10, CustomItemManager::getRandomEnchantedBook(Rarity::COMMON)];
                $rewards[] = [5, CustomItemManager::getRandomEnchantedBook(Rarity::RARE)];
                $rewards[] = [5, VanillaItems::EXPERIENCE_BOTTLE()->setCount(20)];
                $rewards[] = [5, VanillaItems::EXPERIENCE_BOTTLE()->setCount(20)];
                break;
        }

        $reward = null;

        while ($reward === null) {
            /** @phpstan-var array<int, int|Item> $rand */
            $rand = $rewards[array_rand($rewards)];

            if (is_numeric($rand[0]) && mt_rand(1, 100) <= $rand[0]) { //shutup phpstan
                if ($rand[1] instanceof Item) { //shutup phpstan
                    $reward = $rand[1];
                }
            }
        }

        return $reward;
    }

    public function spawnToAll(): void
    {
        parent::spawnToAll();

        //$pk = new SharePacket();
        //$pk->type = SharePacket::TYPE_TAGS;
        //$pk->tags[] = 'SB';
        //$pk->packetBuffer = '';
        //
        //$pkText = new TextPacket();
        //$pkText->message = 'Thanos has spawned!';
        //$pkText->sourceName = 'Thanos has spawned!';
        //$pkText->type = TextPacket::TYPE_ANNOUNCEMENT;
        //$pkText->encode();
        //
        //$pk->packetBuffer = $pkText->getSerializer()->getBuffer();
        //
        //$ess = NGEssentials::getInstance();
        //$ess->getNetSys()->sendSharePacket($pk);
    }
}