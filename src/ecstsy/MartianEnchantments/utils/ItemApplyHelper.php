<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\utils;

use ecstsy\MartianEnchantments\enchantments\CustomEnchantments;
use ecstsy\MartianEnchantments\enchantments\CustomEnchantment;
use ecstsy\MartianEnchantments\enchantments\CustomEnchantmentManager;
use ecstsy\MartianEnchantments\enchantments\Groups;
use ecstsy\MartianEnchantments\server\items\MartianEnchantmentBooks;
use ecstsy\MartianEnchantments\Loader;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\managers\LanguageManager;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Armor;
use pocketmine\item\Axe;
use pocketmine\item\Bow;
use pocketmine\item\Crossbow;
use pocketmine\item\Hoe;
use pocketmine\item\Item;
use pocketmine\item\Pickaxe;
use pocketmine\item\Shovel;
use pocketmine\item\VanillaItems;
use pocketmine\item\Sword;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as C;
use pocketmine\nbt\tag\CompoundTag;

use pocketmine\item\Trident as ItemTrident;

/**
 * Drag-and-drop: scrolls, dust, trak creators, orbs, soul items → books or gear.
 */
final class ItemApplyHelper {

    /** @return array{0: Item, 1: Item, 2: SlotChangeAction, 3: SlotChangeAction}|null */
    public static function getTwoSlotChangeItems(InventoryTransactionEvent $event): ?array {
        $actions = array_values($event->getTransaction()->getActions());
        if (count($actions) !== 2) {
            return null;
        }
        $a0 = $actions[0];
        $a1 = $actions[1];
        if (!$a0 instanceof SlotChangeAction || !$a1 instanceof SlotChangeAction) {
            return null;
        }
        if ($a0->getSourceItem()->isNull() && $a1->getSourceItem()->isNull()) {
            return null;
        }

        return [$a0->getSourceItem(), $a1->getSourceItem(), $a0, $a1];
    }

    public static function tryProcess(
        InventoryTransactionEvent $event,
        Player $player,
        array $actions,
        ?Config $config,
        LanguageManager $lang
    ): bool {
        if (!($config instanceof Config)) {
            return false;
        }
        if (!(bool) $config->getNested("settings.utility-item-drag", true)) {
            return false;
        }
        if (count($actions) !== 2) {
            return false;
        }
        $a0 = $actions[0];
        $a1 = $actions[1];
        if (!$a0 instanceof SlotChangeAction || !$a1 instanceof SlotChangeAction) {
            return false;
        }
        $i0 = $a0->getSourceItem();
        $i1 = $a1->getSourceItem();
        if ($i0->isNull() && $i1->isNull()) {
            return false;
        }
        if ($i0->isNull() || $i1->isNull()) {
            return false;
        }

        $id0 = self::martianId($i0);
        $id1 = self::martianId($i1);

        $pairs = [
            [$i0, $i1, $a0, $a1, $id0, $id1],
            [$i1, $i0, $a1, $a0, $id1, $id0],
        ];

        foreach ($pairs as $p) {
            [$scroll, $target, $scrollAction, $targetAction, $scId, $taId] = $p;
            if ($scId === null) {
                continue;
            }
            if ($scId === "enchantment-book") {
                continue;
            }

            if ($scId === "randomization-scroll" && $taId === "enchantment-book" && self::tryRandomization($player, $scroll, $target, $scrollAction, $targetAction, $config, $lang)) {
                $event->cancel();
                return true;
            }
            if ($scId === "mystery-dust" && $taId === "enchantment-book" && self::tryMysteryDust($player, $scroll, $target, $scrollAction, $targetAction, $config, $lang)) {
                $event->cancel();
                return true;
            }
            if ($scId === "magic-dust" && $taId === "enchantment-book" && self::tryMagicDust($player, $scroll, $target, $scrollAction, $targetAction, $config, $lang)) {
                $event->cancel();
                return true;
            }
        }

        foreach ($pairs as $p) {
            [$tool, $target, $toolAction, $targetAction, $tId, $tarId] = $p;
            if ($tId === null) {
                continue;
            }
            if ($tId === "enchantment-book") {
                continue;
            }
            if ($tId === "randomization-scroll" || $tId === "mystery-dust" || $tId === "magic-dust") {
                continue;
            }

            if ($tId === "white-scroll" && self::isGearTarget($target) && self::tryWhiteScroll($player, $tool, $target, $toolAction, $targetAction, $config, $lang)) {
                $event->cancel();
                return true;
            }
            if ($tId === "holywhitescroll" && self::isGearTarget($target) && self::tryHolyWhiteScroll($player, $tool, $target, $toolAction, $targetAction, $config, $lang)) {
                $event->cancel();
                return true;
            }
            if ($tId === "black-scroll" && self::hasCustomEnchants($target) && self::tryBlackScroll($player, $tool, $target, $toolAction, $targetAction, $config, $lang)) {
                $event->cancel();
                return true;
            }
            if ($tId === "transmogscroll" && self::hasCustomEnchants($target) && self::tryTransmog($player, $tool, $target, $toolAction, $targetAction, $config, $lang)) {
                $event->cancel();
                return true;
            }
            if ($tId === "slot-increaser" && self::trySlotIncreaser($player, $tool, $target, $toolAction, $targetAction, $config, $lang)) {
                $event->cancel();
                return true;
            }
            if (in_array($tId, ["stattrak", "mobtrak", "blocktrak"], true) && self::tryTrakApply($tId, $player, $tool, $target, $toolAction, $targetAction, $config, $lang)) {
                $event->cancel();
                return true;
            }
            if ($tId === "soul-tracker" && self::isWeaponLike($target) && self::trySoulTracker($player, $tool, $target, $toolAction, $targetAction, $config, $lang)) {
                $event->cancel();
                return true;
            }
            if (in_array($tId, ["weapon-orb", "armor-orb", "tool-orb"], true) && self::tryOrb($tId, $player, $tool, $target, $toolAction, $targetAction, $config, $lang)) {
                $event->cancel();
                return true;
            }
        }

        return false;
    }

