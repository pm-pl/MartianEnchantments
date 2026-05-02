<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\utils;

use ecstsy\MartianEnchantments\enchantments\Groups;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\PlayerUtils;
use ecstsy\MartianEnchantments\Loader;
use ecstsy\MartianEnchantments\server\items\MartianEnchantItemFactory;
use ecstsy\MartianEnchantments\utils\ItemApplyHelper;
use pocketmine\item\VanillaItems;
use pocketmine\Server;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

use pocketmine\scheduler\ClosureTask;

/**
 * One active Soul Gem channel per player: pooled souls drawn by USE_SOUL (optional rarity match — see settings.soulsgem).
 */
final class SoulGemSessionManager {

    /** @var array<string, array{group: string, pool: int, slot: int, particle: string}> */
    private static array $sessions = [];

    private static ?string $defaultParticleCache = null;

    /** @var bool|null loaded from settings.soulsgem.require-matching-book-group */
    private static ?bool $requireMatchingBookGroupCache = null;

    /** @var bool|null cached from settings.souls.enabled */
    private static ?bool $soulsEnabledCache = null;

    public static function isChanneling(Player $player): bool {
        return isset(self::$sessions[$player->getUniqueId()->toString()]);
    }

    /** @internal */
    public static function particleForGem(): string {
        if (self::$defaultParticleCache !== null) {
            return self::$defaultParticleCache;
        }
        $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");
        self::$defaultParticleCache = (string) ($cfg?->getNested("settings.soulsgem.activate-particle", "minecraft:enchanting_table_particle") ?? "minecraft:enchanting_table_particle");

        return self::$defaultParticleCache;
    }

    /**
     * When config reloads; next channel open re-reads particle id and booleans.
     */
    public static function clearCachedParticleResolver(): void {
        self::$defaultParticleCache = null;
        self::$requireMatchingBookGroupCache = null;
        self::$soulsEnabledCache = null;
    }

    /** After config reload: close active gem channels when souls were turned off server-wide. */
    public static function deactivateOnlineChannelsWhenSoulsDisabled(): void {
        self::$soulsEnabledCache = null;
        if (self::soulsGloballyEnabled()) {
            return;
        }
        foreach (Server::getInstance()->getOnlinePlayers() as $online) {
            if (isset(self::$sessions[$online->getUniqueId()->toString()])) {
                self::deactivate($online, true);
            }
        }
    }

    private static function soulsGloballyEnabled(): bool {
        if (self::$soulsEnabledCache !== null) {
            return self::$soulsEnabledCache;
        }
        $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");
        self::$soulsEnabledCache = (bool) ($cfg?->getNested("settings.souls.enabled", true) ?? true);

        return self::$soulsEnabledCache;
    }

    /**
     * When true, gems only pay for USE_SOUL on enchants whose book rarity matches the gem’s stored group.
     * When false (default), any USE_SOUL effect can draw from any gem — cost is purely per YAML amount + pool balance.
     */
    private static function requireMatchingBookGroup(): bool {
        if (self::$requireMatchingBookGroupCache !== null) {
            return self::$requireMatchingBookGroupCache;
        }
        $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");
        self::$requireMatchingBookGroupCache = (bool) ($cfg?->getNested("settings.soulsgem.require-matching-book-group", false) ?? false);

        return self::$requireMatchingBookGroupCache;
    }

    private static function messageFor(string $nestedKey, string $fallbackEnglish): string {
        $lm = Loader::getInstance()->getLanguageManager();
        $v = $lm->getNested($nestedKey, $fallbackEnglish);
        if (!\is_string($v) || $v === '') {
            return $fallbackEnglish;
        }
        if (\str_starts_with($v, 'Translation not found:')) {
            return $fallbackEnglish;
        }

        return $v;
    }

    public static function deactivate(Player $player, bool $silent = false): void {
        $id = $player->getUniqueId()->toString();
        $sess = self::$sessions[$id] ?? null;
        unset(self::$sessions[$id]);
        if ($sess !== null) {
            self::writePoolToSlot($player, (int) $sess["slot"], (string) $sess["group"], (int) $sess["pool"]);
        }
        if (!$silent) {
            $player->sendMessage(C::colorize(self::messageFor("items.soulsgem.toggle-off", "&7Soul channel &fdeactivated&7.")));
            PlayerUtils::playSound($player, "random.click");
        }
    }

