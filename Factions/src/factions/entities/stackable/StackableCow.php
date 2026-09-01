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

namespace factions\entities\stackable;

use libMMO\entities\stackable\StackableInterface;
use libMMO\entities\stackable\StackableTrait;
use libMMO\utils\async\AsyncModularEntity;
use libVanilla\entity\passive\Cow;
use pocketmine\entity\Entity;
use pocketmine\utils\TextFormat;

class StackableCow extends Cow implements StackableInterface, AsyncModularEntity
{
    use StackableTrait;
    use AttributeModifier;

    public function getStackedEntity(): StackableInterface|Entity
    {
        return $this;
    }

    public function getCustomName(): string
    {
        return TextFormat::GRAY . 'Cow';
    }
}