    public static function martianId(Item $item): ?string {
        $tag = $item->getNamedTag()->getCompoundTag("MartianEnchantments");
        if ($tag === null) {
            return null;
        }
        if ($tag->getTag("martianItem") === null) {
            return null;
        }

        return $tag->getString("martianItem");
    }

    public static function hasCustomEnchants(Item $item): bool {
        return CustomEnchantmentManager::getEnchantments($item) !== [];
    }

    public static function hasWhiteScrollProtection(Item $item): bool {
        $t = $item->getNamedTag()->getCompoundTag("MartianEnchantments");
        return $t !== null && $t->getInt("whiteScroll", 0) > 0;
    }

    public static function clearWhiteScrollProtection(Item $item): void {
        $root = $item->getNamedTag();
        $m = $root->getCompoundTag("MartianEnchantments");
        if ($m === null) {
            return;
        }
        if ($m->getInt("whiteScroll", 0) < 1) {
            return;
        }
        $m->removeTag("whiteScroll");
        if ($m->getValue() === []) {
            $root->removeTag("MartianEnchantments");
        } else {
            $root->setTag("MartianEnchantments", $m);
        }
    }

    public static function hasHolyWhiteScrollProtection(Item $item): bool {
        $t = $item->getNamedTag()->getCompoundTag("MartianEnchantments");
        return $t !== null && $t->getInt("holyWhiteScroll", 0) > 0;
    }

    public static function clearHolyWhiteScrollProtection(Item $item): void {
        $root = $item->getNamedTag();
        $m = $root->getCompoundTag("MartianEnchantments");
        if ($m === null) {
            return;
        }
        if ($m->getInt("holyWhiteScroll", 0) < 1) {
            return;
        }
        foreach ([
            "holyWhiteScroll",
            "holyWhitePrimed",
            "holyWhiteDeathUsed",
            "holyWhiteDeathMax",
            "holyWhiteCorrupted",
        ] as $k) {
            $m->removeTag($k);
        }
        if ($m->getValue() === []) {
            $root->removeTag("MartianEnchantments");
        } else {
            $root->setTag("MartianEnchantments", $m);
        }
    }

