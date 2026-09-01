<?php
/**
 *         _____ _          _     _            _
 *        / ____| |        | |   | |          | |
 *  __  _| (___ | | ___   _| |__ | | ___   ___| | __
 *  \ \/ /\___ \| |/ / | | | '_ \| |/ _ \ / __| |/ /
 *   >  < ____) |   <| |_| | |_) | | (_) | (__|   <
 *  /_/\_\_____/|_|\_\\__, |_.__/|_|\___/ \___|_|\_\
 *                     __/ |
 *                    |___/
 *
 * Copyright (C) 2016-2022 NetherGames Network
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author Shaheryar Sohail, Driesboy, TobiasDev, TwistedAsylumMC, Drew, larryTheCoder
 */

declare(strict_types=1);

namespace skyblock\islands;

use Generator;
use libMMO\entities\stackable\StackableInterface;
use libMMO\MMOPlugin;
use libMMO\utils\AdventureSettingsObject;
use libMMO\utils\AwaitUtils;
use libMMO\utils\Permissions as MMOPermissions;
use NetherGames\NGEssentials\NGEssentials;
use NetherGames\NGEssentials\player\permissions\Permissions;
use NetherGames\NGEssentials\player\PlayerData;
use NetherGames\NGEssentials\player\PlayerData as NGPlayerData;
use NetherGames\NGEssentials\player\social\friends\FriendsManager;
use pocketmine\entity\Location;
use pocketmine\entity\object\ExperienceOrb;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\utils\TextFormat;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use pocketmine\world\World;
use skyblock\entities\helpers\MiniHelper;
use skyblock\entities\island\IslandNPC;
use skyblock\islands\feature\block\BlockCounter;
use skyblock\islands\feature\IslandLevelSpec;
use skyblock\SkyBlock;
use skyblock\task\IslandBorderTask;
use skyblock\utils\Database;
use SOFe\AwaitGenerator\Await;
use function array_filter;

class Island
{
    public const DATA_TYPE = 0;
    public const DATA_OWNER = 1;
    public const DATA_LEVEL = 2;
    public const DATA_MEMBERS_DATA = 4;
    public const DATA_PVP = 5;
    // RESERVED (ARRAY)
    public const DATA_VIP = 7;
    public const DATA_HELPERS = 8;
    public const DATA_SPAWN = 9;

    public const PERMISSION_INTERACT = 0;
    public const PERMISSION_BUILD = 1;
    public const PERMISSION_INVENTORY = 2;

    public const TYPE_DESERT = 0;
    public const TYPE_GREEK = 1;
    public const TYPE_JUNGLE = 2;
    public const TYPE_MODERN = 3;
    public const TYPE_SCRUBLAND = 4;
    public const TYPE_SNOWY = 5;
    public const TYPE_TOWN = 6;

    public const MAP_NAME = 0;
    public const MAP_DESC = 1;
    public const MAP_PERMISSION = 2;
    public const MAP_NPC_SPAWN = 3;

    public const SKY_BLOCK_DATA = [
        self::TYPE_DESERT => [
            self::MAP_NAME => 'Desert',
            self::MAP_DESC => 'Fields of sand',
            self::MAP_PERMISSION => 'nethergames.vip.ultra',
            self::MAP_NPC_SPAWN => [-3, 94, 1],
        ],
        self::TYPE_GREEK => [
            self::MAP_NAME => 'Greek',
            self::MAP_DESC => 'An ancient temple',
            self::MAP_PERMISSION => 'nethergames.vip.ultra',
            self::MAP_NPC_SPAWN => [-5, 95, -9],
        ],
        self::TYPE_JUNGLE => [
            self::MAP_NAME => 'Jungle',
            self::MAP_DESC => 'Overgrown wilds await',
            self::MAP_PERMISSION => 'nethergames.vip.legend',
            self::MAP_NPC_SPAWN => [12, 102, 17],
        ],
        self::TYPE_MODERN => [
            self::MAP_NAME => 'Modern',
            self::MAP_DESC => 'A stylish estate',
            self::MAP_PERMISSION => 'nethergames.vip.ultra',
            self::MAP_NPC_SPAWN => [7, 94, 7],
        ],
        self::TYPE_SCRUBLAND => [
            self::MAP_NAME => 'Scrubland',
            self::MAP_DESC => 'A lively clearing',
            self::MAP_PERMISSION => '',
            self::MAP_NPC_SPAWN => [-11, 93, -2],
        ],
        self::TYPE_SNOWY => [
            self::MAP_NAME => 'Snowy',
            self::MAP_DESC => 'Frosty winter wonderland',
            self::MAP_PERMISSION => '',
            self::MAP_NPC_SPAWN => [2, 59, 6],
        ],
        self::TYPE_TOWN => [
            self::MAP_NAME => 'Town',
            self::MAP_DESC => 'A quaint village',
            self::MAP_PERMISSION => 'nethergames.vip.ultra',
            self::MAP_NPC_SPAWN => [-8, 93, 5],
        ]
    ];

