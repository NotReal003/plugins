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

namespace libMMO\challenges\forms;

use libforms\elements\Button;
use libforms\elements\ImageButton;
use libforms\FormManager;
use libMMO\challenges\Challenge;
use libMMO\challenges\PlayerChallengeManager;
use libMMO\challenges\RunningChallenge;
use libMMO\event\ChallengeUpdatedEvent;
use libMMO\MMOPlugin;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class ChallengeForms
{
    public static function sendChallengeList(Player $player, MMOPlugin $plugin): void
    {
        $form = FormManager::createSimpleForm($player);

        if ($form !== null) {
            $form->setTitle('Challenges');
            $form->setContent('Click a challenge to view your progress:');

            $challenges = $plugin->getPlayerChallengeManager()->getAllChallenges($player);
            usort($challenges, function (Challenge|RunningChallenge $a, Challenge|RunningChallenge $b) {
                $transform = fn(Challenge|RunningChallenge $_) => $_ instanceof Challenge ? $_ : $_->getChallenge();
                $weighted = fn(Challenge $_) => (int)!$_->isDailyChallenge(); // daily challenges are more urgent
                return $weighted($transform($a)) <=> $weighted($transform($b));
            });

            foreach ($challenges as $challenge) {
                if ($challenge instanceof Challenge) {
                    if ($challenge->isDailyChallenge()) {
                        continue;
                    }

                    $form->addButton(new Button(TextFormat::RED . $challenge->getName() . TextFormat::EOL . TextFormat::GRAY . 'Click to start', static function () use ($player, $challenge, $plugin) {
                        ChallengeForms::sendChallengeAcceptForm($player, $challenge, $plugin);
                    }));
                } elseif ($challenge instanceof RunningChallenge) {
                    if ($challenge->isDone()) {
                        if ($challenge->isClaimed()) {
                            $form->addButton(new Button(TextFormat::GREEN . $challenge->getChallenge()->getName() . TextFormat::EOL . TextFormat::GRAY . 'Completed', static function () use ($player, $plugin) {
                                ChallengeForms::sendChallengeList($player, $plugin);
                            }));
                        } else {
                            $form->addButton(new Button(TextFormat::GREEN . $challenge->getChallenge()->getName() . TextFormat::EOL . TextFormat::GRAY . 'Click to claim rewards', static function () use ($player, $challenge) {
                                $challenge->setClaimed(true);
                                $challenge->giveRewards($player);
                                $player->sendMessage(TextFormat::GREEN . 'You claimed your rewards for completing the ' . TextFormat::GOLD . $challenge->getChallenge()->getName() . TextFormat::GREEN . ' challenge!');
                            }));
                        }
                    } else {
                        $form->addButton(new Button(TextFormat::YELLOW . $challenge->getChallenge()->getName() . TextFormat::EOL . TextFormat::GRAY . 'In progress', static function () use ($player, $challenge, $plugin) {
                            ChallengeForms::getRunningChallengeForm($player, $challenge, $plugin);
                        }));
                    }
                }
            }

            $form->addButton(new ImageButton(TextFormat::RED . 'Exit', ImageButton::IMAGE_TYPE_PATH, 'textures/blocks/barrier'));

            $form->sendForm();
        }
    }

    public static function sendChallengeAcceptForm(Player $player, Challenge $challenge, MMOPlugin $plugin): void
    {
        $form = FormManager::createSimpleForm($player);

        if ($form !== null) {
            $form->setTitle($challenge->getName());
            $form->setContent($challenge->getDescription() . TextFormat::EOL . TextFormat::EOL . 'Rewards: ' . TextFormat::EOL . $challenge->getRewardsFormatted());

            $form->addButton(new Button(TextFormat::GREEN . 'Start Challenge', static function () use ($plugin, $player, $challenge) {
                if ($plugin->getPlayerChallengeManager()->addChallenge($player, new RunningChallenge($challenge))) {
                    $player->sendMessage(TextFormat::GREEN . 'You started the ' . TextFormat::GOLD . $challenge->getName() . TextFormat::GREEN . ' challenge!');
                    $event = new ChallengeUpdatedEvent($player);
                    $event->call();
                } else {
                    $player->sendMessage(TextFormat::RED . "You can't start more than " . PlayerChallengeManager::MAX_PLAYER_CHALLENGES . ' challenges at the same time.');
                }
            }));

            foreach ($challenge->getChallengeActions() as $action) {
                $form->addButton(new Button($action->toDisplayString() . TextFormat::EOL . TextFormat::RED . '0 ' . TextFormat::GRAY . '/ ' . TextFormat::RED . $action->getGoal(), static function () use ($player, $challenge, $plugin) {
                    ChallengeForms::sendChallengeAcceptForm($player, $challenge, $plugin);
                }));
            }

            $form->addButton(new ImageButton(TextFormat::RED . 'Back', ImageButton::IMAGE_TYPE_PATH, 'textures/blocks/barrier', static function () use ($player, $plugin) {
                ChallengeForms::sendChallengeList($player, $plugin);
            }));

            $form->sendForm();
        }
    }

    public static function getRunningChallengeForm(Player $player, RunningChallenge $challenge, MMOPlugin $plugin): void
    {
        $form = FormManager::createSimpleForm($player);

        if ($form !== null) {
            $form->setTitle($challenge->getChallenge()->getName());
            $form->setContent($challenge->getChallenge()->getDescription() . TextFormat::EOL . TextFormat::EOL . 'Rewards: ' . TextFormat::EOL . $challenge->getChallenge()->getRewardsFormatted() . TextFormat::EOL . TextFormat::EOL . ($challenge->isDone() ? TextFormat::GREEN . 'Challenge completed' : ($challenge->getChallenge()->isDailyChallenge() ? TextFormat::RED . 'Daily challenge cannot be cancelled' : TextFormat::GOLD . 'Challenge in progress')));

            if (!$challenge->getChallenge()->isDailyChallenge()) {
                $form->addButton(new Button(TextFormat::RED . 'Cancel Challenge' . TextFormat::EOL . TextFormat::GRAY . 'Your progress will be lost', static function () use ($player, $challenge, $plugin) {
                    $plugin->getPlayerChallengeManager()->removeChallenge($player, $challenge->getChallenge()->getId());
                    $player->sendMessage(TextFormat::RED . 'You cancelled the ' . TextFormat::AQUA . $challenge->getChallenge()->getName() . TextFormat::RED . ' challenge.');

                    $event = new ChallengeUpdatedEvent($player, $challenge);
                    $event->call();
                }));
            }

            foreach ($challenge->getChallenge()->getChallengeActions() as $key => $action) {
                $progress = $challenge->getProgress();
                if (isset($progress[$key])) {
                    if ($action->reached($progress[$key])) {
                        $form->addButton(new Button($action->toDisplayString() . TextFormat::EOL . TextFormat::GREEN . 'Complete', static function () use ($player, $challenge, $plugin) {
                            ChallengeForms::getRunningChallengeForm($player, $challenge, $plugin);
                        }));
                    } else {
                        $form->addButton(new Button($action->toDisplayString() . TextFormat::EOL . TextFormat::GOLD . $challenge->getProgress()[$key] . TextFormat::GRAY . ' / ' . TextFormat::GOLD . $action->getGoal(), static function () use ($player, $challenge, $plugin) {
                            ChallengeForms::getRunningChallengeForm($player, $challenge, $plugin);
                        }));
                    }
                } else {
                    $form->addButton(new Button($action->toDisplayString() . TextFormat::EOL . TextFormat::RED . 0 . TextFormat::GRAY . ' / ' . TextFormat::RED . $action->getGoal(), static function () use ($player, $challenge, $plugin) {
                        ChallengeForms::getRunningChallengeForm($player, $challenge, $plugin);
                    }));
                }
            }

            $form->addButton(new ImageButton(TextFormat::RED . 'Back', ImageButton::IMAGE_TYPE_PATH, 'textures/blocks/barrier', static function () use ($player, $plugin) {
                ChallengeForms::sendChallengeList($player, $plugin);
            }));

            $form->sendForm();
        }
    }
}