    /**
     * Removes the Holy White Scroll protection lore line (see {@code items.holywhitescroll.settings.lore-display}) after a death save.
     */
    /**
     * Rebuilds display name from {@code transmogBaseDisplay} + enchant count suffix when enchants change.
     */
    public static function refreshTransmogDisplayName(Item $item, ?Config $config = null): bool {
        $cfg = $config ?? GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");
        if (!($cfg instanceof Config)) {
            return false;
        }

        $root = $item->getNamedTag();
        $mGear = $root->getCompoundTag("MartianEnchantments");
        if ($mGear === null) {
            return false;
        }

        $base = trim($mGear->getString("transmogBaseDisplay", ""));
        if ($base === "") {
            return false;
        }

        $count = count(CustomEnchantmentManager::getEnchantments($item));
        $fmt = (string) $cfg->getNested("items.transmogscroll.enchants-count-formatting", "&d[&b{count}&d]");
        $suffixRaw = str_replace("{count}", (string) $count, $fmt);
        $suffix = C::colorize($suffixRaw);
        $newName = $base . ($suffix !== "" ? " " . $suffix : "");

        if ($item->getCustomName() === $newName) {
            return false;
        }

        $item->setCustomName($newName);
        Utils::updateGlowEffect($item);

        return true;
    }

    public static function stripHolyWhiteScrollLoreLine(Item $item, Config $config): void {
        $tpl = trim((string) $config->getNested("items.holywhitescroll.settings.lore-display", ""));
        if ($tpl === "") {
            return;
        }
        $needle = C::clean(C::colorize($tpl));
        if ($needle === "") {
            return;
        }
        $lore = $item->getLore();
        $out = [];
        foreach ($lore as $line) {
            if (C::clean($line) === $needle) {
                continue;
            }
            $out[] = $line;
        }
        $item->setLore($out);
    }

    private static function isGearTarget(Item $item): bool {
        if ($item->isNull()) {
            return false;
        }
        $id = self::martianId($item);
        if ($id !== null) {
            if (in_array($id, self::UTILITY_MARTIAN_IDS, true)) {
                return false;
            }
        }

        return !($item->getTypeId() === VanillaItems::AIR()->getTypeId());
    }

    private const UTILITY_MARTIAN_IDS = [
        "white-scroll", "black-scroll", "transmogscroll", "randomization-scroll", "mystery-dust", "magic-dust",
        "slot-increaser", "soulgem", "itemnametag", "holywhitescroll", "stattrak", "mobtrak", "blocktrak",
        "soul-tracker", "weapon-orb", "armor-orb", "tool-orb", "enchanter-book", "secret-dust",
    ];

    private static function playOk(Player $player, Config $config): void {
        PlayerUtils::playSound($player, (string) $config->getNested("settings.applying.cosmetics.applied.sound", "random.levelup"));
    }

    private static function playFail(Player $player, Config $config): void {
        PlayerUtils::playSound($player, (string) $config->getNested("settings.applying.cosmetics.failed.sound", "random.anvil_break"));
    }

    private static function isWeaponLike(Item $item): bool {
        return $item instanceof Sword || $item instanceof Axe || $item instanceof Bow
            || $item instanceof ItemTrident;
    }

    private static function isWeaponOrbTarget(Item $item): bool {
        return self::isWeaponLike($item);
    }

    private static function isArmorOrbTarget(Item $item): bool {
        return $item instanceof Armor;
    }

    private static function isToolOrbTarget(Item $item): bool {
        if ($item instanceof Sword) {
            return false;
        }
        return $item instanceof Pickaxe || $item instanceof Axe || $item instanceof Shovel || $item instanceof Hoe;
    }

    private static function tryRandomization(
        Player $player,
        Item $scroll,
        Item $book,
        SlotChangeAction $scrollAction,
        SlotChangeAction $bookAction,
        Config $config,
        LanguageManager $lang
    ): bool {
        $bTag = $book->getNamedTag()->getCompoundTag("MartianEnchantments");
        $sTag = $scroll->getNamedTag()->getCompoundTag("MartianEnchantments");
        if ($bTag === null || $sTag === null) {
            return false;
        }
        if ($groupsYml = GeneralUtils::getConfiguration(Loader::getInstance(), "groups.yml")) {
            if ((bool) $groupsYml->getNested("settings.randomization-scrolls.limit-applying-per-group", true)) {
                $bGroup = strtoupper((string) $bTag->getString("group", ""));
                if ($bGroup === "") {
                    $en = self::bookEnchantName($bTag);
                    $eObj = $en !== null ? CustomEnchantments::getEnchantmentByName($en) : null;
                    if ($eObj !== null) {
                        $bGroup = strtoupper((string) (Groups::getGroupNameById($eObj->getRarity()) ?? Groups::getFallbackGroup()));
                    }
                }
                $sGroup = strtoupper((string) $sTag->getString("group", ""));
                if ($bGroup !== "" && $sGroup !== "" && $bGroup !== $sGroup) {
                    $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.randomization-wrong-group")));

                    return true;
                }
            }
        }
        $chances = (array) $config->getNested("settings.chances", []);
        [$s, $d] = Utils::resolveBookChances($chances, null, null);
        $bTag->setInt("success", $s);
        $bTag->setInt("destroy", $d);
        $book->getNamedTag()->setTag("MartianEnchantments", $bTag);
        self::applyBookLoreFromTag($book, $bTag, $config);
        $scrollAction->getInventory()->setItem($scrollAction->getSlot(), VanillaItems::AIR());
        $bookAction->getInventory()->setItem($bookAction->getSlot(), $book);
        $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.randomization-success")));
        self::playOk($player, $config);

        return true;
    }

