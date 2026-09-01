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

namespace factions\commands;

use factions\Factions;
use factions\player\tags\TagManager;
use libforms\elements\ImageButton;
use libforms\FormManager;
use libMMO\MMOPlugin;
use libMMO\player\MMOPlayer;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class TagCommand extends BaseCommand
{

    public function __construct(Factions $owningPlugin)
    {
        parent::__construct('tag', $owningPlugin);

        $this->setDescription('Manage tags');
        $this->setUsage(TextFormat::RED . '/tag <set/add>');
    }

    public function executeCommand(Player $sender, string $commandLabel, array $args): bool
    {
        $playerData = $this->getOwningPlugin()->getPlayerData();
        switch ($args[0] ?? "") {
            case 'set':
                $form = FormManager::createSimpleForm($sender);
                if ($form === null) {
                    break;
                }

                $form->setTitle(MMOPlugin::getPrefix() . TextFormat::DARK_GRAY . 'Set Tag Form');
                $tags = $playerData->getOwnedTags($sender);
                if (empty($tags)) {
                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You do not own any tags.");
                    break;
                }

                foreach ($tags as $tag) {
                    $form->addButton(new ImageButton($tag, ImageButton::IMAGE_TYPE_URL, 'https://cdn.nethergames.org/img/factions/items/421-0.png', function (Player $player) use ($tag, $playerData) {
                        $playerData->setCurrentTag($player, $tag);

                        $this->sendMessage($player, 'You set your tag to ' . $tag);
                    }));
                }

                $form->sendForm();
                break;
            case 'add':
                if (!$sender->hasPermission('nethergames.staff')) {
                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . "You don't have permission to execute that command.");
                } else if (($tag = TagManager::searchTagsId($args[1] ?? '')) === null) {
                    $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Such tag does not exists, custom tags are not supported.');
                } else if (($player = Server::getInstance()->getPlayerExact($args[2] ?? '')) !== null && $player instanceof MMOPlayer) {
                    $playerData->addTags($player, $tag);

                    $sender->sendMessage(MMOPlugin::getPrefix() . "You added $args[1] to one of {$player->getName()}'s tags.");
                } else {
                    if (($args[2] ?? '') === '') {
                        $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . 'Please specify a player first.');
                    } else {
                        $sender->sendMessage(MMOPlugin::getPrefix() . TextFormat::RED . $args[2] . " is an invalid player.");
                    }
                }
                break;
            default:
                throw new InvalidCommandSyntaxException();
        }

        return true;
    }
}