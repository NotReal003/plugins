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

namespace libMMO\forms;

use libforms\elements\ImageButton;
use libforms\elements\Label;
use libforms\FormManager;
use libMMO\item\enchantment\Enchantment;
use libMMO\MMOPlugin;
use libMMO\player\enchantment\EnchantmentManager;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\item\enchantment\Enchantment as PMEnchantment;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class EnchantListForm
{
    /** @var array */
    public static array $enchants = [
        EnchantmentManager::FROSTY_ARROWS => ['Frosty Arrows (Level 1-2)', 'Bow', 'Every shot to a player has the chance of giving Slowness I or II'],
        EnchantmentManager::COMBO => ['Combo', 'Sword', 'Grants you a Strength I after hitting your opponent 2 times and Strength II after hitting your opponent 4 times without getting any hit. Strength lasts 5 seconds, duration will reset itself if you hit someone again and removed if your opponent hit you.'],
        EnchantmentManager::GLOW => ['Glow', 'Helmet', 'Grants the player night vision enchantment while being worn.'],
        EnchantmentManager::MINER => ['Miner (Levels 1-2)', 'Pickaxe', 'Gives the player the haste effect while holding the pickaxe (Haste I or II depending on level)'],
        EnchantmentManager::BOOM => ['Boom', 'Pickaxe', 'Every block you break has a chance to break all blocks in a 3x3x3 area (calculated from the block you are breaking)'],
        EnchantmentManager::ACCELERATE => ['Accelerate', 'Boots', 'Gives the player wearing the boots the Speed II effect while being worn'],
        EnchantmentManager::POISON => ['Poison', 'Sword', 'Every hit you deal to a player with the enchanted sword has a chance to give the opponent a Poison I effect'],
        EnchantmentManager::THOR => ['Thor', 'Sword', 'Every hit on the opponent has the chance to strike the opponent with lightning, dealing additional damage'],
        EnchantmentManager::GRAPPLE => ['Grapple', 'Bow', 'Boosts you towards your opponent when shooting.'],
        EnchantmentManager::TRIPLE_SHOT => ['Triple Shot', 'Bow', "Every shot with a bow shoots one three arrows instead of one but only uses one arrow from the player's inventory"],
        EnchantmentManager::GUARDIAN_ANGEL => ['Guardian', 'Armour', 'When going below three hearts in a fight, your health is immediately restored. A cooldown of 30 minutes per use applies, multiple enchanted items do not stack.'],
        EnchantmentManager::TANK => ['Tank', 'Armour', 'Increases your health to help prevent attacks'],
        EnchantmentManager::EVASION => ['Evasion', 'Armour', 'Grants you a chance to evade an attack'],
        EnchantmentManager::IMMUNITY => ['Immunity', 'Armour', 'Grants immunity to any negative effects from attacks'],
        EnchantmentManager::KARMA => ['Karma', 'Armour', 'Grants you a chance reflect damage sustained to the attacker'],
        EnchantmentManager::DECAY => ['Decay', 'Sword', "Allows you to destroy your opponent's armour quicker"],
        EnchantmentManager::DEBILITATE => ['Debilitate', 'Sword', 'Strikes your opponent with negative effects (blindness & slowness)'],
        EnchantmentManager::PILFER => ['Pilfer', 'Sword', 'Grants you a chance to steal coins from your opponent'],
        EnchantmentManager::VELOCITY => ['Velocity', 'Bow', 'Makes your arrows faster when shot'],
        EnchantmentManager::DETONATION => ['Detonation', 'Bow', 'Causes an explosion when an arrow hits your opponent'],
        EnchantmentManager::REPLENISH => ['Replenish', 'Sword', 'Allows you to gain hunger every hit'],
        EnchantmentManager::ESCAPE => ['Escape', 'Sword', 'Allows you to obtain speed, invisibility, and jump boost for 5 seconds'],
        EnchantmentManager::DETECT => ['Detect', 'Pickaxe', 'Increases your chances of winning crate keys while mining'],
        EnchantmentManager::DIZZY => ['Dizzy', 'Sword', 'Grants you a chance to strike your opponent with nausea'],
        EnchantmentManager::VAMPIRE => ['Vampire', 'Sword', 'Grants you a chance to steal a heart off your opponent'],
        EnchantmentManager::MERMAID => ['Mermaid', 'Helmet', 'Allows you to breathe underwater'],
        EnchantmentManager::SPRING => ['Spring', 'Boots', 'Allows you to jump higher'],
        EnchantmentManager::MOLTEN => ['Molten', 'Sword', 'Grants you a chance to set your opponent on fire'],
        EnchantmentManager::SWIPE => ['Swipe', 'Sword', 'Grants you a chance to steal XP from your opponent'],
        EnchantmentManager::ENDURANCE => ['Endurance', 'Armour', 'Allows you to obtain a regeneration effect while on low health'],
        EnchantmentManager::FAMINE => ['Famine', 'Sword', 'Grants you a chance to steal hunger bars from your opponent'],
        EnchantmentManager::ENTANGLEMENT => ['Entanglement', 'Bow', 'Grants you a chance to freeze your opponent for 5 seconds'],
        EnchantmentManager::DRUNK => ['Drunk', 'Helmet', 'Allows you to obtain Strength I at the cost of nausea'],
        EnchantmentManager::KILL_AURA => ['Kill Aura', 'Sword, Bow', 'A chance to kill multiple stacks of monsters in a stack each death event.'],
        EnchantmentManager::LIFESTEAL => ['Lifesteal', 'Sword', 'Regain health when attacking with a chance of 15% for 2 seconds. (Level I for Regeneration III and level II for Regeneration IV)'],
        EnchantmentManager::LETHAL_PRECISION => ['Lethal Precision', 'Bow', 'Every headshot damage will have a chance to deal damage up to 18 percent. (Every levels will gain 3% damage)']
    ];

    public static function sendEnchantListForm(Player $player): void
    {
        $form = FormManager::createSimpleForm($player);

        if ($form !== null) {
            $form->setTitle(MMOPlugin::getPrefix() . TextFormat::DARK_GRAY . "Enchantment List");
            $form->setContent("Available enchantments:");

            foreach (self::$enchants as $index => $ench) {
                /** @var PMEnchantment $enchantment */
                $enchantment = EnchantmentIdMap::getInstance()->fromId($index);
                if (EnchantmentManager::isEnchantExcluded($enchantment)) {
                    continue;
                }

                $form->addButton(new ImageButton($ench[0], ImageButton::IMAGE_TYPE_PATH, "textures/items/book_enchanted", function (Player $player) use ($index): void {
                    self::sendEnchantInfoForm($player, $index);
                }));
            }

            $form->sendForm();
        }
    }

    public static function sendEnchantInfoForm(Player $player, int $index): void
    {
        $form = FormManager::createCustomForm($player, function (Player $player): void {
            self::sendEnchantListForm($player);
        });

        if ($form !== null) {
            $info = self::$enchants[$index];
            $ench = EnchantmentIdMap::getInstance()->fromId($index);

            $form->setTitle($info[0]);
            $form->addElement(new Label(TextFormat::YELLOW . "Applicable Items: " . TextFormat::WHITE . $info[1]));
            $form->addElement(new Label(TextFormat::YELLOW . "Rarity: " . TextFormat::WHITE . Enchantment::RARITY_NAMES[$ench->getRarity()]));
            $form->addElement(new Label(TextFormat::YELLOW . "Max level: " . TextFormat::WHITE . EnchantmentManager::getMaxEnchantmentLevel($ench)));
            $form->addElement(new Label(TextFormat::YELLOW . "Description: " . TextFormat::WHITE . $info[2]));

            $form->sendForm();
        }
    }
}