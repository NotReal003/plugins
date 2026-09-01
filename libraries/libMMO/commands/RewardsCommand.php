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

namespace libMMO\commands;

use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use libMMO\player\PlayerData;
use libMMO\utils\Utils;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\block\BlockTypeIds;
use pocketmine\inventory\Inventory;
use pocketmine\item\ItemTypeIds;
use pocketmine\player\Player;

class RewardsCommand extends BaseCommand
{

    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct('rewards', $plugin);

        $this->setDescription('Rewards command');
    }

    public function executeCommand(MMOPlayer $sender, string $commandLabel, array $args): bool
    {
        $data = $this->getOwningPlugin()->getPlayerData();

        $menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
        $menu->setName('Rewards');
        $menu->setListener(static function (InvMenuTransaction $transaction): InvMenuTransactionResult {
            if (ItemTypeIds::toBlockTypeId($transaction->getItemClickedWith()->getTypeId()) !== BlockTypeIds::AIR) {
                return $transaction->discard();
            }

            return $transaction->continue();
        });

        $menu->setInventoryCloseListener(static function (Player $player, Inventory $inventory) use ($data): void {
            $contents = [];
            foreach ($inventory->getContents() as $content) {
                $contents[] = Utils::zlibEncodeItem($content);
            }
            $data->setValue($player, PlayerData::REWARDS, $contents);
        });

        $contents = $data->getArray($sender, PlayerData::REWARDS);
        foreach ($contents as $content) {
            $menu->getInventory()->addItem(Utils::decodeItem($content));
        }

        $menu->send($sender);

        return true;
    }
}