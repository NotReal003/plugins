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

namespace libMMO\player\enchantment;

use libMMO\item\enchantment\AccelerateEnchantment;
use libMMO\item\enchantment\BoomEnchantment;
use libMMO\item\enchantment\chances\DebilitateEnchantment;
use libMMO\item\enchantment\chances\DetonationEnchantment;
use libMMO\item\enchantment\chances\DizzyEnchantment;
use libMMO\item\enchantment\chances\EntanglementEnchantment;
use libMMO\item\enchantment\chances\EvasionEnchantment;
use libMMO\item\enchantment\chances\FamineEnchantment;
use libMMO\item\enchantment\chances\GrappleEnchantment;
use libMMO\item\enchantment\chances\KarmaEnchantment;
use libMMO\item\enchantment\chances\MoltenEnchantment;
use libMMO\item\enchantment\chances\PilferEnchantment;
use libMMO\item\enchantment\chances\PoisonEnchantment;
use libMMO\item\enchantment\chances\SwipeEnchantment;
use libMMO\item\enchantment\chances\ThorEnchantment;
use libMMO\item\enchantment\chances\VampireEnchantment;
use libMMO\item\enchantment\ComboEnchantment;
use libMMO\item\enchantment\DecayEnchantment;
use libMMO\item\enchantment\DetectEnchantment;
use libMMO\item\enchantment\DrunkEnchantment;
use libMMO\item\enchantment\Enchantment;
use libMMO\item\enchantment\EnduranceEnchantment;
use libMMO\item\enchantment\EscapeEnchantment;
use libMMO\item\enchantment\FrostyArrowsEnchantment;
use libMMO\item\enchantment\GlowEnchantment;
use libMMO\item\enchantment\GuardianAngelEnchantment;
use libMMO\item\enchantment\ImmunityEnchantment;
use libMMO\item\enchantment\KillAuraEnchantment;
use libMMO\item\enchantment\LethalPrecisionEnchantment;
use libMMO\item\enchantment\LifestealEnchantment;
use libMMO\item\enchantment\MermaidEnchantment;
use libMMO\item\enchantment\MinerEnchantment;
use libMMO\item\enchantment\RabbitEnchantment;
use libMMO\item\enchantment\ReplenishEnchantment;
use libMMO\item\enchantment\SpringEnchantment;
use libMMO\item\enchantment\TankEnchantment;
use libMMO\item\enchantment\TripleShotEnchantment;
use libMMO\item\enchantment\VelocityEnchantment;
use libMMO\MMOPlugin;
use libMMO\utils\BaseClass;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\item\enchantment\AvailableEnchantmentRegistry;
use pocketmine\item\enchantment\Enchantment as PMEnchantment;
use pocketmine\item\enchantment\ItemEnchantmentTags as Tags;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\RegistryTrait;

/**
 * This doc-block is generated automatically, do not modify it manually.
 * This must be regenerated whenever registry members are added, removed or changed.
 * @see \pocketmine\utils\RegistryUtils::_generateMethodAnnotations()
 *
 * @method static GlowEnchantment GLOW()
 * @method static MinerEnchantment MINER()
 * @method static BoomEnchantment BOOM()
 * @method static AccelerateEnchantment ACCELERATE()
 * @method static RabbitEnchantment RABBIT()
 * @method static PoisonEnchantment POISON()
 * @method static ThorEnchantment THOR()
 * @method static GuardianAngelEnchantment GUARDIAN_ANGEL()
 * @method static TripleShotEnchantment TRIPLE_SHOT()
 * @method static GrappleEnchantment GRAPPLE()
 * @method static TankEnchantment TANK()
 * @method static EvasionEnchantment EVASION()
 * @method static ImmunityEnchantment IMMUNITY()
 * @method static KarmaEnchantment KARMA()
 * @method static DecayEnchantment DECAY()
 * @method static DebilitateEnchantment DEBILITATE()
 * @method static PilferEnchantment PILFER()
 * @method static VelocityEnchantment VELOCITY()
 * @method static DetonationEnchantment DETONATION()
 * @method static ReplenishEnchantment REPLENISH()
 * @method static EscapeEnchantment ESCAPE()
 * @method static DetectEnchantment DETECT()
 * @method static DizzyEnchantment DIZZY()
 * @method static VampireEnchantment VAMPIRE()
 * @method static MermaidEnchantment MERMAID()
 * @method static SpringEnchantment SPRING()
 * @method static MoltenEnchantment MOLTEN()
 * @method static SwipeEnchantment SWIPE()
 * @method static EnduranceEnchantment ENDURANCE()
 * @method static FamineEnchantment FAMINE()
 * @method static EntanglementEnchantment ENTANGLEMENT()
 * @method static DrunkEnchantment DRUNK()
 * @method static FrostyArrowsEnchantment FROSTY_ARROWS()
 * @method static ComboEnchantment COMBO()
 * @method static KillAuraEnchantment KILL_AURA()
 * @method static LifestealEnchantment LIFESTEAL()
 * @method static LethalPrecisionEnchantment LETHAL_PRECISION()
 */
