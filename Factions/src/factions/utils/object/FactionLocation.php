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

namespace factions\utils\object;

class FactionLocation extends TransferLogic
{
    public function __construct(float|int $x, float|int $y, float|int $z, string $levelName, string $serverRegion)
    {
        parent::__construct($x, $y, $z, $levelName, $serverRegion);
    }

    public function getTransportData(): string
    {
        return json_encode([0 => 'faction', 1 => $this->getFloorX() . ":" . $this->getFloorY() . ":" . $this->getFloorZ() . ":" . $this->getWorldName() . ":"]);
    }
}