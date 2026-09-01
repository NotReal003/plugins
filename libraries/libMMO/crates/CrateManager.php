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

namespace libMMO\crates;

use libMMO\crates\loottables\CrateLootTable;
use libMMO\MMOPlugin;
use libMMO\utils\BaseClass;
use NetherGames\NGEssentials\entity\custom\FloatingText;
use pocketmine\entity\Location;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use function strtolower;

class CrateManager extends BaseClass
{
    /** @var mixed[] */
    protected array $crates = [];
    /**
     * @var CrateLootTable[]
     * @phpstan-var array<string, CrateLootTable>
     */
    private array $lootTables = [];

    public function __construct(MMOPlugin $instance)
    {
        parent::__construct($instance);

        $instance->getServer()->getPluginManager()->registerEvents(new CrateListener($instance), $instance);
    }

    public function addCrateAtPosition(string $crate, Position $position): void
    {
        $crateData = [
            'name' => $crate,
            'position' => $position
        ];

        if (($ess = $this->getPlugin()->getEssentials()) !== null) {
            $floatingText = new FloatingText(Location::fromObject($position->add(0.5, 1.25, 0.5), $position->getWorld()), TextFormat::BOLD . TextFormat::GOLD . $crate . ' Crate', TextFormat::YELLOW . 'Click to open!');

            $ess->getEntityManager()->addEntity($floatingText);
        }

        $this->crates[] = $crateData;
    }

    public function getCrateName(int $crateId): string
    {
        foreach ($this->lootTables as $crate) {
            if ($crate->getKeyDataType() === $crateId) {
                return $crate->getName();
            }
        }

        return '';
    }

    public function getCrateFromPosition(Position $position): string
    {
        foreach ($this->crates as $crate) {
            if ($position->equals($crate['position'])) {
                return (string)$crate['name'];
            }
        }
        return '';
    }

    public function addLootTable(CrateLootTable $lootTable): void
    {
        $this->lootTables[strtolower($lootTable->getName())] = $lootTable;
    }

    public function getLootTable(string $name): ?CrateLootTable
    {
        return $this->lootTables[strtolower($name)] ?? null;
    }

    public function removeLootTable(string $name): void
    {
        unset($this->lootTables[strtolower($name)]);
    }

    /**
     * @return CrateLootTable[]
     */
    public function getLootTables(): array
    {
        return $this->lootTables;
    }

    public function getRandomCrates(Player $player): int
    {
        return 0;
    }
}