    /** @var int */
    private int $islandType;

    /** @var string */
    private string $owner;
    /** @var string */
    private string $ownerXuid;
    /** @var bool[][] */
    private array $permissionsData = [];

    /** @var World|null */
    private ?World $world = null;
    /** @var Location|null */
    private ?Location $spawnVector = null;

    /** @var BlockCounter */
    private BlockCounter $blockCounter;
    /** @var IslandLevelSpec */
    private IslandLevelSpec $xpLevelSpec;

    /** @var int[] */
    private array $helpers;
    /** @var bool */
    private bool $pvpEnabled;
    /** @var bool */
    private bool $islandPublic;
    /** @var bool */
    private bool $hasVip = false;
    /** @var bool */
    private bool $autoUnload = true;
    /** @var bool */
    private bool $isLocked = false; // Locked for read-write operation.

    /** @var Player|null */
    public ?Player $snooper = null;
    /** @var TaskHandler<IslandBorderTask>|null */
    private ?TaskHandler $borderTaskHandler = null;
    /** @var true[] */
    private array $loadedChunks = [];

    public function __construct(string $owner, string $ownerXuid, array $data, bool $public = false)
    {
        $this->owner = $owner;
        $this->ownerXuid = $ownerXuid;

        /** @var IslandLevelSpec $xpLevelSpec */
        $xpLevelSpec = IslandLevelSpec::get($data[self::DATA_LEVEL] ?? 1) ?? IslandLevelSpec::get(1);
        $this->xpLevelSpec = $xpLevelSpec;
        $this->blockCounter = new BlockCounter();

        if (isset($data[self::DATA_SPAWN])) {
            if (is_string($spawn = $data[self::DATA_SPAWN])) {
                [$x, $y, $z, $yaw] = explode(':', $spawn);

                $location = new Location((int)floor((float)$x), (int)floor((float)$y), (int)floor((float)$z), null, (float)$yaw, 0);
            } else {
                [$hash, $yaw] = $spawn;

                World::getBlockXYZ($hash, $x, $y, $z);
                $location = new Location($x, $y, $z, null, $yaw, 0);
            }

            $this->spawnVector = $location;
        }

        $this->islandType = $data[self::DATA_TYPE];
        $this->helpers = $data[self::DATA_HELPERS] ?? [];
        $this->pvpEnabled = $data[self::DATA_PVP] ?? false;
        $this->islandPublic = $public;

        Await::f2c(function () use ($owner, $data) {
            $plugin = MMOPlugin::getInstance();
            if (($player = $plugin->getServer()->getPlayerExact($owner)) !== null) {
                AwaitUtils::waitPlayerSpawned($player, yield);
                yield Await::ONCE;

                while (true) {
                    if ($plugin->getEssentials()->getPlayerData()->getBool($player, NGPlayerData::DATA_LOADED)) {
                        break;
                    }

                    $plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(yield), 20);
                    yield Await::ONCE;
                }

                $this->hasVip = $player->hasPermission('nethergames.vip.ultra');
            } else {
                $this->hasVip = $data[self::DATA_VIP] ?? false;
            }
        });