final class EnchantmentManager extends BaseClass
{
    use RegistryTrait;

    public const TANK = 50;
    public const EVASION = 51;
    public const IMMUNITY = 53;
    public const KARMA = 54;
    public const DECAY = 56;
    public const DEBILITATE = 57;
    public const COMBO = 58;
    public const GUILLOTINE = 59;
    public const PILFER = 60;
    public const VELOCITY = 61;
    public const DETONATION = 62;
    public const REPLENISH = 63;
    public const ESCAPE = 64;
    public const DETECT = 65;
    public const DIZZY = 66;
    public const VAMPIRE = 70;
    public const MERMAID = 73;
    public const SPRING = 74;
    public const MOLTEN = 75;
    public const SWIPE = 76;
    public const ENDURANCE = 77;
    public const FAMINE = 78;
    public const ENTANGLEMENT = 79;
    public const DRUNK = 80;
    public const FROSTY_ARROWS = 81;
    public const KILL_AURA = 82;
    public const LIFESTEAL = 83;
    public const LETHAL_PRECISION = 84;

    public const GLOW = 100;
    public const MINER = 101;
    public const ACCELERATE = 102;
    public const RABBIT = 103;
    public const POISON = 104;
    public const THOR = 105;
    public const BOOM = 106;
    public const GUARDIAN_ANGEL = 107;
    public const TRIPLE_SHOT = 108;
    public const GRAPPLE = 109;

    /** @var int[][] */
    private static array $cooldowns = [];
    /** @var Item[] */
    private static array $excludedReverse = [];
    /** @var true[] */
    private static array $excludedEnchants = [];
    /** @var int[] */
    private static array $vanillaMaxLevel = [];

    public function __construct(MMOPlugin $instance, bool $registerListener = true)
    {
        parent::__construct($instance);

        self::checkInit();
        self::addItemExclusion(VanillaItems::BOOK(), VanillaItems::ENCHANTED_BOOK());

        if ($registerListener) {
            $instance->getServer()->getPluginManager()->registerEvents(new EnchantListener($instance), $instance);
        }
    }

    /**
     * @return Enchantment[]
     */
    public static function getAll(): array
    {
        return self::_registryGetAll();
    }

    public static function getMaxEnchantmentLevel(PMEnchantment $enchantment): int
    {
        if (isset(self::$vanillaMaxLevel[$hash = spl_object_hash($enchantment)])) {
            return self::$vanillaMaxLevel[$hash];
        }

        return $enchantment->getMaxLevel();
    }

    public static function setEnchantmentLevel(array $data): void
    {
        self::$vanillaMaxLevel = $data;
    }

    public static function isItemExcluded(Item $item): bool
    {
        foreach (self::$excludedReverse as $exclusion) {
            if ($exclusion->getTypeId() === $item->getTypeId()) {
                return true;
            }
        }

        return false;
    }

    public static function addItemExclusion(Item ...$items): void
    {
        foreach ($items as $item) {
            self::$excludedReverse[] = $item;
        }
    }

    public static function isEnchantExcluded(PMEnchantment $enchantment): bool
    {
        return isset(self::$excludedEnchants[spl_object_hash($enchantment)]);
    }

    public static function addEnchantExclusion(PMEnchantment ...$enchantments): void
    {
        foreach ($enchantments as $enchantment) {
            self::$excludedEnchants[spl_object_hash($enchantment)] = true;
        }
    }

