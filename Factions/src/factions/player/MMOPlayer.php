<?php
/**
 *        ______         _   _
 *       |  ____|       | | (_)
 *  __  _| |__ __ _  ___| |_ _  ___  _ __  ___
 *  \ \/ /  __/ _` |/ __| __| |/ _ \| '_ \/ __|
 *   >  <| | | (_| | (__| |_| | (_) | | | \__ \
 *  /_/\_\_|  \__,_|\___|\__|_|\___/|_| |_|___/
 *
 * Copyright (C) 2016-2021 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author larryTheCoder
 */

declare(strict_types=1);

namespace factions\player;

use factions\Factions;
use NetherGames\NGEssentials\player\permissions\Permissions;
use NetherGames\NGEssentials\player\PlayerData as NGPlayerData;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\lang\Translatable;
use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;
use pocketmine\network\mcpe\protocol\types\BoolGameRule;
use pocketmine\utils\Utils;

class MMOPlayer extends \libMMO\player\MMOPlayer
{
    /** @var bool */
    private bool $hudTracking = false;
    /** @var int|null */
    private ?int $attackedEntityId = null;

    /**
     * @return Entity|null The entity that was attacking this player.
     */
    public function getAttackedEntity(): ?Entity
    {
        return $this->attackedEntityId !== null ? $this->getWorld()->getEntity($this->attackedEntityId) : null;
    }

    public function setAttackedEntity(Entity $entity): void
    {
        $this->attackedEntityId = $entity->getId();
    }

    public function onUpdate(int $currentTick): bool
    {
        $hasUpdated = parent::onUpdate($currentTick);

        if (!($this->hasPermission(Permissions::RANK_OWNER) || $this->hasPermission(Permissions::RANK_DEVELOPER))) {
            $playerData = Factions::getInstance()->getPlayerData();
            $ngPlayerData = Factions::getInstance()->getEssentials()->getPlayerData();

            $hud = $playerData->isHudEnabled($this);
            $isTracking = $ngPlayerData->getBool($this, NGPlayerData::TRACK);
            if ($hud && $isTracking) {
                $this->enableHud(false);

                $this->hudTracking = true;
            } else if (!$hud && $this->hudTracking && !$isTracking) {
                $this->enableHud(true);

                $this->hudTracking = false;
            }
        }

        return $hasUpdated;
    }


    public function setAllowFlight(bool $value): void
    {
        parent::setAllowFlight($value);

        if (!$value && $this->hasPermission('nethergames.flight.orb')) {
            $this->addAttachment(Factions::getInstance(), 'nethergames.flight.orb', false);
        }
    }

    public function sendMessage(Translatable|string $message): void
    {
        if (!$this->isConnected()) {
            return;
        }

        $this->getNetworkSession()->onChatMessage($message);
    }

    /**
     * @param bool $value
     */
    public function enableHud(bool $value): void
    {
        $playerData = Factions::getInstance()->getPlayerData();
        $playerManager = Factions::getInstance()->getPlayerManager();

        $hud = $playerData->isHudEnabled($this);
        $playerData->setHudStatus($this, $value);

        if ($value === $hud) {
            return;
        }

        if (!$value) {
            $this->showCoordinates(false);

            $scoreboard = Factions::getInstance()->getEssentials()->getServerData()->getScoreBoard();
            $scoreboard->removePlayer($this);
        } else {
            $this->showCoordinates();

            $playerManager->sendScoreboard($this);
        }
    }

    public function showCoordinates(bool $showCoordinates = true): void
    {
        $pk = GameRulesChangedPacket::create(['showcoordinates' => new BoolGameRule($showCoordinates, false)]);
        $this->getNetworkSession()->sendDataPacket($pk);
    }

    public function damageArmor(float $damage): void
    {
        $damageChances = 80;

        // 0 - 80 - 100
        // Apply damage in range of 0-80, probability for the armor to get damaged is 80% now.
        if (Utils::getRandomFloat() < ($damageChances / 100) && mt_rand(0, 100) < $damageChances) {
            parent::damageArmor($damage);
        }
    }
}