    private static function bookEnchantName(CompoundTag $bTag): ?string {
        $n = $bTag->getString("enchant-no-color", "");
        if ($n === "") {
            return null;
        }
        $e = CustomEnchantments::getEnchantmentByName(strtolower($n));

        return $e?->getName();
    }

    private static function bookGroupKey(CompoundTag $bTag, CustomEnchantment $enchant): string {
        $g = $bTag->getString("group", "");
        if ($g !== "") {
            return strtoupper($g);
        }

        return strtoupper((string) (Groups::getGroupNameById($enchant->getRarity()) ?? Groups::getFallbackGroup()));
    }

    private static function tryMysteryDust(
        Player $player,
        Item $dust,
        Item $book,
        SlotChangeAction $dustAction,
        SlotChangeAction $bookAction,
        Config $config,
        LanguageManager $lang
    ): bool {
        $bTag = $book->getNamedTag()->getCompoundTag("MartianEnchantments");
        $dTag = $dust->getNamedTag()->getCompoundTag("MartianEnchantments");
        if ($bTag === null || $dTag === null) {
            return false;
        }
        $en = self::bookEnchantName($bTag);
        if ($en === null) {
            return false;
        }
        $eObj = CustomEnchantments::getEnchantmentByName($en);
        if ($eObj === null) {
            return false;
        }
        $bGroup = self::bookGroupKey($bTag, $eObj);
        $dGroup = (string) $dTag->getString("group", "");
        if ($dGroup !== "" && $bGroup !== strtoupper($dGroup)) {
            if ((bool) GeneralUtils::getConfiguration(Loader::getInstance(), "groups.yml")?->getNested("settings.magic-dust.limit-applying-per-group", true)) {
                $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.mystery-wrong-group")));

                return true;
            }
        }
        $s = (int) $bTag->getInt("success", 100);
        if ($s >= 100) {
            $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.mystery-already-max")));
            self::playFail($player, $config);

            return true;
        }
        $add = (int) $dTag->getInt("percent", 0);
        if ($add < 1) {
            $add = 1;
        }
        $bTag->setInt("success", min(100, $s + $add));
        $book->getNamedTag()->setTag("MartianEnchantments", $bTag);
        self::applyBookLoreFromTag($book, $bTag, $config);
        $dustAction->getInventory()->setItem($dustAction->getSlot(), VanillaItems::AIR());
        $bookAction->getInventory()->setItem($bookAction->getSlot(), $book);
        $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.mystery-success")));
        self::playOk($player, $config);

        return true;
    }

