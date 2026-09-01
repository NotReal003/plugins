<?php

declare(strict_types=1);

namespace libMMO\item;

use pocketmine\block\BlockTypeIds;
use pocketmine\item\ItemTypeIds;

class CooldownList
{
    public static array $consumable = [
        ItemTypeIds::GOLDEN_APPLE => 32 * 20,
        ItemTypeIds::ENCHANTED_GOLDEN_APPLE => 35 * 20,
    ];

    public static array $interactable = [
        -BlockTypeIds::INVISIBLE_BEDROCK // This is just an example, the negative sign is to indicate that it is an item block
    ];

    public static array $usable = [
        ItemTypeIds::ENDER_PEARL => 10 * 20,
    ];
}
