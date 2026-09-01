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
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder
 */

namespace skyblock\task;

use Closure;
use GlobalLogger;
use InvalidArgumentException;
use libMMO\utils\Utils;
use pocketmine\block\tile\Container;
use pocketmine\block\tile\Tile;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\scheduler\AsyncTask;
use pocketmine\scheduler\AsyncWorker;
use pocketmine\thread\Thread;
use pocketmine\world\format\io\exception\CorruptedWorldException;
use pocketmine\world\format\io\exception\UnsupportedWorldFormatException;
use pocketmine\world\format\io\WorldProviderManager;
use pocketmine\world\format\io\WorldProviderManagerEntry;
use Throwable;

/**
 * Intensive search task that aims to search all contents in the world by firing them
 * before the world is being loaded.
 */
class IslandSearchAsyncTask extends AsyncTask
{
    private const LOCAL_KEY_COMPLETION = 'completion';

    public const RESULT_SUCCESS = 0;
    public const RESULT_FAILURE = 1;

    /** @var WorldProviderManager|null */
    public static ?WorldProviderManager $providerManager = null;

    /** @var string */
    private string $worldPath;

    public function __construct(string $worldPath, Closure $onComplete)
    {
        $this->worldPath = $worldPath;

        $this->storeLocal(self::LOCAL_KEY_COMPLETION, $onComplete);
    }

    public function onRun(): void
    {
        if (self::$providerManager === null) {
            self::$providerManager = new WorldProviderManager();
        }

        $provider = null;
        try {
            $providers = self::$providerManager->getMatchingProviders($this->worldPath);
            if (count($providers) !== 1) {
                $this->setResult([self::RESULT_FAILURE, null]);

                GlobalLogger::get()->warning("Unable to load world $this->worldPath due to ambiguous world data");
                return;
            }

            /** @var WorldProviderManagerEntry $providerClass */
            $providerClass = array_shift($providers);

            $currentThread = Thread::getCurrentThread();
            assert($currentThread instanceof AsyncWorker);

            try {
                $provider = $providerClass->fromPath($this->worldPath, new \PrefixedLogger($currentThread->getLogger(), "IslandSearch $this->worldPath"));
            } catch (CorruptedWorldException) {
                $this->setResult([self::RESULT_FAILURE, null]);

                GlobalLogger::get()->error("Unable to load world $this->worldPath, world is corrupted!");

                return;
            } catch (UnsupportedWorldFormatException) {
                $this->setResult([self::RESULT_FAILURE, null]);

                GlobalLogger::get()->error("Unable to load world $this->worldPath, world is unsupported!");

                return;
            }

            // World-wide violations data, this contents contains ALL the
            // violations occurs in the world itself. TODO: Modify the values?
            $wDurVL = 0;
            $wLoreVL = 0;
            $wMaxLore = 0;
            $wCurseVL = 0;
            $knownTiles = [];
            foreach ($provider->getAllChunks() as $chunk) {
                $tiles = $chunk->getData()->getTileNBT();

                foreach ($tiles as $nbt) {
                    $type = $nbt->getString(Tile::TAG_ID, "");
                    $knownTiles[$type] = ($knownTiles[$type] ?? 0) + 1;

                    $contents = [];
                    if (($inventoryTag = $nbt->getTag(Container::TAG_ITEMS)) instanceof ListTag) {
                        /** @var CompoundTag $itemNBT */
                        foreach ($inventoryTag as $itemNBT) {
                            $contents[$itemNBT->getByte("Slot")] = Item::nbtDeserialize($itemNBT);
                        }
                    }

                    [$durabilityVL, $loreTileVL, $maxLore, $curseVL] = Utils::doInventoryCheck($contents);

                    $wDurVL += $durabilityVL;
                    $wLoreVL += $loreTileVL;
                    $wMaxLore += $maxLore;
                    $wCurseVL += $curseVL;
                }
            }

            $this->setResult([self::RESULT_SUCCESS, [$wDurVL, $wLoreVL, $wMaxLore, $wCurseVL, $knownTiles]]);
        } catch (Throwable $error) {
            $this->setResult([self::RESULT_FAILURE, $error]);
        } finally {
            $provider?->close();
        }
    }

    public function onError(): void
    {
        try {
            /** @var Closure|null $closure */
            $closure = $this->fetchLocal(self::LOCAL_KEY_COMPLETION);
        } catch (InvalidArgumentException) {
            return;
        }

        if ($closure !== null) {
            $closure([self::RESULT_FAILURE, null]);
        }
    }

    public function onCompletion(): void
    {
        $storage = $this->fetchLocal(self::LOCAL_KEY_COMPLETION);
        $storage($this->getResult());
    }
}