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

namespace skyblock\block;

use libforms\elements\Button;
use libforms\FormManager;
use libMMO\entities\stackable\StackingEngine;
use libVanilla\entity\EntityBase;
use NetherGames\NGEssentials\utils\ImpossibleException;
use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockToolType;
use pocketmine\block\BlockTypeIds as Ids;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\MonsterSpawner as PMMonsterSpawnerBase;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\ToolTier;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\BlockTransaction;
use skyblock\SkyBlock;
use function number_format;

final class SpawnerBlock extends PMMonsterSpawnerBase
{
    public const SPAWN_RADIUS = 2;
    public const NEARBY_SPAWN_LIMIT = 7;
    public const GLOBAL_SPAWN_LIMIT = 20;
    public const SPAWN_INTERVAL_UPGRADE_PRICE = 25000;
    public const SPAWN_INTERVAL_MIN = 5;

    public function __construct()
    {
        parent::__construct(new BlockIdentifier(Ids::MONSTER_SPAWNER, SpawnerTile::class), "Monster Spawner", new BlockTypeInfo(new BlockBreakInfo(5.0, BlockToolType::PICKAXE, ToolTier::WOOD->getHarvestLevel())));
    }

    public function getVariantBitmask(): int
    {
        return 0;
    }

    public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null): bool
    {
        if (parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player)) {
            return true;
        }

        return false;
    }

    public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []): bool
    {
        if ($player === null) {
            return false;
        }

        $tile = $this->getPosition()->getWorld()->getTile($this->getPosition());
        if ($tile instanceof SpawnerTile) {
            $form = FormManager::createSimpleForm($player);

            if ($form !== null) {
                $form->setTitle('Spawner Options');
                $form->setContent(TextFormat::GOLD . 'Level ' . $tile->getSpawnerLevel()->getId() . ' Spawner' . TextFormat::EOL . TextFormat::YELLOW . 'Spawn rate: 1 every ' . $tile->getSpawnInterval() . ' seconds');

                $currentLevel = $tile->getSpawnerLevel();
                $nextLevel = $currentLevel->getNextLevel();
                if ($nextLevel === null) {
                    $form->addButton(new Button(TextFormat::YELLOW . 'Level ' . $currentLevel->getId() . TextFormat::EOL . TextFormat::RED . 'Max level reached',
                        function (Player $player) use ($item, $face, $clickVector): void {
                            $this->onInteract($item, $face, $clickVector, $player);
                        }
                    ));
                } else {
                    $form->addButton(new Button(TextFormat::YELLOW . 'Upgrade Level' . TextFormat::EOL . TextFormat::GRAY . 'Upgrade to level ' . $nextLevel->getId() . ' (' . TextFormat::GREEN . '$' . number_format($nextLevel->getPrice()) . TextFormat::GRAY . ')',
                        function (Player $p) use ($currentLevel, $tile): void {
                            if (($nextLevel = $currentLevel->getNextLevel()) !== null) {
                                SkyBlock::getInstance()->getEconomyManager()->reducePlayerMoney($p->getName(), $nextLevel->getPrice(), static function () use ($p, $nextLevel, $tile): void {
                                    $tile->setSpawnerLevel($nextLevel);
                                    $p->sendMessage(TextFormat::GREEN . 'Your spawner has been upgraded to ' . TextFormat::GOLD . 'level ' . $nextLevel->getId() . TextFormat::GREEN . '!');
                                });
                            } else {
                                $p->sendMessage(TextFormat::RED . "Your spawner's level is already maxed!");
                            }
                        }
                    ));
                }

                if ($tile->getSpawnInterval() > self::SPAWN_INTERVAL_MIN) {
                    $form->addButton(new Button(TextFormat::YELLOW . 'Upgrade Spawn Rate' . TextFormat::EOL . TextFormat::GRAY . 'Reduce by 1 second (' . TextFormat::GREEN . '$' . number_format(self::SPAWN_INTERVAL_UPGRADE_PRICE) . TextFormat::GRAY . ')',
                        function (Player $p) use ($tile): void {
                            /** @phpstan-ignore-next-line */
                            if (($interval = $tile->getSpawnInterval()) > self::SPAWN_INTERVAL_MIN) {
                                SkyBlock::getInstance()->getEconomyManager()->reducePlayerMoney($p->getName(), self::SPAWN_INTERVAL_UPGRADE_PRICE, static function () use ($p, $tile, $interval): void {
                                    $tile->setSpawnInterval($interval - 1);
                                    $p->sendMessage(TextFormat::GREEN . 'Your spawner has been upgraded to ' . TextFormat::GOLD . 'spawn 1 every ' . ($interval - 1) . TextFormat::GREEN . ' seconds!');
                                });
                            } else {
                                $p->sendMessage(TextFormat::RED . "Your spawner's spawn rate is already maxed!");
                            }
                        }
                    ));
                } else {
                    $form->addButton(new Button(
                        TextFormat::YELLOW . 'Spawn Rate' . TextFormat::EOL . TextFormat::RED . 'Max spawn rate reached',
                        function (Player $player) use ($item, $face, $clickVector): void {
                            $this->onInteract($item, $face, $clickVector, $player);
                        }
                    ));
                }

                $form->sendForm();
            }

            return true;
        }
        return false;
    }

    public function onScheduledUpdate(): void
    {
        $spawnerTile = $this->getPosition()->getWorld()->getTile($this->getPosition());
        if (!($spawnerTile instanceof SpawnerTile)) {
            return;
        }

        $entityClass = $spawnerTile->getSpawnerLevel()->getEntityClass();
        $cap = self::NEARBY_SPAWN_LIMIT;
        $cap2 = self::GLOBAL_SPAWN_LIMIT;
        foreach ($this->getPosition()->getWorld()->getEntities() as $entity) {
            if ($entity instanceof EntityBase && --$cap2 <= 0) {
                $this->getPosition()->getWorld()->scheduleDelayedBlockUpdate($this->getPosition()->asVector3(), $spawnerTile->getSpawnInterval() * 20);
                return;
            }

            if ($entity instanceof $entityClass && --$cap <= 0) {
                $this->getPosition()->getWorld()->scheduleDelayedBlockUpdate($this->getPosition()->asVector3(), $spawnerTile->getSpawnInterval() * 20);
                return;
            }
        }

        for ($spawned = 0, $tries = 0; $tries < 10 && $spawned < 1; ++$tries) {
            $spawnPos = $this->getPosition()->getWorld()->getSafeSpawn($this->getPosition()->add(
                random_int(-self::SPAWN_RADIUS, self::SPAWN_RADIUS),
                random_int(-self::SPAWN_RADIUS, self::SPAWN_RADIUS),
                random_int(-self::SPAWN_RADIUS, self::SPAWN_RADIUS)
            ));

            //TODO: this would be better if it used the entity's real height/width, but right now we can't easily
            //access it without an instance of the entity.
            $bbCheck = new AxisAlignedBB(
                $spawnPos->x - 1,
                $spawnPos->y,
                $spawnPos->z - 1,
                $spawnPos->x + 1,
                $spawnPos->y + 2,
                $spawnPos->z + 1
            );
            if (count($this->getPosition()->getWorld()->getCollisionBlocks($bbCheck)) === 0) {
                $spawned++;

                $targetStack = StackingEngine::searchForStack($spawnPos, $spawnerTile->getSpawnerLevel()->getEntityClass());
                if ($targetStack !== null) {
                    $targetStack->stack(1);
                } else {
                    $spawnerTile->getSpawnerLevel()->spawn($this->getPosition()->getWorld(), $spawnPos);
                }
            }
        }

        $this->getPosition()->getWorld()->scheduleDelayedBlockUpdate($this->getPosition()->asVector3(), $spawnerTile->getSpawnInterval() * 20);
    }

    public function getDrops(Item $item): array
    {
        $drop = StringToItemParser::getInstance()->parse("monster_spawner");
        $spawnerTile = $this->getPosition()->getWorld()->getTile($this->getPosition());

        if ($spawnerTile instanceof SpawnerTile) {
            $nbt = $spawnerTile->getCleanedNBT();
            if ($nbt === null) {
                throw ImpossibleException::fromMessage('$nbt === null', 'NG Spawner should always have exported NBT');
            }

            $drop->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GOLD . 'Level ' . $spawnerTile->getSpawnerLevel()->getId() . ' Spawner');
            $drop->setLore([
                '',
                TextFormat::RESET . TextFormat::AQUA . 'Spawn rate: ' . TextFormat::WHITE . '1/' . $spawnerTile->getSpawnInterval() . ' seconds',
                '',
                TextFormat::RESET . TextFormat::GRAY . 'Place anywhere to activate spawner.'
            ]);
            $drop->setCustomBlockData($nbt);
        }

        return [$drop];
    }

    protected function getXpDropAmount(): int
    {
        return 0;
    }
}