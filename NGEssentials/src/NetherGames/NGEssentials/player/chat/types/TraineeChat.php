<?php

declare(strict_types=1);

namespace NetherGames\NGEssentials\player\chat\types;

use NetherGames\NGEssentials\player\chat\ChatManager;
use NetherGames\NGEssentials\player\chat\kafka\channel\ChatChannel;
use NetherGames\NGEssentials\player\chat\kafka\channel\StaffChannel;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class TraineeChat extends ServerWideChat
{
    public const PREFIX = TextFormat::YELLOW . 'TRAINEE' . TextFormat::RESET . ' » ';

    public function __construct(ChatManager $chatManager)
    {
        parent::__construct(
            'Trainee Chat',
            ChatChannel::CHANNEL_TRAINEE,
            $chatManager,
        );
    }

    public function broadcast(Player $player, string $message): void
    {
        parent::broadcast($player, $message);

        $this->sendEntry($player, $message, 'trainee');
    }

    public function getPrefix(): string
    {
        return self::PREFIX;
    }

    protected function getKey(Player $player): string
    {
        /** @var StaffChannel $channel */
        $channel = $this->getChatChannel();

        return $channel->getKey();
    }
}