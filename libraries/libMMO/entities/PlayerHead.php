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

namespace libMMO\entities;

use Closure;
use Generator;
use libMMO\MMOPlugin;
use libMMO\player\Inventory;
use libMMO\player\PlayerData;
use LogicException;
use muqsit\invmenu\InvMenu;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\BaseInventory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;
use SOFe\AwaitGenerator\Await;
use function array_merge;
use function fclose;
use function number_format;
use function random_bytes;
use function round;
use function str_repeat;
use function stream_get_contents;
use function time;

class PlayerHead extends Human
{
    private const GEOMETRY_NAME = 'geometry.playerHead';
    private const GEOMETRY_DATA_PATH = 'playerhead.json';

    private const AGE_LIMIT = 4000;

    private const NAME_TAG = 'NameTag';
    private const INVENTORY_TAG = 'headInventoryTag';
    private const REWARD_TAG = 'rewardMoneyTag';
    private const BOUNTY_TAG = 'bountyTag';

    /** @phpstan-var Closure(Player $damager, string $victimName, int $rewardMoney, int $bountyMoney): void */
    private static ?Closure $onInteractCallback = null;
    /** @var string */
    private static $geometryData;
    /** @var int */
    private int $age = 0;
    /** @var InvMenu|null */
    private ?InvMenu $headMenu = null;
    /** @var string */
    private string $playerName = '';
    /** @var int */
    private int $rewardMoney;
    /** @var int */
    private int $bountyMoney = 0;
    /** @var int */
    private int $time = 0;

    public static function onInteractCallback(Closure $callback): void
    {
        Utils::validateCallableSignature(function (Player $damager, string $victimName, int $rewardMoney, int $bountyMoney): void {}, $callback);

        self::$onInteractCallback = $callback;
    }

    public function __construct(Location $location, ?CompoundTag $nbt = null, ?Player $player = null,  array $inventoryContents = [], bool $useBounty = false)
    {
        if ($player !== null) {
            $skin = $player->getSkin();
            $skin = new Skin($skin->getSkinId(), $skin->getSkinData(), $skin->getCapeData(), self::GEOMETRY_NAME, self::$geometryData);

            $playerData = MMOPlugin::getInstance()->getPlayerData();

            $this->rewardMoney = $playerData->getInt($player, PlayerData::PLAYER_MONEY);
            $playerData->setValue($player, PlayerData::PLAYER_MONEY, 0);

            $playerName = $player->getName();
            if ($useBounty) {
                Await::f2c(function () use ($playerName, $playerData): Generator {
                    $playerData->addCallbackToPlayer($playerName, yield);

                    yield Await::ONCE;

                    await: /* Asynchronous recursion, this prevents getting an outdated bounty values */
                    $playerData->loadValue($playerName, PlayerData::BOUNTY, yield);

                    $currentBounty = yield Await::ONCE;
                    if ($playerData->isBeingSaved($playerName)) {
                        MMOPlugin::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(yield), 20);
                        yield Await::ONCE;

                        goto await;
                    }

                    if ($currentBounty > 0) {
                        $this->bountyMoney = $currentBounty;

                        $playerData->setValue($playerName, PlayerData::BOUNTY, 0, true);
                        if (!$playerData->getBool($playerName, PlayerData::DATA_LOADED)) {
                            $playerData->saveValue($playerName, PlayerData::BOUNTY);
                        }
                    }
                });
            }

            $this->playerName = $playerName;
        } elseif ($nbt !== null) {
            $inventoryTag = $nbt->getTag(self::INVENTORY_TAG);
            $nameTag = $nbt->getTag(self::NAME_TAG);
            $reward = $nbt->getInt(self::REWARD_TAG, 0);
            $bounty = $nbt->getInt(self::BOUNTY_TAG, 0);

            $skin = Human::parseSkinNBT($nbt);

            if ($inventoryTag instanceof ListTag && $nameTag instanceof StringTag) {
                $inventoryContents = Inventory::convertNBTToComponents($inventoryTag);

                $this->bountyMoney = $bounty;
                $this->rewardMoney = $reward;
                $this->playerName = $nameTag->getValue();
            } else {
                parent::__construct($location, $skin);

                $this->flagForDespawn();
                return;
            }
        } else {
            parent::__construct($location, new Skin("Standard_Custom", str_repeat(random_bytes(3) . "\xff", 2048)));

            $this->flagForDespawn();
            return;
        }

