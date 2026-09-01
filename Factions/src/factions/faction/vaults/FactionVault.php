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

namespace factions\faction\vaults;

use factions\faction\object\Faction;
use factions\utils\Database;
use Generator;
use libMMO\MMOPlugin;
use libMMO\player\Inventory as MMOInventory;
use muqsit\invmenu\InvMenu;
use NetherGames\NGEssentials\NGEssentials;
use pocketmine\inventory\BaseInventory;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use SOFe\AwaitGenerator\Await;

class FactionVault
{
    /** @var Faction */
    private Faction $faction;
    /** @var InvMenu */
    private InvMenu $menu;
    /** @var Player|null */
    private ?Player $lockedPlayer = null;

    public function __construct(Faction $faction)
    {
        $this->faction = $faction;

        $this->menu = InvMenu::create('libmmo:double');
        $this->menu->setName('Faction\'s Vault');
        $this->menu->setInventoryCloseListener($this->onInventoryClose());
    }

    private function onInventoryClose(): callable
    {
        return function (Player $player, BaseInventory $inventory): void {
            $this->unlock(zstd_compress(json_encode(MMOInventory::convertInventoryToJson($inventory))));
        };
    }

    public static function create(Faction $faction): self
    {
        return new FactionVault($faction);
    }

    public function setLock(Player $player): self
    {
        Await::f2c(function () use ($player): Generator {
            Database::executeSelect(Database::UPDATE_FACTION_VAULTS_OPEN, [
                'faction_id' => $this->faction->getFactionId(),
                'player_name' => $player->getName(),
                'server_id' => NGEssentials::getInstance()->getServerManager()->getUniqueId(),
            ], yield, yield Await::REJECT);

            $results = yield Await::ONCE;

            ['result' => $result, 'contents' => $data] = $results[0];

            if ($result === 0) {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'The faction vault is currently in use by ' . $data);
                return;
            }

            $this->lockedPlayer = $player;

            if (!$player->isConnected()) {
                $this->unlock($data);
            } else {
                if ($data !== null) {
                    $contents = MMOInventory::convertJsonToContents(MMOInventory::convertStringToInventoryJSON($data));
                } else {
                    $contents = [];
                }

                $this->menu->getInventory()->setContents($contents);
                $this->menu->send($player, callback: yield);

                if (!(yield Await::ONCE)) {
                    $this->unlock($data);
                }
            }
        }, catches: Database::getFailClosure());

        return $this;
    }

    public function unlock(string $contents): void
    {
        if ($this->lockedPlayer === null) {
            return;
        }

        Await::f2c(function () use ($contents): Generator {
            Database::executeSelect(Database::UPDATE_FACTION_VAULTS_CLOSE, [
                'faction_id' => $this->faction->getFactionId(),
                'player_name' => $this->lockedPlayer->getName(),
                'server_id' => NGEssentials::getInstance()->getServerManager()->getUniqueId(),
                'contents' => $contents
            ], yield, yield Await::REJECT);

            $results = yield Await::ONCE;
            $result = $results[0]['result'];

            if (!$this->lockedPlayer->isConnected()) {
                $this->lockedPlayer = null;
                return;
            }

            $player = $this->lockedPlayer;

            if ($result === 0) {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Your faction's vaults could not be verified - this case should never happen in the first place!");
            } else if ($result === 1) {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Lock could not be removed, you are not the player opening the faction's vault.");
            } else if ($result === 2) {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "Unable to update the faction's vault, internal database error?");
            } else {
                $player->sendMessage(MMOPlugin::getPrefix() . "Successfully changed the faction's vault.");
            }

            $this->lockedPlayer = null;
        }, catches: Database::getFailClosure());
    }
}
