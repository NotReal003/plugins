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

namespace libMMO\aggressiveoptz;

use Closure;
use InvalidArgumentException;
use libMMO\aggressiveoptz\component\defaults\FallingBlockOptimizationComponent;
use libMMO\aggressiveoptz\component\defaults\LiquidFallingOptimizationComponent;
use libMMO\aggressiveoptz\component\OptimizationComponentFactory;
use libMMO\aggressiveoptz\component\OptimizationComponentManager;
use libMMO\aggressiveoptz\helper\AggressiveOptzHelper;
use libMMO\MMOPlugin;
use Logger;
use pocketmine\event\Event;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\Server;
use ReflectionException;
use ReflectionFunction;
use ReflectionNamedType;
use RuntimeException;

final class AggressiveOptzAPI
{

    /** @var MMOPlugin */
    private MMOPlugin $loader;
    /** @var AggressiveOptzHelper */
    private AggressiveOptzHelper $helper;
    /** @var OptimizationComponentFactory */
    private OptimizationComponentFactory $component_factory;
    /** @var OptimizationComponentManager */
    private OptimizationComponentManager $component_manager;

    public function __construct(MMOPlugin $loader)
    {
        $this->loader = $loader;
        $this->helper = new AggressiveOptzHelper();
        $this->loadComponent();
    }

    public function init(): void
    {
        $this->helper->init($this);
    }

    public function getHelper(): AggressiveOptzHelper
    {
        return $this->helper;
    }

    public function getScheduler(): TaskScheduler
    {
        return $this->loader->getScheduler();
    }

    public function getLogger(): Logger
    {
        return $this->loader->getLogger();
    }

    public function getComponentFactory(): OptimizationComponentFactory
    {
        return $this->component_factory;
    }

    public function getComponentManager(): OptimizationComponentManager
    {
        return $this->component_manager;
    }

    /**
     * Registers an event handler and returns a closure which unregisters
     * the handler.
     *
     * @param Closure $event_handler
     * @param int $priority
     * @param bool $handleCancelled
     *
     * @phpstan-template TEvent of Event
     * @phpstan-param Closure(TEvent) : void $event_handler
     *
     * @return Closure() : void
     */
    public function registerEvent(Closure $event_handler, int $priority = EventPriority::NORMAL, bool $handleCancelled = false): Closure
    {
        try {
            $event_class_instance = (new ReflectionFunction($event_handler))->getParameters()[0]->getType();
            if ($event_class_instance === null || !($event_class_instance instanceof ReflectionNamedType)) {
                throw new InvalidArgumentException("Invalid parameter #1 supplied to event handler");
            }

            /** @phpstan-var class-string<TEvent> $event_class */
            $event_class = $event_class_instance->getName();

            $this->getServer()->getPluginManager()->registerEvent($event_class, $event_handler, $priority, $this->loader, $handleCancelled);

            $listener = null;
            foreach (HandlerListManager::global()->getListFor($event_class)->getListenersByPriority($priority) as $entry) {
                if ($entry->getHandler() === $event_handler) {
                    $listener = $entry;
                    break;
                }
            }
            assert($listener !== null);

            return static function () use ($event_class, $listener): void {
                HandlerListManager::global()->getListFor($event_class)->unregister($listener);
            };
        } catch (ReflectionException $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getServer(): Server
    {
        return $this->loader->getServer();
    }

    private function loadComponent(): void
    {
        $this->component_factory = new OptimizationComponentFactory();
        $this->component_factory->register("aggressiveoptz:falling_block", FallingBlockOptimizationComponent::class);
        $this->component_factory->register("aggressiveoptz:liquid_falling", LiquidFallingOptimizationComponent::class);

        $this->component_manager = new OptimizationComponentManager($this);
    }
}