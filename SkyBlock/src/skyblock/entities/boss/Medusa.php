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

use libPhysX\internal\Rotation;
use libPhysX\PhysX;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;

class Medusa extends Boss
{
    private const TTS_TICK_DEFAULT = 0;
    private const TTS_TICK_MAX = 200;

    private const MEDUSA_VISION_EFFECT_DURATION = 200;

    /** @var int - Turn to stone tick */
    private int $ttsTick = self::TTS_TICK_DEFAULT;

    public function __construct(Location $location, Skin $skin)
    {
        $this->bossId = self::MEDUSA;
        $this->speed = 0.14;
        $this->spawnMinion = true;
        $this->spawnHealth = 500;
        $this->damage = 7;

        parent::__construct($location, $skin);
    }

    public function entityBaseTick(int $tickDiff = 1): bool
    {
        if ($this->ttsTick === self::TTS_TICK_DEFAULT) {
            $target = $this->getTargetEntity();

            if ($target !== null) {
                $expectedRotation = PhysX::calculateRotationEulerAngle($target->getPosition(), $this->getPosition());
                $currentRotation = new Rotation($target->getLocation()->getYaw(), $target->getLocation()->getPitch());

                if (PhysX::compareRotation($currentRotation, $expectedRotation, 20, 20)) {
                    // It looks like it is a bit hard to add a custom color effect to this instance
                    // as all vanilla enchantments are hardcoded into the server.
                    $effectInstance = new EffectInstance(VanillaEffects::BLINDNESS(), self::MEDUSA_VISION_EFFECT_DURATION);

                    $target->getEffects()->add($effectInstance);

                    $this->ttsTick = self::TTS_TICK_MAX;
                }
            }
        } else {
            $this->ttsTick--;
        }

        return parent::entityBaseTick($tickDiff);
    }
}