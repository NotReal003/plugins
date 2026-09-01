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

namespace libMMO\challenges\actions;

use libMMO\challenges\ChallengeAction;
use libMMO\challenges\ChallengeSet;
use pocketmine\entity\Entity;
use pocketmine\utils\TextFormat;
use function explode;
use function str_replace;
use function ucwords;

class KillEntityAction extends ChallengeAction
{
    /** @var string */
    protected string $entityType;

    public function __construct(int $goal, string $entityType)
    {
        parent::__construct($goal);

        $this->entityType = $entityType;
    }

    public static function getEntityType(string $entityId): string
    {
        return ucwords(str_replace('_', ' ', explode(':', $entityId)[1]));
    }

    public function toDisplayString(): string
    {
        $goal = $this->getGoal();

        return TextFormat::YELLOW . 'Slay ' . TextFormat::GOLD . $goal . ' ' . TextFormat::YELLOW . self::getEntityType($this->entityType) . (($goal > 1) ? 's' : '');
    }

    public function shouldIncreaseProgress(?object $object): bool
    {
        if ($object instanceof Entity) {
            return strpos($object::getNetworkTypeId(), $this->entityType) !== false;
        }

        return false;
    }

    public function getActionType(): int
    {
        return ChallengeSet::KILL_ENTITY;
    }
}