        $headMenu = InvMenu::create(MMOPlugin::MENU_CHEST_PLAYERHEAD);

        $inventory = $headMenu->getInventory();
        $inventory->setContents($inventoryContents);

        $headMenu->setName($this->playerName . 's Inventory');
        $headMenu->setInventoryCloseListener(function (Player $player, \pocketmine\inventory\Inventory $closedInventory): void {
            if (!$this->isClosed() && $this->getLocation()->isValid() && count($closedInventory->getContents()) === 0 && $this->bountyMoney === 0 && count($closedInventory->getViewers()) === 1) {
                $this->flagForDespawn();
            }
        });

        $this->headMenu = $headMenu;

        parent::__construct($location, $skin);
    }

    public static function setup(MMOPlugin $plugin): void
    {
        $resource = $plugin->getResource(self::GEOMETRY_DATA_PATH);
        self::$geometryData = (string)stream_get_contents($resource);
        fclose($resource);
    }

    public function onDispose(): void
    {
        if ($this->headMenu !== null) {
            $inventory = $this->headMenu->getInventory();
            if ($inventory instanceof BaseInventory) {
                $inventory->removeAllViewers();
            }
        }

        parent::onDispose();
    }

    /**
     * Save the player head data to NBT.
     *
     * @return CompoundTag
     */
    public function saveNBT(): CompoundTag
    {
        $nbt = parent::saveNBT();

        $nbt->setString(self::NAME_TAG, $this->playerName);
        $nbt->setInt(self::REWARD_TAG, $this->rewardMoney);
        $nbt->setInt(self::BOUNTY_TAG, $this->bountyMoney);

        /** @var BaseInventory $inventory */
        $inventory = $this->getHeadMenu()->getInventory();
        $nbt->setTag(self::INVENTORY_TAG, Inventory::convertInventoryToNBT($inventory));

        return $nbt;
    }

    public function getHeadMenu(): InvMenu
    {
        if ($this->headMenu === null) {
            throw new LogicException('Head Menu is null');
        }

        return $this->headMenu;
    }

    /**
     * Runs at the entity's base tick.
     *
     * @param int $tickDiff
     * @return bool
     */
    public function entityBaseTick(int $tickDiff = 1): bool
    {
        if (!$this->isFlaggedForDespawn()) {
            if ($this->age > self::AGE_LIMIT) {
                $this->flagForDespawn();
            } else {
                $this->age += $tickDiff;

                // update nametag after every second
                if (time() > $this->time) {
                    $this->time = time();
                    $this->updateNameTag();
                }
            }
        }

        return parent::entityBaseTick($tickDiff);
    }

    /**
     * Updates the name-tag to a smart name-tag
     * which displays important information
     * about the dropped entity.
     *
     * @return void
     */
    private function updateNameTag(): void
    {
        $timeLeft = round((self::AGE_LIMIT - $this->age) / 20);

        $color = TextFormat::GREEN;
        if ($timeLeft <= 30) {
            if ($timeLeft > 10) {
                $color = TextFormat::YELLOW;
            } else {
                $color = TextFormat::RED;
            }
        }

        $display = TextFormat::BOLD . TextFormat::GOLD . $this->playerName . 's Inventory' . TextFormat::EOL . $color . 'Removed in ' . $timeLeft . 's';

        if ($this->rewardMoney !== 0 || $this->bountyMoney !== 0) {
            $display .= TextFormat::EOL . TextFormat::RED . '$' . number_format($this->rewardMoney + $this->bountyMoney) . ' in the grave!';
        }

        $this->setNameTag($display);
    }

    public function attack(EntityDamageEvent $source): void
    {
        if ($source instanceof EntityDamageByEntityEvent && !$source instanceof EntityDamageByChildEntityEvent && !$source->isCancelled()) {
            $damager = $source->getDamager();
            if ($damager instanceof Player) {
                $source->cancel();
                $this->getHeadMenu()->send($damager);

                if ($this->rewardMoney > 0 || $this->bountyMoney > 0) {
                    (self::$onInteractCallback)($damager, $this->playerName, $this->rewardMoney, $this->bountyMoney);

                    $this->rewardMoney = 0;
                    $this->bountyMoney = 0;
                }
            }
        }
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(0.1, 0.75);
    }

    protected function initEntity(CompoundTag $nbt): void
    {
        $this->time = time();

        parent::initEntity($nbt);

        $this->setNameTagVisible(true);
        $this->setNameTagAlwaysVisible(true);
    }
}