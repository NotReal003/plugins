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
use factions\utils\Database;
use GlobalLogger;
use NetherGames\NGEssentials\player\permissions\Permissions;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use poggit\libasynql\base\DataConnectorImpl;
use poggit\libasynql\base\QuerySendQueue;
use poggit\libasynql\base\SqlThreadPool;
use Threaded;

/**
 * Database debugging tool, useful to retrieve any pending queries from libasynql
 */
class DebugCommand extends Command
{
    public function __construct()
    {
        parent::__construct('debug');

        $this->setDescription('Debugging tool');
        $this->setUsage(TextFormat::RED . '/debug <total|query>');

        $this->setPermission(Permissions::RANK_DEVELOPER);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if (!$this->testPermission($sender)) {
            return true;
        }

        /** @var DataConnectorImpl $database */
        $database = Database::getMySQLDatabase();

        /** @var SqlThreadPool $threadPool */
        $threadPool = (function () {
            /** @noinspection PhpUndefinedFieldInspection */
            return $this->thread;
        })->call($database);

        /** @var QuerySendQueue $bufferSend */
        $bufferSend = (function () {
            /** @noinspection PhpUndefinedFieldInspection */
            return $this->bufferSend;
        })->call($threadPool);

        $result = $bufferSend->synchronized(function () use ($bufferSend) {
            /** @var Threaded $queries */
            $queries = (function () {
                /** @noinspection PhpUndefinedFieldInspection */
                return $this->queries;
            })->call($bufferSend);

            $queryData = [];

            foreach ($queries as $row) {
                [$queryId, $mode, $query, $params] = unserialize($row, ["allowed_classes" => true]);

                $queryData[] = [$mode, $query, $params];
            }

            return $queryData;
        });

        $sender->sendMessage(Factions::getPrefix() . "Total queries pending: " . count($result));
        $sender->sendMessage(Factions::getPrefix() . "Worker load: " . $threadPool->getLoad() . '%');

        foreach ($result as [$mode, $query, $args]) {
            $sender->sendMessage($mode . ' -> ' . $query);
            $sender->sendMessage('Data: ' . json_encode($args));

            GlobalLogger::get()->warning("Queuing mode-$mode query: " . str_replace(["\r\n", "\n"], "\\n ", $query) . " | Args: " . json_encode($args));
        }

        return true;
    }
}