    private static function tryMagicDust(
        Player $player,
        Item $dust,
        Item $book,
        SlotChangeAction $dustAction,
        SlotChangeAction $bookAction,
        Config $config,
        LanguageManager $lang
    ): bool {
        $bTag = $book->getNamedTag()->getCompoundTag("MartianEnchantments");
        $dTag = $dust->getNamedTag()->getCompoundTag("MartianEnchantments");
        if ($bTag === null || $dTag === null) {
            return false;
        }
        $en = self::bookEnchantName($bTag);
        if ($en === null) {
            return false;
        }
        $eObj = CustomEnchantments::getEnchantmentByName($en);
        if ($eObj === null) {
            return false;
        }
        $bGroup = self::bookGroupKey($bTag, $eObj);
        $dGroup = (string) $dTag->getString("group", "");
        if ($dGroup !== "" && $bGroup !== strtoupper($dGroup)) {
            if ((bool) GeneralUtils::getConfiguration(Loader::getInstance(), "groups.yml")?->getNested("settings.magic-dust.limit-applying-per-group", true)) {
                $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.magic-wrong-group")));

                return true;
            }
        }
        $sub = (int) $dTag->getInt("percent", 0);
        if ($sub < 1) {
            $sub = 1;
        }
        $d = (int) $bTag->getInt("destroy", 0);
        $bTag->setInt("destroy", max(0, $d - $sub));
        $book->getNamedTag()->setTag("MartianEnchantments", $bTag);
        self::applyBookLoreFromTag($book, $bTag, $config);
        $dustAction->getInventory()->setItem($dustAction->getSlot(), VanillaItems::AIR());
        $bookAction->getInventory()->setItem($bookAction->getSlot(), $book);
        $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.magic-success")));
        self::playOk($player, $config);

        return true;
    }

    private static function applyBookLoreFromTag(Item $book, CompoundTag $bTag, Config $config): void {
        $en = self::bookEnchantName($bTag);
        if ($en === null) {
            return;
        }
        $e = CustomEnchantments::getEnchantmentByName($en);
        if ($e === null) {
            return;
        }
        $lvl = (int) $bTag->getInt("level", 1);
        $s = (int) $bTag->getInt("success", 100);
        $d = (int) $bTag->getInt("destroy", 0);
        $newB = MartianEnchantmentBooks::create($e, $lvl, $s, $d);
        if ($newB !== null) {
            $book->setCustomName($newB->getCustomName());
            $book->setLore($newB->getLore());
        }
    }

    private static function tryWhiteScroll(
        Player $player,
        Item $scroll,
        Item $target,
        SlotChangeAction $scrollAction,
        SlotChangeAction $targetAction,
        Config $config,
        LanguageManager $lang
    ): bool {
        $m = $target->getNamedTag()->getCompoundTag("MartianEnchantments") ?? new CompoundTag();
        if ($m->getInt("whiteScroll", 0) > 0) {
            $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.white-already")));
            self::playFail($player, $config);

            return true;
        }
        $m->setInt("whiteScroll", 1);
        $target->getNamedTag()->setTag("MartianEnchantments", $m);
        $lore = $config->getNested("items.white-scroll.lore-display");
        if (is_string($lore) && $lore !== "") {
            $lines = $target->getLore();
            $lines[] = C::colorize($lore);
            $target->setLore($lines);
        }
        $scrollAction->getInventory()->setItem($scrollAction->getSlot(), VanillaItems::AIR());
        $targetAction->getInventory()->setItem($targetAction->getSlot(), $target);
        $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.white-success")));
        self::playOk($player, $config);

        return true;
    }

    private static function tryHolyWhiteScroll(
        Player $player,
        Item $scroll,
        Item $target,
        SlotChangeAction $scrollAction,
        SlotChangeAction $targetAction,
        Config $config,
        LanguageManager $lang
    ): bool {
        $m = $target->getNamedTag()->getCompoundTag("MartianEnchantments") ?? new CompoundTag();

        if ($m->getInt("holyWhiteCorrupted", 0) === 1) {
            $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.holy-corrupted")));
            self::playFail($player, $config);

            return true;
        }

        if ($m->getInt("whiteScroll", 0) < 1) {
            $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.holy-need-white")));
            self::playFail($player, $config);

            return true;
        }

        if ($m->getInt("holyWhitePrimed", 0) === 1) {
            $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.holy-primed")));
            self::playFail($player, $config);

            return true;
        }

        $maxDefault = max(1, (int) $config->getNested("items.holywhitescroll.settings.max-death-saves", 3));
        $deathMax = $m->getInt("holyWhiteDeathMax", 0) < 1 ? $maxDefault : max(1, $m->getInt("holyWhiteDeathMax", $maxDefault));
        $m->setInt("holyWhiteDeathMax", $deathMax);

        $used = max(0, $m->getInt("holyWhiteDeathUsed", 0));
        if ($used >= $deathMax) {
            $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.holy-exhausted")));
            self::playFail($player, $config);

            return true;
        }

        $m->setInt("holyWhiteScroll", 1);
        $m->setInt("holyWhitePrimed", 1);

        $ld = (string) $config->getNested("items.holywhitescroll.settings.lore-display", "");
        if ($ld !== "") {
            $lines = $target->getLore();
            $lines[] = C::colorize($ld);
            $target->setLore($lines);
        }

        $target->getNamedTag()->setTag("MartianEnchantments", $m);
        $scrollAction->getInventory()->setItem($scrollAction->getSlot(), VanillaItems::AIR());
        $targetAction->getInventory()->setItem($targetAction->getSlot(), $target);
        $remaining = max(0, $deathMax - $used);
        $player->sendMessage(C::colorize(str_replace("{remaining}", (string) $remaining, (string) $lang->getNested("items.apply.holy-success"))));
        self::playOk($player, $config);

        return true;
    }

