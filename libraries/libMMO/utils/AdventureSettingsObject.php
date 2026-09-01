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

namespace libMMO\utils;

use libMMO\MMOPlugin;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\types\AbilitiesData;
use pocketmine\network\mcpe\protocol\types\AbilitiesLayer;
use pocketmine\network\mcpe\protocol\UpdateAbilitiesPacket;
use pocketmine\network\mcpe\protocol\UpdateAdventureSettingsPacket;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\SingletonTrait;
use function array_key_first;

/**
 * Stores all AdventureSettingsPacket related data, this will filter all outbound {@see AdventureSettingsPacket} packet
 * by players here, and it will modify the given packet for the plugin.
 */
final class AdventureSettingsObject implements Listener
{
    use SingletonTrait;

    /** @var bool[] */
    private array $allowWorldEdit = [];

    public function __construct()
    {
        Server::getInstance()->getPluginManager()->registerEvents($this, MMOPlugin::getInstance());
    }

    public function setBuildingPermission(Player $player, bool $allowBuild): void
    {
        $this->allowWorldEdit[$player->getName()] = $allowBuild;

        $session = $player->getNetworkSession();
        $session->syncAbilities($player);
        $session->syncAdventureSettings();
    }

    /**
     * @param DataPacketSendEvent $event
     * @priority LOWEST
     */
    public function onDataPacketSend(DataPacketSendEvent $event): void
    {
        if (count($targets = $event->getTargets()) === 1) {
            $packets = $event->getPackets();
            $player = $targets[array_key_first($targets)]->getPlayer();
            if ($player === null || !$player->isConnected()) {
                return;
            }
            $worldEditable = $this->getBuildingPermission($player);

            foreach ($packets as $index => $packet) {
                if ($packet instanceof UpdateAdventureSettingsPacket) {
                    $packets[$index] = UpdateAdventureSettingsPacket::create(
                        noAttackingMobs: $packet->isNoAttackingMobs(),
                        noAttackingPlayers: $packet->isNoAttackingPlayers(),
                        worldImmutable: !$worldEditable,
                        showNameTags: $packet->isShowNameTags(),
                        autoJump: $packet->isAutoJump()
                    );
                } else if ($packet instanceof UpdateAbilitiesPacket) {
                    $alProperty = (new \ReflectionClass(AbilitiesData::class))->getProperty("abilityLayers");
                    $abilityLayers = $alProperty->getValue($packet->getData());
                    /** @var AbilitiesLayer $abilityLayer */
                    $index = count($abilityLayers) - 1;
                    foreach ($abilityLayers as $i => $abilityLayer) {
                        if ($abilityLayer->getLayerId() !== AbilitiesLayer::LAYER_BASE) {
                            continue;
                        }
                        $index = $i;
                    }
                    $abilitiesProp = (new \ReflectionClass(AbilitiesLayer::class))->getProperty("boolAbilities");
                    $abilitiesProp->setValue($abilityLayers[$index], array_replace($abilitiesProp->getValue($abilityLayers[$index]), [
                        AbilitiesLayer::ABILITY_BUILD => $worldEditable,
                        AbilitiesLayer::ABILITY_MINE => $worldEditable,
                        AbilitiesLayer::ABILITY_DOORS_AND_SWITCHES => $worldEditable,
                        AbilitiesLayer::ABILITY_OPEN_CONTAINERS => $worldEditable,
                    ]));
                    usort($abilityLayers, fn(AbilitiesLayer $a, AbilitiesLayer $b) => $a <=> $b);
                    $alProperty->setValue($packet->getData(), array_values($abilityLayers));
                }
            }
        }
    }

    public function getBuildingPermission(Player $player): bool
    {
        return $this->allowWorldEdit[$player->getName()] ?? true;
    }

    /**
     * @param PlayerQuitEvent $event
     * @priority LOWEST
     */
    public function onPlayerQuitEvent(PlayerQuitEvent $event): void
    {
        unset($this->allowWorldEdit[$event->getPlayer()->getName()]);
    }
}