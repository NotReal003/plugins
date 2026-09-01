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
 * @author Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder, Studgi
 */
declare(strict_types=1);

namespace libMMO\player;

use libMMO\MMOPlugin;
use NetherGames\NGEssentials\player\NGPlayer;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\player\PlayerInfo;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class MMOPlayer extends NGPlayer
{
    /** @var int */
    private int $combatHitIdx = 0;
    /** @var int */
    private int $combatHitRate = 0;
    /** @var int */
    private int $combatTimer = 0;
    /** @var int */
    private int $commandTimer;

    public function __construct(Server $server, NetworkSession $session, PlayerInfo $playerInfo, bool $authenticated, Location $spawnLocation, ?CompoundTag $namedtag)
    {
        parent::__construct($server, $session, $playerInfo, $authenticated, $spawnLocation, $namedtag);

        $this->effectManager = new MMOEffectManager($this);
        $this->commandTimer = time();
    }

    /**
     * Returns the remaining cooldown (in ticks) the player has for an Item.
     *
     * @param Item $item
     * @return int
     */
    public function getItemCooldown(Item $item): int
    {
        if ($this->hasItemCooldown($item)) {
            $serverTick = $this->getServer()->getTick();

            return $this->getItemCooldownExpiry($item) - $serverTick;
        }

        return 0;
    }

    public function attack(EntityDamageEvent $source): void
    {
        $this->resetHitRate();

        parent::attack($source);
    }

    public function resetHitRate(): void
    {
        $this->combatHitRate = 0;
        $this->combatHitIdx = 0;
    }

    public function increaseHit(): void
    {
        $this->combatHitIdx = 2 * 20;
        $this->combatHitRate++;
    }

    public function getHitRate(): int
    {
        return $this->combatHitRate;
    }

    public function onUpdate(int $currentTick): bool
    {
        $hasUpdated = parent::onUpdate($currentTick);

        if ($hasUpdated && $this->combatHitIdx > 0) {
            $this->combatHitIdx--;
        }

        if ($hasUpdated && $this->isCombatTimerActive()) {
            $this->combatTimer--;

            if ($this->combatTimer === 0) {
                $this->sendMessage(MMOPlugin::getPrefix() . TextFormat::GOLD . 'You are no longer combat tagged.');
            }
        }

        return $hasUpdated;
    }

    /**
     * Returns if the combat timer is current active.
     *
     * @return bool
     */
    public function isCombatTimerActive(): bool
    {
        return $this->combatTimer > 0;
    }

    public function loadInventory(string $inventoryString): void
    {
        $inventoryData = Inventory::convertStringToInventoryJSON($inventoryString, $this->getName(), true);

        $this->getInventory()->setContents(Inventory::convertJsonToContents($inventoryData[Inventory::INVENTORY_TAG] ?? []));
        $this->getArmorInventory()->setContents(Inventory::convertJsonToContents($inventoryData[Inventory::INVENTORY_ARMOR_TAG] ?? []));
        $this->getEnderInventory()->setContents(Inventory::convertJsonToContents($inventoryData[Inventory::INVENTORY_ENDER_CHEST_TAG] ?? []));
    }

    public function saveInventory(): string
    {
        return Inventory::convertInventoryJSONToString(
            Inventory::convertInventoryToJson($this->getInventory()),
            Inventory::convertInventoryToJson($this->getArmorInventory()),
            Inventory::convertInventoryToJson($this->getEnderInventory())
        );
    }

    /**
     * Returns the amount of time (in seconds) on the combat timer.
     *
     * @return int
     */
    public function getCombatTimer(): int
    {
        return $this->combatTimer;
    }

    /**
     * Sets the player's combat timer in seconds.
     *
     * @param int $time
     * @param bool $silent
     */
    public function setCombatTimer(int $time, bool $silent = false): void
    {
        if ($time !== 0 && !$this->isCombatTimerActive() && !$silent) {
            $this->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'You have been combat tagged! You may log out in ' . TextFormat::AQUA . $time . ' seconds' . TextFOrmat::RED . '. An early logout will cause consequences.');
        }

        $this->combatTimer = $time * 20;
    }

    /**
     * @return int
     */
    public function getCommandTimer(): int
    {
        return time() - $this->commandTimer;
    }

    /**
     * @return void
     */
    public function resetCommandTimer(): void
    {
        $this->commandTimer = time();
    }
}
