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

namespace skyblock\entities\island;

use libPhysX\internal\Rotation;
use libPhysX\PhysX;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use skyblock\forms\IslandForm;
use skyblock\islands\Island;
use skyblock\SkyBlock;

class IslandNPC extends Entity
{
    public function __construct(Location $location, ?CompoundTag $nbt = null)
    {
        parent::__construct($location, $nbt);

        $this->setNameTagVisible();
        $this->setNameTagAlwaysVisible();
    }

    protected function getInitialDragMultiplier(): float { return 0.0; }
    protected function getInitialGravity(): float { return 0.0; }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::BLAZE;
    }

    public function calcYawAndPitch(): Rotation
    {
        return PhysX::calculateRotationEulerAngle($this->getLocation(), $this->getWorld()->getSafeSpawn());
    }

    public function attack(EntityDamageEvent $source): void
    {
        if ($source instanceof EntityDamageByEntityEvent) {
            $plugin = SkyBlock::getInstance();

            $island = $plugin->getIslandManager()->getIslandByWorld($this->getWorld());
            $damager = $source->getDamager();

            if ($damager instanceof Player && $island !== null) {
                if ($island->getOwner() === $damager->getName()) {
                    IslandForm::sendIslandManager($damager, $island, $plugin);
                } else {
                    $damager->sendMessage(TextFormat::RED . "You can only modify your own island's settings.");
                }
            }
        }
    }

    public function canBeMovedByCurrents(): bool
    {
        return false;
    }

    public function setMotion(Vector3 $motion): bool
    {
        return false;
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(1.8, 0.5);
    }

    protected function syncNetworkData(EntityMetadataCollection $properties): void
    {
        parent::syncNetworkData($properties);

        $properties->setGenericFlag(EntityMetadataFlags::SILENT, true);
    }
}