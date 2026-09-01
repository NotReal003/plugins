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

use factions\Factions;
use LogicException;
use NetherGames\NGEssentials\NGEssentials;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\world\Position;

/**
 * Transfer logic for servers within Farlands game servers.
 */
abstract class TransferLogic extends Vector3
{
    /** @var string */
    private string $serverRegion;
    /** @var string */
    private string $levelName;

    public function __construct(float|int $x, float|int $y, float|int $z, string $levelName, string $serverRegion)
    {
        parent::__construct($x, $y, $z);

        $this->serverRegion = $serverRegion;
        $this->levelName = $levelName;
    }

    public function getPosition(): Position
    {
        if (!$this->isValidServer()) {
            throw new LogicException("Attempting to retrieve position of an other server position.");
        }

        return Position::fromObject($this, Server::getInstance()->getWorldManager()->getWorldByName($this->getWorldName()));
    }

    public function isValidServer(): bool
    {
        return $this->getServerRegion() === NGEssentials::getInstance()->getServerManager()->getServerRegion() && !Factions::isBadlands();
    }

    public function getServerRegion(): string
    {
        return $this->serverRegion;
    }

    public function getWorldName(): string
    {
        return $this->levelName;
    }

    public abstract function getTransportData(): ?string;
}