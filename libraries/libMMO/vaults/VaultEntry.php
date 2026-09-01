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

namespace libMMO\vaults;

use JsonException;
use libMMO\MMOPlugin;
use libMMO\player\Inventory as MMOInventory;
use libMMO\player\PlayerData;
use muqsit\invmenu\InvMenu;
use pocketmine\inventory\BaseInventory;
use pocketmine\inventory\Inventory;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class VaultEntry
{
    public const MAX_PRIVATE_VAULTS = 5;

    public function __construct(
        public string  $playerName,
        public InvMenu $menu,
        public int     $vaultId)
    {
    }

    public function getInvMenu(): InvMenu
    {
        return $this->menu;
    }

    public function doCloseInventory(): void
    {
        /** @var BaseInventory $inventory */
        $inventory = $this->menu->getInventory();
        $inventory->removeAllViewers();
    }

    /**
     * Allow faster serialization of an inventory without compression.
     * This is useful when the player is transferring between servers.
     *
     * @return string
     */
    public function fastSerialize(): string
    {
        return json_encode(MMOInventory::convertInventoryToJson($this->menu->getInventory()));
    }

    /**
     * @throws JsonException
     */
    public static function fastDeserialize(Player $player, int $vaultId, string $payload): VaultEntry
    {
        $menu = InvMenu::create(MMOPlugin::MENU_CHEST_DOUBLE);
        $menu->setName(MMOPlugin::getPrefix() . TextFormat::DARK_GRAY . 'Private Vaults #' . ($vaultId + 1));
        $menu->setInventoryCloseListener(static function (Player $player, Inventory $inventory) use ($vaultId): void {
            $playerData = MMOPlugin::getInstance()->getPlayerData();

            $vaults = $playerData->getArray($player, PlayerData::PRIVATE_VAULTS);
            $vaults[$vaultId] = base64_encode(zstd_compress(json_encode(MMOInventory::convertInventoryToJson($inventory))));

            $playerData->setValue($player, PlayerData::PRIVATE_VAULTS, $vaults);

            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::GREEN . 'Your Private Vault #' . ($vaultId + 1) . ' has been saved.');
        });

        $menu->getInventory()->setContents(MMOInventory::convertJsonToContents(json_decode($payload, true, 512, JSON_THROW_ON_ERROR)));

        return new VaultEntry($player->getName(), $menu, $vaultId);
    }
}