<?php

declare(strict_types=1);

namespace skyblock\commands;


use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use pocketmine\utils\TextFormat;

class ArenaCommand extends BaseCommand
{

    public function __construct(MMOPlugin $owningPlugin)
    {
        parent::__construct("arena", $owningPlugin);

        $this->setDescription("Teleport to the boss arena");
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        $world = $sender->getServer()->getWorldManager()->getWorldByName("sb-arena");

        if ($world === null) {
            $sender->sendMessage(TextFormat::RED . "You can only teleport to the arena during a boss fight");
        } else {
            $sender->teleport($world->getSpawnLocation());
        }

        return true;
    }
}
