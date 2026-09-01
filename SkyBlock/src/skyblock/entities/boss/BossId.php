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

interface BossId
{

    public const MEDUSA = 0;
    public const BIG_FOOT = 1;
    public const DESERTER = 2;
    public const THANOS = 3;
    public const MINION = -1;

    public const BOSS = [
        self::MEDUSA => Medusa::class,
        self::BIG_FOOT => BigFoot::class,
        self::DESERTER => Deserter::class,
        self::MINION => BossMinion::class,
        self::THANOS => Thanos::class
    ];
}