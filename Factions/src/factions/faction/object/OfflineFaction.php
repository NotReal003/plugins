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

namespace factions\faction\object;


use factions\Factions;
use factions\utils\Database;
use factions\utils\EventEmitter;
use Generator;
use SOFe\AwaitGenerator\Await;

class OfflineFaction
{
    public function __construct(
        private int    $factionId = 0,
        private string $factionName = '') {}

    public function getFactionId(): int
    {
        return $this->factionId;
    }

    public function getFactionName(): string
    {
        return $this->factionName;
    }

    /**
     * @param int $strength
     */
    public function subtractFromStrength(int $strength): void
    {
        Await::f2c(function () use ($strength): Generator {
            Database::executeChange(Database::STRENGTH_DECREASE, [
                'strength' => $strength,
                'faction_id' => $this->factionId,
            ], yield, yield Await::REJECT);

            yield Await::ONCE;

            Database::executeSelect(Database::STRENGTH_GET, [
                'faction_id' => $this->factionId
            ], yield, yield Await::REJECT);

            $select = yield Await::ONCE;

            if (!isset($select[0])) {
                return;
            }

            Factions::getInstance()->getEventEmitter()->broadcastEvent($this, EventEmitter::EVENT_CHANGE_STRENGTH, [$select[0]['strength']]);
        }, catches: Database::getFailClosure());
    }
}