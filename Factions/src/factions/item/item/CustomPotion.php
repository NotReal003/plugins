<?php
/**
 *        ______         _   _
 *       |  ____|       | | (_)
 *  __  _| |__ __ _  ___| |_ _  ___  _ __  ___
 *  \ \/ /  __/ _` |/ __| __| |/ _ \| '_ \/ __|
 *   >  <| | | (_| | (__| |_| | (_) | | | \__ \
 *  /_/\_\_|  \__,_|\___|\__|_|\___/|_| |_|___/
 *
 * Copyright (C) 2016-2023 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder, Studgi
 */

declare(strict_types=1);

namespace factions\item\item;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\item\Potion;

class CustomPotion extends Potion
{
    public const BUILDER = 0;
    public const RAIDER = 1;
    public const TRY_HARD = 2;
    public const MARAUDER = 3;

    public function getAdditionalEffects(): array
    {
        $enchantmentInfo = $this->getNamedTag();
        if ($enchantmentInfo->getInt('potionType', -1) === -1) {
            return parent::getAdditionalEffects();
        }

        return match ($enchantmentInfo->getInt('potionType')) {
            self::BUILDER => [
                new EffectInstance(VanillaEffects::HASTE(), 3 * 60 * 20, 2),
                new EffectInstance(VanillaEffects::FIRE_RESISTANCE(), 8 * 60 * 20),
                new EffectInstance(VanillaEffects::NIGHT_VISION(), 8 * 60 * 20),
                new EffectInstance(VanillaEffects::WATER_BREATHING(), 8 * 60 * 20)
            ],
            self::TRY_HARD => [
                new EffectInstance(VanillaEffects::SPEED(), 2 * 60 * 20, 1),
                new EffectInstance(VanillaEffects::JUMP_BOOST(), 3 * 60 * 20),
                new EffectInstance(VanillaEffects::NIGHT_VISION(), 8 * 60 * 20)
            ],
            self::RAIDER => [
                new EffectInstance(VanillaEffects::JUMP_BOOST(), 3 * 60 * 20, 2),
                new EffectInstance(VanillaEffects::SPEED(), 3 * 60 * 20),
                new EffectInstance(VanillaEffects::NIGHT_VISION(), 8 * 60 * 20)
            ],
            self::MARAUDER => [
                new EffectInstance(VanillaEffects::JUMP_BOOST(), 3 * 60 * 20, 1),
                new EffectInstance(VanillaEffects::SPEED(), 3 * 60 * 20),
            ],
            default => parent::getAdditionalEffects(),
        };
    }
}