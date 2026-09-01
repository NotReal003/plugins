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
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder
 */

declare(strict_types=1);

namespace skyblock\player\enchantment;

use libMMO\entities\stackable\StackableInterface;
use libMMO\MMOPlugin;
use libMMO\player\enchantment\EnchantmentManager;
use libMMO\player\MMOPlayer;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\projectile\Arrow;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\sound\BlazeShootSound;

class EnchantListener extends \libMMO\player\enchantment\EnchantListener
{
    /** @var true[] */
    private array $enchantLock = [];

    /**
     * @param EntityDeathEvent $event
     * @priority MONITOR
     */
    public function onEntityDeathEvent(EntityDeathEvent $event): void
    {
        $entity = $event->getEntity();
        if (!($entity instanceof StackableInterface)) {
            return;
        }

        $cause = $entity->getLastDamageCause();

        if ($cause instanceof EntityDamageByEntityEvent) {
            $damager = $cause->getDamager();

            if ($damager instanceof MMOPlayer) {
                $itemHolding = $damager->getInventory()->getItemInHand();

                if (($instance = $itemHolding->getEnchantment(EnchantmentManager::KILL_AURA())) === null || isset($this->enchantLock[$damager->getName()])) {
                    return;
                }

                if (mt_rand(0, 20) > 15) {
                    $drops = mt_rand(1, $instance->getLevel() * 2);

                    $this->enchantLock[$damager->getName()] = true;

                    while ($drops > 0) {
                        if ($entity->getStackedAmount() <= 0) {
                            break;
                        }

                        $entity->kill();

                        $drops--;
                    }

                    unset($this->enchantLock[$damager->getName()]);
                }
            }
        }
    }

    /**
     * @param EntityDamageEvent $event
     * @priority MONITOR
     */
    public function onEntityDamageEvent(EntityDamageEvent $event): void
    {
        // Lifesteal, chance to regain health when attacking, Regeneration 3 (amp 2) for 2 seconds odd of 1 to 500
        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();
            $entity = $event->getEntity();

            if (!($damager instanceof MMOPlayer)) {
                return;
            }

            $itemHolding = $damager->getInventory()->getItemInHand();

            if (($instance = $itemHolding->getEnchantment(EnchantmentManager::LIFESTEAL())) !== null && mt_rand(0, 100) < 15) {
                $damager->getEffects()->add(new EffectInstance(VanillaEffects::REGENERATION(), 2 * 20, $instance->getLevel() + 1));
            }

            if ($entity instanceof MMOPlayer && $event->getCause() === EntityDamageEvent::CAUSE_PROJECTILE) {
                if (!($event instanceof EntityDamageByChildEntityEvent)) {
                    return;
                }

                /** @var Arrow|null $child */
                $child = $event->getChild();
                if (!($child instanceof Arrow)) {
                    return;
                }

                // Lethal Precision - Bow, every headshot will have a chance to deal damage up to 0.3x up to 2.1x
                // Lifesteal - Sword, A chance to regain health when attacking, this chance is around 7% - 21% from level 1 to 4
                // Kill Aura - Chance to kill multiple stacks of monsters in a stack each death event

                // Detonation Enchantment [TESTED: NO]
                if (($instance = $damager->getInventory()->getItemInHand()->getEnchantment(EnchantmentManager::LETHAL_PRECISION())) !== null) {
                    $playerHeadStart = $entity->getPosition()->add(0.0, 1.621, 0.0)->getY() - 0.17;
                    if ($child->getPosition()->getY() > $playerHeadStart) {
                        // +3% to 18%, +3% for every level.
                        $event->setBaseDamage($event->getBaseDamage() + ($event->getBaseDamage() * (0.03 + ($instance->getLevel() * 0.03))));
                    }
                }
            }
        }
    }

    protected function doRandomCrate(Player $player, int $maxTries, int $level): void
    {
        $maxLevel = $level > 0 ? ($level === 1 ? 200 : ($level === 2 ? 150 : 100)) : 250;

        if (mt_rand(1, $maxLevel) === mt_rand(1, $maxLevel)) {
            $player->getWorld()->addSound($player->getLocation()->asVector3(), new BlazeShootSound());

            $crate = $this->getPlugin()->getCrateManager()->getRandomCrates($player);

            $this->getPlugin()->getPlayerData()->increaseKey($player, $crate);

            $player->sendTitle(' ', MMOPlugin::getPrefix() . TextFormat::GRAY . sprintf("You've found a %s Crate Key " . TextFormat::GRAY . "while mining!", TextFormat::YELLOW . $this->getPlugin()->getCrateManager()->getCrateName($crate)), 0, 60, 20);
        }
    }
}