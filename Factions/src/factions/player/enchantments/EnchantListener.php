<?php
/**
 *        ______         _   _
 *       |  ____|       | | (_)
 *  __  _| |__ __ _  ___| |_ _  ___  _ __  ___
 *  \ \/ /  __/ _` |/ __| __| |/ _ \| '_ \/ __|
 *   >  <| | | (_| | (__| |_| | (_) | | | \__ \
 *  /_/\_\_|  \__,_|\___|\__|_|\___/|_| |_|___/
 *
 * Copyright (C) 2016-2024 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author larryTheCoder
 */

declare(strict_types=1);

namespace factions\player\enchantments;

use factions\Factions;
use factions\player\MMOPlayer;
use factions\utils\Area;
use libMMO\MMOPlugin;
use NetherGames\NGEssentials\item\SimpleCustomItem;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\player\PlayerData as NGPlayerData;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\world\sound\BlazeShootSound;

class EnchantListener extends \libMMO\player\enchantment\EnchantListener
{
    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct($plugin);

        self::$grappleEnchChance = PHP_INT_MAX;
    }

    /**
     * @param EntityDamageByEntityEvent $event
     * @priority HIGHEST
     */
    public function onEntityDamageByEntity(EntityDamageByEntityEvent $event): void
    {
        parent::onEntityDamageByEntity($event);

        $entity = $event->getEntity();
        $damager = $event->getDamager();

        if ($entity instanceof Living && $damager instanceof MMOPlayer && $event->getCause() === EntityDamageEvent::CAUSE_ENTITY_ATTACK) {
            $item = $damager->getInventory()->getItemInHand();

            // Ignore custom items
            if ($item instanceof SimpleCustomItem) {
                return;
            }

            // Custom Enchantment: Sharpness [TESTED: YES]
            if (($instance = $item->getEnchantment(VanillaEnchantments::SHARPNESS())) !== null) {
                $damage = ($instance->getLevel() * 0.2) + 1;

                $event->setBaseDamage($event->getBaseDamage() + $damage);
            }
        }
    }

    protected function canModifyBlocks(Player $player, int $x, int $y, int $z): bool
    {
        $isTracking = NGEssentials::getInstance()->getPlayerData()->getBool($player, NGPlayerData::TRACK);
        if ($isTracking || Factions::isBadlands()) {
            return $player->hasPermission('nethergames.developer');
        } else if (($claim = Factions::getInstance()->getClaimManager()->getClaimInPosition($position = new Position($x, $y, $z, $player->getWorld()))) !== null) {
            return $claim->canAccess($player) || $player->hasPermission('nethergames.developer');
        } else if (Area::isAreaInside($position)) {
            return $player->hasPermission('nethergames.developer');
        }

        return true;
    }

    protected function doRandomCrate(Player $player, int $maxTries, int $level): void
    {
        $maxChances = $level > 0 ? ($level === 1 ? 500 : ($level === 2 ? 400 : 300)) : 1000;

        for ($i = 0; $i < $maxTries; $i++) {
            if (mt_rand(1, $maxChances) === mt_rand(1, $maxChances)) {
                $player->getWorld()->addSound($player->getLocation()->asVector3(), new BlazeShootSound());

                $crate = $this->getPlugin()->getCrateManager()->getRandomCrates($player);

                $this->getPlugin()->getPlayerData()->increaseKey($player, $crate);

                $player->sendTitle(' ', MMOPlugin::getPrefix() . TextFormat::GRAY . sprintf("You've found a %s Crate Key " . TextFormat::GRAY . "while mining!", TextFormat::YELLOW . $this->getPlugin()->getCrateManager()->getCrateName($crate)), 0, 60, 20);
            }
        }
    }
}