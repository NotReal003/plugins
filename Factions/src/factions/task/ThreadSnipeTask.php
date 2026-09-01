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

namespace factions\task;

use Closure;
use pocketmine\scheduler\AsyncTask;
use pmmp\thread\Thread as NativeThread;

class ThreadSnipeTask extends AsyncTask
{
    private const TLS_CALLBACK_CALL = "callback";
    private const TLS_WORKER_ID = "worker_id";

    /** @var string */
    private $result;

    public function __construct(Closure $result, int $workerId)
    {
        $this->storeLocal(self::TLS_CALLBACK_CALL, $result);
        $this->storeLocal(self::TLS_WORKER_ID, $workerId);
    }

    public function onRun(): void
    {
        $data = [memory_get_usage(), NativeThread::getCurrentThreadId()];

        $this->result = igbinary_serialize($data);
    }

    public function onCompletion(): void
    {
        $result = igbinary_unserialize($this->result);

        $closure = $this->fetchLocal(self::TLS_CALLBACK_CALL);
        $workerId = $this->fetchLocal(self::TLS_WORKER_ID);
        $closure($result, $workerId);
    }
}