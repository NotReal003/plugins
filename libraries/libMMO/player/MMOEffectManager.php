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

namespace libMMO\player;

use pocketmine\entity\effect\Effect;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\EffectManager;
use pocketmine\entity\Living;

class MMOEffectManager extends EffectManager
{
    /** @var EffectInstance[] */
    private array $permanentEffects = [];

    public function __construct(
        private Living $entity
    )
    {
        parent::__construct($this->entity);
    }

    public function removePermanent(EffectInstance $effect, bool $ignoreAmplifier = false): void
    {
        $cancelled = false;

        $index = spl_object_id($effect->getType());
        if (isset($this->effects[$index]) && !$ignoreAmplifier) {
            $oldEffect = $this->effects[$index];
            if (
                abs($effect->getAmplifier()) < $oldEffect->getAmplifier()
                || (abs($effect->getAmplifier()) === abs($oldEffect->getAmplifier()) && $effect->getDuration() < $oldEffect->getDuration())
            ) {
                $cancelled = true;
            }
        }

        unset($this->permanentEffects[$index]);

        if (!$cancelled) {
            parent::remove($effect->getType());
        }
    }

    public function addPermanent(EffectInstance $effect): void
    {
        $this->permanentEffects[spl_object_id($effect->getType())] = $effect;

        $this->add($effect);
    }

    public function hasPermanent(Effect $effect): bool
    {
        return isset($this->permanentEffects[spl_object_id($effect)]);
    }

    public function tick(int $tickDiff = 1): bool
    {
        foreach ($this->effects as $index => $instance) {
            $type = $instance->getType();
            if ($type->canTick($instance)) {
                $type->applyEffect($this->entity, $instance);
            }
            $instance->decreaseDuration($tickDiff);
            if ($instance->hasExpired()) {
                $this->remove($instance->getType());

                $oldEffect = $this->permanentEffects[$index] ?? null;
                if ($oldEffect !== null) {
                    $this->add($oldEffect);
                }
            }
        }

        return count($this->effects) > 0;
    }
}