        $this->updateMembersData($data[self::DATA_MEMBERS_DATA] ?? []);
    }

    private function updateBorderTask(?World $world): void
    {
        if ($this->world !== null && ($world === null || $this->world !== $world || !$world->isLoaded())) {
            $this->borderTaskHandler->cancel();
            $this->borderTaskHandler = null;
        }

        if ($world === null || !$world->isLoaded()) {
            return;
        }

        $scheduler = MMOPlugin::getInstance()->getScheduler();
        $this->borderTaskHandler = $scheduler->scheduleRepeatingTask(new IslandBorderTask($world, $this), 2);
    }

    /**
     * Enforces xuid keys for friend members. This can only work
     * if the player joined SkyBlock before.
     *
     * @param array $membersData
     */
    public function updateMembersData(array $membersData): void
    {
        Await::f2c(function () use ($membersData): Generator {
            $friends = [];

            $ess = NGEssentials::getInstance();
            if ($ess->isDisabled()) { // Probable fix for "You cannot schedule a query on an invalidated queue."
                return;
            }

            $socialManager = $ess->getPlayerManager()->getSocialManager()->getFriendsManager();
            if (($owner = $ess->getServer()->getPlayerExact($ownerName = $this->getOwner())) === null || !$ess->getPlayerData()->getBool($owner, PlayerData::DATA_LOADED)) {
                $socialManager->loadRelations($ownerName, yield);
                $relations = yield Await::ONCE;

                foreach ($relations as $playerName => $relation) {
                    if ($relation === FriendsManager::RELATION_FRIEND) {
                        $friends[] = $playerName;
                    }
                }
            } else {
                $friends = $socialManager->getFriends($owner);
            }

            $oldMembersData = $membersData;
            $newMembersData = [];

            $parsedFriends = [];
            $query = 'SELECT player, xuid FROM player_data';
            if (count($friends) > 0) {
                $query .= ' WHERE';

                $arguments = [];
                foreach ($friends as $playerName) {
                    $query .= " player LIKE ? OR";
                    $arguments[] = $playerName;
                }

                $query = substr($query, 0, strlen($query) - 3);

                Database::executeSelectRaw($query, $arguments, yield, yield Await::REJECT);

                $factionRows = yield Await::ONCE;

                foreach ($factionRows as ["player" => $player, "xuid" => $xuid]) {
                    $parsedFriends[$player] = $xuid;
                }
            }

            foreach ($parsedFriends as $friendXuid) {
                if (isset($oldMembersData[$friendXuid]) && count($oldMembersData[$friendXuid]) !== 0) {
                    $newMembersData[$friendXuid] = $oldMembersData[$friendXuid];
                }
            }

            foreach ($oldMembersData as $memberName => $data) {
                if (!is_numeric($memberName)) {
                    foreach ($parsedFriends as $friend => $friendXuid) {
                        if (strtolower($friend) !== strtolower($memberName)) {
                            continue;
                        }

                        $newMembersData[$friendXuid] = $data;
                        break;
                    }
                }
            }

            $this->permissionsData = $newMembersData;

            $world = $this->getWorld();
            if ($world === null) {
                return;
            }

            foreach ($world->getPlayers() as $player) {
                if ($this->hasPermission($player->getXuid(), self::PERMISSION_BUILD)) {
                    AdventureSettingsObject::getInstance()->setBuildingPermission($player, true);
                }
            }
        }, catches: Database::getFailClosure(true));
    }

    public function onChunkLoad(World $world, int $chunkX, int $chunkZ): void
    {
        if (isset($this->loadedChunks[$chunkHash = World::chunkHash($chunkX, $chunkZ)])) {
            return;
        }

        foreach ($world->getChunkEntities($chunkX, $chunkZ) as $entity) {
            if ($entity instanceof StackableInterface || $entity instanceof ExperienceOrb) {
                $entity->flagForDespawn();
            }
        }

        $this->loadedChunks[$chunkHash] = true;
    }

    /**
     * @param bool $autoUnload Enable the auto-unload system.
     */
    public function setUnloadLock(bool $autoUnload): void
    {
        $this->autoUnload = $autoUnload;
    }

    /**
     * @param bool $enablePvP Enables the pvp system in the island.
     */
    public function setPvPEnabled(bool $enablePvP): void
    {
        $this->pvpEnabled = $enablePvP;
    }

    /**
     * @param bool $islandPublic Allows other players to teleport into this island publicly.
     */
    public function setIslandPublic(bool $islandPublic): void
    {
        $this->islandPublic = $islandPublic;

        Database::executeChange(Database::SET_ISLAND_PUBLIC, [
            'xuid' => $this->getOwnerXuid(),
            'public' => $islandPublic ? 1 : 0
        ]);
    }

    /**
     * @param World|null $world
     * @internal
     */
    public function setWorld(?World $world): void
    {
        $this->updateBorderTask($world);
        $this->world = $world;
    }

    /**
     * @param Location $location The spawn location that need to be set.
     */
    public function setSpawnPosition(Location $location): void
    {
        $rotation = round($location->getYaw() / 45) * 45;

        if (is_nan($rotation) || is_infinite($rotation)) {
            $rotation = 0;
        }

        $this->spawnVector = Location::fromObject($location->floor(), null, $rotation);
    }

    /**
     * Set's players permission for an action
     *
     * @param string $playerXuid
     * @param int $permissionId
     * @param bool $bool
     */
    public function setPermission(string $playerXuid, int $permissionId, bool $bool): void
    {
        if ($bool) {
            $this->permissionsData[$playerXuid][$permissionId] = true;
        } else if (isset($this->permissionsData[$playerXuid])) {
            unset($this->permissionsData[$playerXuid][$permissionId]);

            if (count($this->permissionsData[$playerXuid]) === 0) {
                unset($this->permissionsData[$playerXuid]);
            }
        }
    }

    /**
     * Level up the island world.
     */
    public function levelUp(): void
    {
        $nextLevel = $this->xpLevelSpec->getNextLevel();

        if ($nextLevel !== null) {
            $this->xpLevelSpec = $nextLevel;
        }
    }


    /**
     * Spawn an NPC in the island.
     */
    public function spawnNPC(): void
    {
        [$x, $y, $z] = self::SKY_BLOCK_DATA[$this->getType()][self::MAP_NPC_SPAWN];

        $entity = new IslandNPC(Location::fromObject(new Vector3($x, $y, $z), $this->getWorld()));
        $entity->setNameTag(TextFormat::BOLD . TextFormat::YELLOW . 'Island Settings' . TextFormat::EOL . TextFormat::RESET . TextFormat::GREEN . 'Tap me!');
        $entity->setNameTagAlwaysVisible(true);
        $entity->spawnToAll();

        $rot = $entity->calcYawAndPitch();
        $entity->setRotation($rot->yaw, $rot->pitch);
    }

    /**
     * @return int The island type, you can read the above constants to see more details about this.
     */
    public function getType(): int
    {
        return $this->islandType;
    }

    /**
     * @return int The SkyBlock xp level, this is determined by how bosses the player fight.
     */
    public function getXpLevel(): int
    {
        return $this->xpLevelSpec->getId();
    }

    /**
     * @return string The owner of this island.
     */
    public function getOwner(): string
    {
        return $this->owner;
    }

    /**
     * @return string The owner's xbox xuid.
     */
    public function getOwnerXuid(): string
    {
        return $this->ownerXuid;
    }

    /**
     * @return World|null The island world, this will return null if it hasn't been loaded.
     */
    public function getWorld(): ?World
    {
        return $this->world;
    }

    public function getSpawnPosition(): Position
    {
        /** @var World $world */
        $world = $this->getWorld();

        if ($this->spawnVector !== null) {
            $spawn = $this->spawnVector;

            return Location::fromObject($spawn->add(0.5, 0, 0.5), $world, $spawn->getYaw());
        }

        return $world->getSpawnLocation();
    }

    /**
     * @return bool {@code true} If the island can be unloaded if there is no players inside.
     */
    public function isUnloadUnlocked(): bool
    {
        return $this->autoUnload;
    }

    /**
     * @return bool {@code true} If the player is directly related to this island.
     */
    public function isMember(Player $player): bool
    {
        return $player->getName() === $this->getOwner() || NGEssentials::getInstance()->getPlayerManager()->getSocialManager()->getFriendsManager()->isFriend($player, $this->getOwner());
    }

    /**
     * @return bool {@code true} If the island has pvp enabled.
     */
    public function isPvPEnabled(): bool
    {
        return $this->pvpEnabled;
    }

    /**
     * @return bool {@code true} If the island is open for public teleportation.
     */
    public function isIslandPublic(): bool
    {
        return $this->islandPublic;
    }

    /**
     * @return bool {@code true} If the island has block expansion limit.
     */
    public function hasBlockExpansion(): bool
    {
        return $this->hasVip;
    }

    /**
     * @param Player|string $playerXuid The player xuid, this will remain constant object.
     * @param int $permissionId The permission id itself, you can read more on the PERMISSIONS constants above.
     * @return bool {@code true} If the player has the specified permission in this server.
     */
    public function hasPermission(Player|string $playerXuid, int $permissionId): bool
    {
        /** @var Player|null $player */
        $player = null;
        if ($playerXuid instanceof Player) {
            $player = $playerXuid;
            $playerXuid = $playerXuid->getXuid();
        }

        return $playerXuid === $this->getOwnerXuid() || ($this->permissionsData[$playerXuid][$permissionId] ?? false) || ($player !== null && $player->hasPermission(Permissions::RANK_ADMIN));
    }

    /**
     * Removes mini-helper from this island.
     *
     * @param Player $player The player that tries to remove the helpers.
     * @param MiniHelper $helper The mini-helper that need to be removed
     * @return bool {@code true} if the operation was successful.
     */
    public function removeHelper(Player $player, MiniHelper $helper): bool
    {
        if ($player->getName() === $this->getOwner()) {
            if (isset($this->helpers[$helper->getJobType()])) {
                $this->helpers[$helper->getJobType()]--;

                if ($this->helpers[$helper->getJobType()] <= 0) {
                    unset($this->helpers[$helper->getJobType()]);
                }
            }

            return true;
        }

        $player->sendMessage(TextFormat::RED . 'You do not have permission to remove helpers on this island.');
        return false;
    }

    /**
     * Add a mini-helper into this island.
     *
     * @param Player $player The player that tries to add the mini-helper.
     * @param int $jobType The mini-helper that need to be added.
     * @return bool {@code true} if the operation was successful.
     */
    public function addHelper(Player $player, int $jobType): bool
    {
        if ($player->getName() === $this->getOwner()) {
            if (isset($this->helpers[$jobType])) {
                if ($this->helpers[$jobType] >= 3) {
                    $player->sendMessage(TextFormat::RED . 'You have reached the limit of 3 helpers with this job.');
                    return false;
                }

                $this->helpers[$jobType]++;
            } else {
                $this->helpers[$jobType] = 1;
            }

            return true;
        }

        $player->sendMessage(TextFormat::RED . 'You do not have permission to add helpers on this island.');
        return false;
    }

    /**
     * @return BlockCounter
     */
    public function getBlockCounter(): BlockCounter
    {
        return $this->blockCounter;
    }

    /**
     * @return IslandLevelSpec
     */
    public function getXpLevelSpec(): IslandLevelSpec
    {
        return $this->xpLevelSpec;
    }

    /**
     * @return bool[][] The array of player's permissions. as [ player -> [ id -> true|false ] ]
     */
    public function getPermissionsData(): array
    {
        return $this->permissionsData;
    }

    /**
     * @return int[] The mini-helpers count, the array is identified by its job
     */
    public function getHelpers(): array
    {
        return $this->helpers;
    }

    /**
     * @return Player[] The players online in the world, includes the owner.
     */
    public function getOnlineMembers(): array
    {
        return array_filter($this->getWorld()->getPlayers(), fn(Player $player) => $this->isMember($player));
    }

    /**
     * @return bool[][] Permission data for the selected player xuid.
     */
    public function getMembersData(): array
    {
        return $this->permissionsData;
    }

    /**
     * @return array The SkyBlock's data in a batch.
     */
    public function getData(): array
    {
        $data = [];

        $data[self::DATA_TYPE] = $this->getType();
        if ($this->hasBlockExpansion()) {
            $data[self::DATA_VIP] = true;
        }
        $data[self::DATA_OWNER] = $this->getOwner();
        if ($this->getXpLevel() > 1) {
            $data[self::DATA_LEVEL] = $this->getXpLevel();
        }
        $data[self::DATA_MEMBERS_DATA] = $this->getPermissionsData();
        if ($this->isPvPEnabled()) {
            $data[self::DATA_PVP] = $this->isPvPEnabled();
        }
        if (count($helpers = $this->getHelpers()) > 0) {
            $data[self::DATA_HELPERS] = $helpers;
        }
        if ($this->spawnVector !== null) {
            $spawn = $this->spawnVector->floor();
            $rotation = $this->spawnVector->getYaw();

            if (is_nan($rotation) || is_infinite($rotation)) {
                $rotation = 0;
            }

            $data[self::DATA_SPAWN] = [World::blockHash($spawn->getX(), $spawn->getY(), $spawn->getZ()), $this->spawnVector->getYaw(), $rotation];
        }

        return $data;
    }

    /**
     * @return bool
     */
    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    /**
     * @param bool $isLocked
     */
    public function setLocked(bool $isLocked): void
    {
        $this->isLocked = $isLocked;
    }

    public function setExtraData(array $extraData): void
    {
        if (count($extraData) < 5) {
            return;
        }

        [$wDurVL, $wLoreVL, $wMaxLore, $wCurseVL, $knownTiles] = $extraData;

        $this->getBlockCounter()->setData($knownTiles);

        // TODO: Pouches detection, would be nicer yes?
        if ($wDurVL > 1 || $wLoreVL > 2 || $wCurseVL > 8) {
            SkyBlock::getInstance()->getLoggerStream()->add("(Global) " . $this->getOwner() . '-' . $this->getOwnerXuid() . ": durVL=$wDurVL, loreVL=$wLoreVL, maxLore=$wMaxLore, curseVL=$wCurseVL");
        }
    }
}