    public static function getCooldown(Player $player, PMEnchantment $ench): int
    {
        $highResolution = hrtime(true);
        if (isset(self::$cooldowns[$player->getName()][spl_object_hash($ench)])) {
            /** @phpstan-ignore-next-line */
            [$cooldown, $timer] = self::$cooldowns[$player->getName()][spl_object_hash($ench)];

            return (int)(($cooldown / 20) - round(($highResolution - $timer) / 1e+9));
        }

        return -1;
    }

    public static function addCooldown(Player $player, PMEnchantment $ench, int $ticks): void
    {
        self::$cooldowns[$player->getName()][spl_object_hash($ench)] = [$ticks, hrtime(true)];
    }

    public static function hasCooldown(Player $player, PMEnchantment $enchantment): bool
    {
        return isset(self::$cooldowns[$player->getName()][spl_object_hash($enchantment)]) && self::getCooldown($player, $enchantment) > 0;
    }

    protected static function setup(): void
    {
        $enchIdMap = EnchantmentIdMap::getInstance();
        $enchIdMap->register(self::GLOW, $glow = new GlowEnchantment());
        $enchIdMap->register(self::MINER, $miner = new MinerEnchantment());
        $enchIdMap->register(self::BOOM, $boom = new BoomEnchantment());
        $enchIdMap->register(self::ACCELERATE, $accelerate = new AccelerateEnchantment());
        $enchIdMap->register(self::RABBIT, $rabbit = new RabbitEnchantment());
        $enchIdMap->register(self::POISON, $poison = new PoisonEnchantment());
        $enchIdMap->register(self::THOR, $thor = new ThorEnchantment());
        $enchIdMap->register(self::GUARDIAN_ANGEL, $guardianAngel = new GuardianAngelEnchantment());
        $enchIdMap->register(self::TRIPLE_SHOT, $tripleShot = new TripleShotEnchantment());
        $enchIdMap->register(self::GRAPPLE, $grapple = new GrappleEnchantment());

        // Factions Enchantment
        $enchIdMap->register(self::TANK, $tank = new TankEnchantment());
        $enchIdMap->register(self::EVASION, $evasion = new EvasionEnchantment());
        $enchIdMap->register(self::IMMUNITY, $immunity = new ImmunityEnchantment());
        $enchIdMap->register(self::KARMA, $karma = new KarmaEnchantment());
        $enchIdMap->register(self::DECAY, $decay = new DecayEnchantment());
        $enchIdMap->register(self::DEBILITATE, $debilitate = new DebilitateEnchantment());
        $enchIdMap->register(self::PILFER, $pilfer = new PilferEnchantment());
        $enchIdMap->register(self::VELOCITY, $velocity = new VelocityEnchantment());
        $enchIdMap->register(self::DETONATION, $detonation = new DetonationEnchantment());
        $enchIdMap->register(self::REPLENISH, $replenish = new ReplenishEnchantment());
        $enchIdMap->register(self::ESCAPE, $escape = new EscapeEnchantment());
        $enchIdMap->register(self::DETECT, $detect = new DetectEnchantment());
        $enchIdMap->register(self::DIZZY, $dizzy = new DizzyEnchantment());
        $enchIdMap->register(self::VAMPIRE, $vampire = new VampireEnchantment());
        $enchIdMap->register(self::MERMAID, $mermaid = new MermaidEnchantment());
        $enchIdMap->register(self::SPRING, $spring = new SpringEnchantment());
        $enchIdMap->register(self::MOLTEN, $molten = new MoltenEnchantment());
        $enchIdMap->register(self::SWIPE, $swipe = new SwipeEnchantment());
        $enchIdMap->register(self::ENDURANCE, $endurance = new EnduranceEnchantment());
        $enchIdMap->register(self::FAMINE, $famine = new FamineEnchantment());
        $enchIdMap->register(self::ENTANGLEMENT, $entanglement = new EntanglementEnchantment());
        $enchIdMap->register(self::DRUNK, $drunk = new DrunkEnchantment());
        $enchIdMap->register(self::FROSTY_ARROWS, $frostyArrows = new FrostyArrowsEnchantment());
        $enchIdMap->register(self::COMBO, $combo = new ComboEnchantment());

        // SkyBlock enchantments
        $enchIdMap->register(self::KILL_AURA, $killAura = new KillAuraEnchantment());
        $enchIdMap->register(self::LIFESTEAL, $lifesteal = new LifestealEnchantment());
        $enchIdMap->register(self::LETHAL_PRECISION, $lethalPrecision = new LethalPrecisionEnchantment());

        $registry = AvailableEnchantmentRegistry::getInstance();
        $registry->register($glow, [Tags::HELMET], []);
        $registry->register($miner, [Tags::PICKAXE], []);
        $registry->register($boom, [Tags::BLOCK_TOOLS], []);
        $registry->register($accelerate, [Tags::BOOTS], []);
        $registry->register($rabbit, [Tags::BOOTS], []);
        $registry->register($poison, [Tags::SWORD], []);
        $registry->register($thor, [Tags::SWORD], []);
        $registry->register($guardianAngel, [Tags::ARMOR], []);
        $registry->register($tripleShot, [Tags::BOW], []);
        $registry->register($grapple, [Tags::BOW], []);

        // Factions enchantments
        $registry->register($tank, [Tags::ARMOR], []);
        $registry->register($evasion, [Tags::ARMOR], []);
        $registry->register($immunity, [Tags::ARMOR], []);
        $registry->register($karma, [Tags::ARMOR], []);
        $registry->register($decay, [Tags::SWORD], []);
        $registry->register($debilitate, [Tags::SWORD], []);
        $registry->register($pilfer, [Tags::SWORD], []);
        $registry->register($velocity, [Tags::BOW], []);
        $registry->register($detonation, [Tags::BOW], []);
        $registry->register($replenish, [Tags::SWORD], []);
        $registry->register($escape, [Tags::SWORD], []);
        $registry->register($detect, [Tags::BLOCK_TOOLS], []);
        $registry->register($dizzy, [Tags::SWORD], []);
        $registry->register($vampire, [Tags::SWORD], []);
        $registry->register($mermaid, [Tags::HELMET], []);
        $registry->register($spring, [Tags::BOOTS], []);
        $registry->register($molten, [Tags::SWORD], []);
        $registry->register($swipe, [Tags::SWORD], []);
        $registry->register($endurance, [Tags::ARMOR], []);
        $registry->register($famine, [Tags::SWORD], []);
        $registry->register($entanglement, [Tags::BOW], []);
        $registry->register($drunk, [Tags::HELMET], []);
        $registry->register($frostyArrows, [Tags::BOW], []);
        $registry->register($combo, [Tags::SWORD], []);

        // SkyBlock enchantments
        $registry->register($killAura, [Tags::SWORD, Tags::BOW], []);
        $registry->register($lifesteal, [Tags::SWORD], []);
        $registry->register($lethalPrecision, [Tags::BOW], []);

        self::register("glow", $glow);
        self::register("miner", $miner);
        self::register("boom", $boom);
        self::register("accelerate", $accelerate);
        self::register("rabbit", $rabbit);
        self::register("poison", $poison);
        self::register("thor", $thor);
        self::register("guardian_angel", $guardianAngel);
        self::register("triple_shot", $tripleShot);
        self::register("grapple", $grapple);
        self::register("tank", $tank);
        self::register("evasion", $evasion);
        self::register("immunity", $immunity);
        self::register("karma", $karma);
        self::register("decay", $decay);
        self::register("debilitate", $debilitate);
        self::register("pilfer", $pilfer);
        self::register("velocity", $velocity);
        self::register("detonation", $detonation);
        self::register("replenish", $replenish);
        self::register("escape", $escape);
        self::register("detect", $detect);
        self::register("dizzy", $dizzy);
        self::register("vampire", $vampire);
        self::register("mermaid", $mermaid);
        self::register("spring", $spring);
        self::register("molten", $molten);
        self::register("swipe", $swipe);
        self::register("endurance", $endurance);
        self::register("famine", $famine);
        self::register("entanglement", $entanglement);
        self::register("drunk", $drunk);
        self::register("frosty_arrows", $frostyArrows);
        self::register("combo", $combo);
        self::register("kill_aura", $killAura);
        self::register("lifesteal", $lifesteal);
        self::register("lethal_precision", $lethalPrecision);
    }

    protected static function register(string $name, Enchantment $enchantment): void
    {
        self::_registryRegister($name, $enchantment);
    }
}