    private static function tryBlackScroll(
        Player $player,
        Item $scroll,
        Item $target,
        SlotChangeAction $scrollAction,
        SlotChangeAction $targetAction,
        Config $config,
        LanguageManager $lang
    ): bool {
        $all = CustomEnchantmentManager::getEnchantments($target);
        if ($all === []) {
            return false;
        }
        $sTag = $scroll->getNamedTag()->getCompoundTag("MartianEnchantments");
        $okSuccess = 95;
        if ($sTag !== null) {
            if ($sTag->getTag("blackscroll-success") !== null) {
                $okSuccess = (int) $sTag->getInt("blackscroll-success", 95);
            } else {
                $okSuccess = (int) $sTag->getInt("success", 95);
            }
        } else {
            $bs = $config->getNested("items.black-scroll");
            if (is_array($bs) && isset($bs["success"])) {
                $okSuccess = (int) $bs["success"];
            }
        }

        // Destroy % on the created book: random each extraction (not the scroll/config "destroy" — that key is legacy/unused here).
        $destroyOnBook = mt_rand(1, 100);

        $names = array_keys($all);
        $pick = $names[array_rand($names)];
        $e = CustomEnchantments::getEnchantmentByName($pick);
        if ($e === null) {
            return false;
        }
        $lvl = (int) $all[$pick];
        CustomEnchantmentManager::removeEnchantment($target, $e);
        self::refreshTransmogDisplayName($target, $config);
        $newBook = MartianEnchantmentBooks::create(
            $e,
            $lvl,
            $okSuccess,
            $destroyOnBook
        );
        if ($newBook === null) {
            return false;
        }
        $leftover = $player->getInventory()->addItem($newBook);
        foreach ($leftover as $drop) {
            if (!$drop->isNull()) {
                $player->getWorld()->dropItem($player->getPosition()->add(0, 0.5, 0), $drop);
            }
        }
        $scrollAction->getInventory()->setItem($scrollAction->getSlot(), VanillaItems::AIR());
        $targetAction->getInventory()->setItem($targetAction->getSlot(), $target);
        $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.black-extract")));
        self::playOk($player, $config);

        return true;
    }

    private static function tryTransmog(
        Player $player,
        Item $scroll,
        Item $target,
        SlotChangeAction $scrollAction,
        SlotChangeAction $targetAction,
        Config $config,
        LanguageManager $lang
    ): bool {
        $all = CustomEnchantmentManager::getEnchantments($target);
        if (count($all) < 1) {
            return false;
        }
        $rows = [];
        foreach ($all as $name => $level) {
            $ce = CustomEnchantments::getEnchantmentByName($name);
            if ($ce === null) {
                continue;
            }
            $rows[] = ['name' => $ce->getName(), 'level' => (int) $level, 'r' => $ce->getRarity()];
        }
        if ($rows === []) {
            return false;
        }
        usort($rows, static fn (array $a, array $b): int => $b['r'] <=> $a['r']);
        $nbt = new CompoundTag();
        foreach ($rows as $r) {
            $nbt->setInt($r['name'], $r['level']);
        }
        $root = $target->getNamedTag();
        $mGear = $root->getCompoundTag("MartianEnchantments") ?? new CompoundTag();

        $count = count($rows);
        $fmt = (string) $config->getNested("items.transmogscroll.enchants-count-formatting", "&d[&b{count}&d]");
        $suffixRaw = str_replace("{count}", (string) $count, $fmt);
        $suffix = C::colorize($suffixRaw);

        $base = trim($mGear->getString("transmogBaseDisplay", ""));
        if ($base === "") {
            $base = $target->hasCustomName()
                ? $target->getCustomName()
                : C::RESET . $target->getName();
        }
        $mGear->setString("transmogBaseDisplay", $base);

        $root->setTag("MartianCES", $nbt);
        $root->setTag("MartianEnchantments", $mGear);
        $target->setNamedTag($root);
        $target->setCustomName($base . ($suffix !== "" ? " " . $suffix : ""));
        Utils::updateGlowEffect($target);
        $scrollAction->getInventory()->setItem($scrollAction->getSlot(), VanillaItems::AIR());
        $targetAction->getInventory()->setItem($targetAction->getSlot(), $target);
        $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.transmog-success")));
        self::playOk($player, $config);

        return true;
    }

