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

namespace libMMO\utils;

use Closure;
use Generator;
use GlobalLogger;
use libMMO\MMOPlugin;
use libMMO\player\PlayerData;
use NetherGames\NGEssentials\player\chat\kafka\message\RawMessage;
use NetherGames\NGEssentials\player\chat\kafka\type\ChatText;
use pocketmine\utils\Utils as PMUtils;
use RdKafka\Message;
use SOFe\AwaitGenerator\Await;
use Throwable;
use function json_decode;
use function json_encode;
use const JSON_THROW_ON_ERROR;

/**
 * Subscribe/Publish event system for MMO servers. A topic may have different channels and identified by an id.
 */
abstract class EventEmitter extends BaseListener
{
    public const TOPIC_MMO_NAME = 'mmo_server';
    public const CHANNEL_DEFAULT = 'mmo:default';

    public const NOTIFICATION_BOUNTY = 0;
    public const NOTIFICATION_MONEY = 1;
    public const NOTIFICATION_BANK = 2;

    /** @var Closure[] */
    private array $listeners = [];
    /** @var string */
    private string $serverId;
    /**
     * Whether cross-server event propagation is available. This is false when NGEssentials was started
     * with `kafkaEnabled: false`, in which case this server is the sole participant of its topic: nothing
     * is published and no remote events ever arrive. Local state remains authoritative either way.
     */
    private bool $distributed;

    public function __construct(MMOPlugin $plugin)
    {
        parent::__construct($plugin);

        $essentials = $plugin->getEssentials();
        $this->serverId = explode('-', $essentials->getServerManager()->getUniqueId())[4];

        $consumer = $essentials->getConsumer();
        $this->distributed = $consumer !== null;

        if ($consumer === null) {
            $plugin->getLogger()->notice(
                'Kafka is disabled, MMO events will not propagate between servers. This is expected for a ' .
                'single-server deployment; enable kafkaEnabled in the NGEssentials config to restore it.'
            );

            return;
        }

        $playerData = $plugin->getPlayerData();
        $consumer->addTopic($this->getTopicName(), function (Message $message) use ($plugin, $playerData): void {
            [$serverId, $notificationId, $channel] = explode('-', $message->key);

            if ($serverId === $this->serverId) {
                return;
            }

            $notificationId = (int)$notificationId;

            foreach ($this->listeners as $listener) {
                try {
                    $listener($notificationId, $channel, $message->payload);
                } catch (Throwable $error) {
                    GlobalLogger::get()->error("Listener for " . PMUtils::getNiceClosureName($listener) . " has thrown an exception.");
                    GlobalLogger::get()->error("Payload: " . $message->payload);
                    GlobalLogger::get()->logException($error);
                }
            }

            // Each channel can have different payload method, default channel is always json-encoded.
            if ($channel == self::CHANNEL_DEFAULT) {
                $data = json_decode($message->payload, true, 1024, JSON_THROW_ON_ERROR);

                $player = $plugin->getServer()->getPlayerExact($data[0]);
                if ($player === null || !$player->isConnected() || !$playerData->getBool($player, PlayerData::DATA_LOADED)) {
                    return;
                }

                // TODO: Optimization and possibly dupe prevention.
                // In Factions, PLAYER_MONEY is constrained by database while in SkyBlock, it is used only when the player
                // is online, we probably need to drop this notification and force factions to use the bank?
                // Possible economy rewrite?
                switch ($notificationId) {
                    case self::NOTIFICATION_BOUNTY:
                        $playerData->loadValue($player->getName(), PlayerData::BOUNTY, function (int $balance) use ($player): void {
                            if (!$player->isConnected()) {
                                return;
                            }
                            $this->getPlugin()->getPlayerManager()->updateBountyScoreboard($player->getName(), $balance);
                        });
                        break;
                    case self::NOTIFICATION_MONEY:
                        $playerData->loadValue($player->getName(), PlayerData::PLAYER_MONEY, function (int $balance) use ($player): void {
                            if (!$player->isConnected()) {
                                return;
                            }
                            $this->getPlugin()->getPlayerData()->setValue($player, PlayerData::PLAYER_MONEY, $balance, false, true);
                            $this->getPlugin()->getPlayerManager()->updateMoneyScoreboard($player);
                        });
                        break;
                    case self::NOTIFICATION_BANK:
                        if (!isset($playerData->getColumnNames()[PlayerData::BANK_MONEY])) {
                            break; // Do not handle unknown columns notifications.
                        }

                        $playerData->loadValue($player->getName(), PlayerData::BANK_MONEY, function () {
                        });
                        break;
                }
            }
        });
    }

    /**
     * Whether events published here reach other servers. False on a single-server deployment.
     */
    public function isDistributed(): bool
    {
        return $this->distributed;
    }

    /**
     * Publish any messages to the target player, if the player is offline, it will store the contents
     * into the database and contents will be sent to the player when they are online.
     *
     * @param string $playerName the player name
     * @param string $message
     */
    public function broadcastMessage(string $playerName, string $message): void
    {
        $this->getPlugin()->getEssentials()->getPlayerManager()->getChatManager()->sendGuaranteedMessage(
            $playerName,
            new ChatText(new RawMessage($message)),
        );
    }


    /**
     * Default channel broadcaster used by the internal mmo library.
     */
    public function broadcastDefault(string $playerName, int $notificationId, array $extraData = []): void
    {
        $this->publishEvent(json_encode([
            $playerName,
            $extraData
        ]), $notificationId, self::CHANNEL_DEFAULT);
    }

    /**
     * Publish any event/notifications to certain channels identified by an id using kafka topics.
     *
     * No-op when Kafka is disabled: there are no peers to notify, and this server's own state was already
     * updated by the caller before publishing.
     */
    public function publishEvent(string $payload, int $notificationId, string $channel): void
    {
        $this->getPlugin()->getEssentials()->getPublisher()?->publishMessage($this->getTopicName(), $payload, $this->serverId . '-' . $notificationId . '-' . $channel);
    }

    /**
     * Listener callback for events published from another server.
     */
    public function addListener(Closure $closure): void
    {
        PMUtils::validateCallableSignature($closure, function (int $notificationId, string $channel, string $payload): void {
        });

        $this->listeners[] = $closure;
    }

    protected function getTopicName(): string
    {
        return self::TOPIC_MMO_NAME . '-' . $this->getPlugin()->getEssentials()->getServerManager()->getServerType();
    }
}