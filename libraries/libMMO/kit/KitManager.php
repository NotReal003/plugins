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

namespace libMMO\kit;

use libforms\elements\Button;
use libforms\FormManager;
use libMMO\item\CustomItemManager;
use libMMO\MMOPlugin;
use libMMO\player\PlayerData;
use libMMO\utils\BaseClass;
use libMMO\utils\Utils as MMOUtils;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use NetherGames\NGEssentials\utils\Utils;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function time;

class KitManager extends BaseClass
{
    /** @var Kit[] */
    private static array $kits = [];

    /**
     * Returns the items within the kit.
     *
     * @param string $title
     * @return Item[]|null
     */
    public static function getContents(string $title): ?array
    {
        return isset(self::$kits[$title]) ? self::$kits[$title]->getItems() : null;
    }

    /**
     * This MUST be called whenever the kit manager is constructed, preferably during onEnable().
     *
     * @param string $title
     * @param int $cooldown
     * @param array $items
     * @param string $permission
     * @param string $color
     */
    public function addKit(string $title, int $cooldown, array $items, string $permission = '', string $color = TextFormat::WHITE): void
    {
        if (isset(self::$kits[$title])) {
            return;
        }

        self::$kits[$title] = new Kit($title, $cooldown, $items, $permission, $color);
    }

    /**
     * Sends the menu and handles its transactions.
     *
     * @param Player $player
     * @param bool $useForm
     */
    public function send(Player $player, bool $useForm = false): void
    {
        if ($useForm) {
            $form = FormManager::createSimpleForm($player);

            if ($form !== null) {
                $form->setTitle('Kits');

                foreach ($this->getKits() as $kit) {
                    $form->addButton(new Button($kit->getTitle(), function (Player $player) use ($kit) {
                        $this->redeemKit($player, $kit);
                    }));
                }

                $form->sendForm();
            }
        } else {
            $menu = InvMenu::create('libmmo:single');
            $menu->setName('Kits');

            foreach ($this->getKits() as $kit) {
                $item = CustomItemManager::getKitItem($kit->getTitle(), $kit->getColor());
                $item->setCustomBlockData(MMOUtils::readOnlyTag());
                $menu->getInventory()->addItem($item);
            }

            $menu->setListener(InvMenu::readonly(function (DeterministicInvMenuTransaction $transaction): void {
                $player = $transaction->getPlayer();
                $itemClicked = $transaction->getItemClicked();

                if (!$itemClicked->isNull()) {
                    $this->redeemKit($player, $this->getKit($itemClicked->getNamedTag()->getString('title')));

                    $player->removeCurrentWindow();
                }
            }));

            $menu->send($player);
        }
    }

    /**
     * @return Kit[]
     */
    public function getKits(): array
    {
        return self::$kits;
    }

    public function redeemKit(Player $player, Kit $kit): void
    {
        if (($permission = $kit->getPermission()) !== '' && !$player->hasPermission($permission)) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'You do not have permission to claim this kit.');
        } elseif (($time = $this->getKitCooldown($player, $kit)) > 0) {
            $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'This kit is currently in cooldown. Time left: ' . TextFormat::AQUA . Utils::timeToHR($time));
        } else {
            $kitItem = CustomItemManager::getKitItem($kit->getTitle(), $kit->getColor());

            if ($player->getInventory()->canAddItem($kitItem)) {
                $player->getInventory()->addItem($kitItem);

                $playerData = $this->getPlugin()->getPlayerData();
                $cooldowns = $playerData->getArray($player, PlayerData::KIT_COOLDOWN);
                $cooldowns[$kit->getTitle()] = time() + $kit->getCooldown();
                $playerData->setValue($player, PlayerData::KIT_COOLDOWN, $cooldowns, true);
            } else {
                $player->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Your inventory is currently full!');
            }
        }
    }

    public function getKitCooldown(Player $player, Kit $kit): int
    {
        $cooldowns = $this->getPlugin()->getPlayerData()->getArray($player, PlayerData::KIT_COOLDOWN);

        if (isset($cooldowns[$kit->getTitle()])) {
            return $cooldowns[$kit->getTitle()] - time();
        }
        return 0;
    }

    public function getKit(string $title): ?Kit
    {
        return self::$kits[$title] ?? null;
    }
}