    private static function trySlotIncreaser(
        Player $player,
        Item $scroll,
        Item $target,
        SlotChangeAction $scrollAction,
        SlotChangeAction $targetAction,
        Config $config,
        LanguageManager $lang
    ): bool {
        $dTag = $scroll->getNamedTag()->getCompoundTag("MartianEnchantments");
        $sGroup = (string) $dTag?->getString("group", "");
        $bGroup = self::anyEnchantGroupKey($target);
        $sG = strtoupper($sGroup);
        $bG = $bGroup !== null ? strtoupper($bGroup) : null;
        if ($sG !== "" && $bG !== null && $sG !== $bG) {
            if ((bool) (GeneralUtils::getConfiguration(Loader::getInstance(), "groups.yml")?->getNested("settings.magic-dust.limit-applying-per-group", true))) {
                $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.slot-wrong-group")));
                return true;
            }
        }
        $add = (int) $dTag?->getInt("count", 1);
        if ($add < 1) {
            $add = 1;
        }
        $defaultMax = (int) $config->getNested("settings.slots.max", 9);
        $cap = (int) $config->getNested("settings.slots.max-increase-of-slots", 13);
        $m = $target->getNamedTag()->getCompoundTag("MartianEnchantments") ?? new CompoundTag();
        $cur = (int) $m->getInt("maxEnchantSlots", $defaultMax);
        $m->setInt("maxEnchantSlots", min($cap, $cur + $add));
        $target->getNamedTag()->setTag("MartianEnchantments", $m);
        $scrollAction->getInventory()->setItem($scrollAction->getSlot(), VanillaItems::AIR());
        $targetAction->getInventory()->setItem($targetAction->getSlot(), $target);
        $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.slot-success")));
        self::playOk($player, $config);

        return true;
    }

    private static function anyEnchantGroupKey(Item $item): ?string {
        $all = CustomEnchantmentManager::getEnchantments($item);
        if ($all === []) {
            return null;
        }
        $first = array_key_first($all);
        $e = CustomEnchantments::getEnchantmentByName($first);
        if ($e === null) {
            return null;
        }

        return strtoupper((string) (Groups::getGroupNameById($e->getRarity()) ?? ""));
    }

    private static function tryTrakApply(
        string $trakId,
        Player $player,
        Item $creator,
        Item $target,
        SlotChangeAction $scrollAction,
        SlotChangeAction $targetAction,
        Config $config,
        LanguageManager $lang
    ): bool {
        $m = $target->getNamedTag()->getCompoundTag("MartianEnchantments") ?? new CompoundTag();
        if ($m->getString("trackType", "") !== "") {
            $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.trak-already")));
            self::playFail($player, $config);
            return true;
        }
        if ($trakId === "stattrak" && !self::isWeaponOrbTarget($target)) {
            $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.trak-wrong-item")));
            self::playFail($player, $config);
            return true;
        }
        if ($trakId === "mobtrak" && !self::isWeaponOrbTarget($target)) {
            $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.trak-wrong-item")));
            self::playFail($player, $config);
            return true;
        }
        if ($trakId === "blocktrak") {
            $isBlockTool = $target instanceof Pickaxe || $target instanceof Shovel || $target instanceof Hoe
                || $target instanceof Axe;
            if (!$isBlockTool) {
                $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.trak-wrong-item")));
                self::playFail($player, $config);
                return true;
            }
        }
        $m->setString("trackType", $trakId);
        $m->setInt("trackCount", 0);
        $target->getNamedTag()->setTag("MartianEnchantments", $m);
        $lore = $config->getNested("items.{$trakId}.settings.lore-display", "");
        if (is_string($lore) && $lore !== "") {
            $lore = str_replace("{stats}", "0", $lore);
            $lines = $target->getLore();
            $lines[] = C::colorize($lore);
            $target->setLore($lines);
        }
        $scrollAction->getInventory()->setItem($scrollAction->getSlot(), VanillaItems::AIR());
        $targetAction->getInventory()->setItem($targetAction->getSlot(), $target);
        $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.trak-applied")));
        self::playOk($player, $config);

        return true;
    }