    /**
     * @return bool true if channel started, false if invalid / blocked
     */
    public static function tryToggleChannel(Player $player): bool {
        if (!self::soulsGloballyEnabled()) {
            PlayerUtils::playSound($player, "note.bass");
            self::sendGemLine($player, "items.soul-tracker.disabled", "&cSouls are not enabled on this server.");

            return false;
        }

        $id = $player->getUniqueId()->toString();
        $held = $player->getInventory()->getItemInHand();
        $slot = $player->getInventory()->getHeldItemIndex();

        if (isset(self::$sessions[$id])) {
            $sess = self::$sessions[$id];
            if ((int) $sess["slot"] === $slot && ItemApplyHelper::martianId($held) === "soulgem") {
                self::deactivate($player, false);

                return true;
            }
            PlayerUtils::playSound($player, "note.bass");
            self::sendGemLine($player, "items.soulsgem.already-other", "&cAnother Soul Gem slot is channeling — turn it off first.");

            return false;
        }

        if ($held->isNull() || ItemApplyHelper::martianId($held) !== "soulgem") {
            PlayerUtils::playSound($player, "note.bass");
            self::sendGemLine($player, "items.soulsgem.hold-gem", "&cHold one Soul Gem in your main hand.");

            return false;
        }

        if ($held->getCount() !== 1) {
            PlayerUtils::playSound($player, "note.bass");
            self::sendGemLine($player, "items.soulsgem.single-stack", "&cSplit to one gem (single stack) to open a channel.");

            return false;
        }

        $tag = $held->getNamedTag()->getCompoundTag("MartianEnchantments");
        if ($tag === null) {
            PlayerUtils::playSound($player, "note.bass");
            self::sendGemLine($player, "items.soulsgem.invalid-gem", "&cThis item is missing Soul Gem data (regive with &f/me giveitem&c).");

            return false;
        }

        $pool = (int) $tag->getInt("count", 1);
        if ($pool < 1) {
            PlayerUtils::playSound($player, "note.bass");
            self::sendGemLine($player, "items.soulsgem.empty", "&cThat Soul Gem has no souls left.");

            return false;
        }

        $group = strtoupper(trim($tag->getString("group", "")));
        if ($group === "") {
            $group = strtoupper(Groups::getFallbackGroup());
        }

        $particleId = self::particleForGem();
        self::$sessions[$id] = [
            "group" => $group,
            "pool" => $pool,
            "slot" => $slot,
            "particle" => $particleId,
        ];

        SoulGemParticleEffect::channelOpenBurst($player, $particleId);
        PlayerUtils::playSound($player, "random.orb", 1, 1, 16);
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(static function () use ($player): void {
            if ($player->isOnline()) {
                PlayerUtils::playSound($player, "block.enchanting_table.use", 0.9, 1.08, 20);
            }
        }), 10);

        $onTpl = self::messageFor(
            "items.soulsgem.toggle-on",
            "&aSoul channel active. &f{souls} &7souls flowing from your gem."
        );
        $player->sendMessage(C::colorize(\str_replace("{souls}", (string) $pool, $onTpl)));

        return true;
    }

    /**
     * For USE_SOUL: returns false if no channel, wrong gem group, or not enough souls.
     */
    public static function tryConsumeSouls(Player $player, int $amount, string $enchantGroupUpper): bool {
        if (!self::soulsGloballyEnabled()) {
            return false;
        }

        $id = $player->getUniqueId()->toString();
        $sess = self::$sessions[$id] ?? null;
        if ($sess === null) {
            return false;
        }

        $g = (string) $sess["group"];
        if (self::requireMatchingBookGroup()
            && $g !== ""
            && $enchantGroupUpper !== ""
            && $g !== $enchantGroupUpper) {
            return false;
        }

        $pool = (int) $sess["pool"];
        if ($pool < $amount) {
            return false;
        }

        $sess["pool"] = $pool - $amount;
        self::$sessions[$id] = $sess;
        self::writePoolToSlot($player, (int) $sess["slot"], $g, (int) $sess["pool"]);

        return true;
    }

    public static function clearOnQuit(Player $player): void {
        $id = $player->getUniqueId()->toString();
        if (!isset(self::$sessions[$id])) {
            return;
        }
        self::deactivate($player, true);
    }

    private static function writePoolToSlot(Player $player, int $slot, string $group, int $pool): void {
        $inv = $player->getInventory();
        $it = $inv->getItem($slot);
        if ($it->isNull() || ItemApplyHelper::martianId($it) !== "soulgem") {
            return;
        }

        $root = $it->getNamedTag();
        $m = $root->getCompoundTag("MartianEnchantments") ?? new CompoundTag();
        $m->setInt("count", max(0, $pool));
        if ($group !== "") {
            $m->setString("group", $group);
        }
        $root->setTag("MartianEnchantments", $m);
        $it->setNamedTag($root);

        if ($pool < 1) {
            $inv->setItem($slot, VanillaItems::AIR());
            unset(self::$sessions[$player->getUniqueId()->toString()]);

            return;
        }

        MartianEnchantItemFactory::refreshNameAndLore(
            "soulgem",
            $it,
            [
                "count" => max(1, $pool),
                "group" => trim($group) !== '' ? trim($group) : strtoupper(trim($m->getString("group", ""))),
            ]
        );

        $inv->setItem($slot, $it);
    }

    private static function sendGemLine(Player $player, string $langKey, string $fallbackEnglish): void {
        $player->sendMessage(C::colorize(self::messageFor($langKey, $fallbackEnglish)));
    }
}
