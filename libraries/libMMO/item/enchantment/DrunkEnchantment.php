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

namespace libMMO\item\enchantment;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\item\enchantment\ItemFlags;
use pocketmine\item\enchantment\Rarity;
use pocketmine\utils\Limits;

class DrunkEnchantment extends PermanentEffectEnchantment
{
    public function __construct()
    {
        parent::__construct('Drunk', Rarity::COMMON, ItemFlags::HEAD, ItemFlags::NONE, 1);
    }

    public function getEffect(int $amplifier = 0): array
    {
        return [
            new EffectInstance(VanillaEffects::NAUSEA(), Limits::INT32_MAX, 1),
            new EffectInstance(VanillaEffects::STRENGTH(), Limits::INT32_MAX, 1),
        ];
    }
}