    private static function trySoulTracker(
        Player $player,
        Item $tracker,
        Item $target,
        SlotChangeAction $scrollAction,
        SlotChangeAction $targetAction,
        Config $config,
        LanguageManager $lang
    ): bool {
        $m = $target->getNamedTag()->getCompoundTag("MartianEnchantments") ?? new CompoundTag();
        if ($m->getInt("soulTrack", 0) > 0) {
            $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.soultrack-already")));
            self::playFail($player, $config);
            return true;
        }
        $m->setInt("soulTrack", 1);
        $m->setInt("soulsKilled", 0);
        $target->getNamedTag()->setTag("MartianEnchantments", $m);
        $sLore = (string) $config->getNested("settings.souls.lore", "");
        if ($sLore !== "") {
            $sLore = str_replace("{souls}", "0", $sLore);
            $lines = $target->getLore();
            $lines[] = C::colorize($sLore);
            $target->setLore($lines);
        }
        $scrollAction->getInventory()->setItem($scrollAction->getSlot(), VanillaItems::AIR());
        $targetAction->getInventory()->setItem($targetAction->getSlot(), $target);
        $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.soultrack-applied")));
        self::playOk($player, $config);
        return true;
    }

    private static function tryOrb(
        string $orbId,
        Player $player,
        Item $orb,
        Item $target,
        SlotChangeAction $orbAction,
        SlotChangeAction $targetAction,
        Config $config,
        LanguageManager $lang
    ): bool {
        $oTag = $orb->getNamedTag()->getCompoundTag("MartianEnchantments");
        if ($oTag === null) {
            return false;
        }
        $ok = $orbId === "weapon-orb" && self::isWeaponOrbTarget($target);
        if ($orbId === "armor-orb") {
            $ok = self::isArmorOrbTarget($target);
        }
        if ($orbId === "tool-orb") {
            $ok = self::isToolOrbTarget($target) || ($target instanceof Axe) || ($target instanceof Pickaxe) || $target instanceof Shovel || $target instanceof Hoe;
        }
        if (!$ok) {
            $player->sendMessage(C::colorize((string) $lang->getNested("orbs.cannot-apply")));
            self::playFail($player, $config);
            return true;
        }
        $success = (int) $oTag->getInt("success", 100);
        $add = (int) $oTag->getInt("new", 0);
        $maxCap = (int) $oTag->getInt("max", 9);
        if (mt_rand(1, 100) > $success) {
            $player->sendMessage(C::colorize((string) $lang->getNested("orbs.failed")));
            $orbAction->getInventory()->setItem($orbAction->getSlot(), VanillaItems::AIR());
            self::playFail($player, $config);
            return true;
        }
        $m = $target->getNamedTag()->getCompoundTag("MartianEnchantments") ?? new CompoundTag();
        $def = (int) $config->getNested("settings.slots.max", 9);
        $cur = (int) $m->getInt("maxEnchantSlots", $def);
        $afterSlots = min($maxCap, $cur + $add);
        $m->setInt("maxEnchantSlots", $afterSlots);
        $target->getNamedTag()->setTag("MartianEnchantments", $m);
        $slotLoreTpl = trim((string) $config->getNested("items.orb.lore", ""));
        if ($slotLoreTpl !== "") {
            $displayLine = str_replace(["{max}", "{increased}"], [(string) $afterSlots, (string) $add], $slotLoreTpl);
            $lines = $target->getLore();
            $lines[] = C::colorize($displayLine);
            $target->setLore($lines);
        }
        $orbAction->getInventory()->setItem($orbAction->getSlot(), VanillaItems::AIR());
        $targetAction->getInventory()->setItem($targetAction->getSlot(), $target);
        $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.orb-success")));
        self::playOk($player, $config);
        return true;
    }
}
