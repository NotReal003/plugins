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

namespace libMMO\aggressiveoptz\component\defaults;

use Closure;
use libMMO\aggressiveoptz\AggressiveOptzAPI;
use libMMO\aggressiveoptz\component\OptimizationComponent;
use LogicException;
use pocketmine\block\Liquid;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\block\BlockSpreadEvent;
use pocketmine\math\Vector3;
use ReflectionProperty;
use function array_key_exists;

class LiquidFallingOptimizationComponent implements OptimizationComponent
{

    /** @var Closure|null */
    private ?Closure $unregister = null;

    public function __construct()
    {
    }

    public function enable(AggressiveOptzAPI $api): void
    {
        if ($this->unregister !== null) {
            throw new LogicException("Tried to register event handler twice");
        }

        $_falling = new ReflectionProperty(Liquid::class, "falling");
        $_decay = new ReflectionProperty(Liquid::class, "decay");

        $liquids = [];
        foreach (RuntimeBlockStateRegistry::getInstance()->getAllKnownStates() as $block) {
            if ($block instanceof Liquid && $_falling->getValue($block) && $_decay->getValue($block) === 0) {
                $liquids[$block->getStateId()] = true;
            }
        }

        $air_id = VanillaBlocks::AIR()->getStateId();
        $this->unregister = $api->registerEvent(function (BlockSpreadEvent $event) use ($liquids, $air_id): void {
            $new_state = $event->getNewState();
            if (array_key_exists($new_state->getStateId(), $liquids)) {
                $pos = $new_state->getPosition();
                $world = $pos->getWorld();

                /** @var int $x */
                $x = $pos->x;
                /** @var int $y */
                $y = $pos->y;
                /** @var int $z */
                $z = $pos->z;

                $chunk = $world->getChunk($x >> 4, $z >> 4);
                if ($chunk !== null && !$world->isChunkLocked($x >> 4, $z >> 4)) {
                    $xc = $x & 0x0f;
                    $zc = $z & 0x0f;
                    $last_y = null;
                    while (--$y >= 0) {
                        if ($chunk->getBlockStateId($xc, $y, $zc) !== $air_id) {
                            break;
                        }
                        $world->setBlockAt($x, $y, $z, $new_state, false);
                        $last_y = $y;
                    }
                    if ($last_y !== null) {
                        $source = $event->getSource();
                        if ($source instanceof Liquid) {
                            $world->scheduleDelayedBlockUpdate(new Vector3($x, $last_y, $z), max(1, $source->tickRate()));
                        }
                    }
                }
            }
        });
    }

    public function disable(AggressiveOptzAPI $api): void
    {
        if ($this->unregister === null) {
            throw new LogicException("Tried to unregister an unregistered event handler");
        }

        ($this->unregister)();
        $this->unregister = null;
    }
}