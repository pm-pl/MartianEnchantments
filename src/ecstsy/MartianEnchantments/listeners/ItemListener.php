<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\listeners;

use ecstsy\MartianEnchantments\enchantments\CustomEnchantmentManager;
use ecstsy\MartianEnchantments\enchantments\CustomEnchantments;
use ecstsy\MartianEnchantments\enchantments\Groups;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\PlayerUtils;
use ecstsy\MartianEnchantments\Loader;

use ecstsy\MartianEnchantments\utils\EnchantApplyGate;
use ecstsy\MartianEnchantments\utils\EnchanterBookOpenHelper;
use ecstsy\MartianEnchantments\utils\ItemApplyHelper;
use pocketmine\entity\object\FireworkRocket;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;

use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\VanillaItems;

use pocketmine\player\Player;
use pocketmine\utils\Config;

use pocketmine\utils\TextFormat as C;

final class ItemListener implements Listener {

    public function onBlockPlace(BlockPlaceEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $tag = $item->getNamedTag();

        if ($tag->getTag("MartianEnchantments") !== null) {
            $event->cancel();
        }
    }

    public function onPlayerItemUse(PlayerItemUseEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();

        $tag = $item->getNamedTag()->getCompoundTag("MartianEnchantments");
        if ($tag === null) {
            return;
        }

        if ($tag->getTag("martianItem") === null) {
            return;
        }

        $martianId = $tag->getString("martianItem");

        if ($martianId === "enchanter-book") {
            $event->cancel();
            EnchanterBookOpenHelper::tryOpen($player, $item);

            return;
        }

        if ($martianId !== "enchantment-book") {
            return;
        }

        $event->cancel();
        $lang = Loader::getInstance()->getLanguageManager();

        $enchant = strtolower($tag->getString("enchant-no-color", ""));
        $enchantment = CustomEnchantments::getEnchantmentByName($enchant);

        if ($enchantment === null) {
            $player->sendMessage(C::colorize((string) $lang->getNested("interact.enchantment-book.unknown-enchant")));
            return;
        }

        $level = $tag->getInt("level", 0);
        if ($level < 1) {
            $player->sendMessage(C::colorize((string) $lang->getNested("interact.enchantment-book.invalid-level")));
            return;
        }

        $header = $lang->getNested("interact.enchantment-book.header");
        if (is_string($header) && $header !== "" && !str_starts_with($header, "Translation not found:")) {
            $player->sendMessage(C::colorize($header));
        }

        $rawLines = $lang->getNested("interact.enchantment-book.lines");
        $lines = is_array($rawLines) ? $rawLines : [];

        $replacements = [
            "{enchant}" => CustomEnchantments::getEnchantmentDisplayName(
                $enchantment->getName(),
                Groups::translateGroupToColor($enchantment->getRarity())
            ),
            "{applies}" => implode(", ", $enchantment->getApplicableItems()),
            "{max-level}" => (string) $enchantment->getMaxLevel(),
            "{roman-level}" => GeneralUtils::getRomanNumeral($enchantment->getMaxLevel()),
            "{description}" => $enchantment->getDescription()
        ];

        foreach ($lines as $line) {
            $player->sendMessage(C::colorize(str_replace(
                array_keys($replacements),
                array_values($replacements),
                (string) $line
            )));

        }
    }

    public function onDragDropEnchant(InventoryTransactionEvent $event): void {
        $transaction = $event->getTransaction();
        $actions = array_values($transaction->getActions());

        if (count($actions) !== 2) {
            return;
        }

        $loader = Loader::getInstance();
        $config = GeneralUtils::getConfiguration($loader, "config.yml");

        if (!($config instanceof Config)) {
            return;
        }

        $language = $loader->getLanguageManager();
        $source = $transaction->getSource();
        if ($source instanceof Player && ItemApplyHelper::tryProcess($event, $source, $actions, $config, $language)) {
            return;
        }

        if (!(bool) $config->getNested("settings.enchantment-book.drag-drop-application", true)) {

            return;
        }

        $bookAction = null;
        $itemAction = null;
        $bookTag = null;
        $item = null;

        foreach ($actions as $action) {

            if (!$action instanceof SlotChangeAction) {
                continue;
            }


            $source = $action->getSourceItem();
            $tag = $source->getNamedTag()->getCompoundTag("MartianEnchantments");

            if ($tag !== null && $tag->getString("martianItem", "") === "enchantment-book") {
                $bookAction = $action;
                $bookTag = $tag;
            } elseif ($source->getTypeId() !== VanillaItems::AIR()->getTypeId()) {
                $itemAction = $action;
                $item = $source;
            }
        }

        if ($bookAction === null || $itemAction === null || $bookTag === null || $item === null) {
            return;
        }

        $event->cancel();
        $player = $transaction->getSource();

        if (!$player instanceof Player) {
            return;
        }


        $enchantKey = strtolower($bookTag->getString("enchant-no-color", ""));
        $level = $bookTag->getInt("level", 0);
        $success = $bookTag->getInt("success", 100);
        $destroy = $bookTag->getInt("destroy", 0);


        if ($enchantKey === "" || $level < 1) {
            $player->sendMessage(C::colorize((string) $language->getNested("commands.enchantment-not-found")));
            self::playApplyFailSound($player, $config);


            return;
        }

        $enchantment = CustomEnchantments::getEnchantmentByName($enchantKey);
        if ($enchantment === null) {

            $player->sendMessage(C::colorize(str_replace(
                "{enchant}",
                $enchantKey,
                (string) $language->getNested("commands.enchantment-not-found")
            )));
            self::playApplyFailSound($player, $config);

            return;
        }

        if (!EnchantApplyGate::passesGlobalGearAllowlist($item)) {
            $player->sendMessage(C::colorize((string) $language->getNested("applying.gear-not-whitelisted")));
            self::playApplyFailSound($player, $config);

            return;
        }

        if ((bool) $config->getNested("settings.enchantLimitation.enabled", true)) {

            $limLore = (string) $config->getNested("settings.enchantLimitation.lore", "");
            $limNbtKey = (string) $config->getNested("settings.enchantLimitation.NBT-tag", "unmodifiable");

            $blocked = false;

            if ($limLore !== "") {
                foreach ($item->getLore() as $line) {
                    if (C::clean($line) === C::clean(C::colorize($limLore))) {
                        $blocked = true;
                        break;
                    }
                }
            }

            if (!$blocked && $limNbtKey !== "") {
                if ($item->getNamedTag()->getTag($limNbtKey) !== null) {
                    $blocked = true;
                }
            }

            if ($blocked) {

                $player->sendMessage(C::colorize((string) $language->getNested("enchant-limitations.cannot-be-modified")));
                self::playApplyFailSound($player, $config);


                return;
            }
        }

        $itemTag = $item->getNamedTag()->getCompoundTag("MartianEnchantments");
        $maxSlots = $itemTag?->getInt("maxEnchantSlots", 9) ?? 9;

        $currentEnchants = CustomEnchantmentManager::getEnchantments($item);

        $combiningEnabled = (bool) $config->getNested("settings.combining.enabled", false);
        $upgradeEnabled = (bool) $config->getNested("settings.combining.chances.upgrade", true);
        $useChances = (bool) $config->getNested("settings.combining.chances.use-chances", true);

        $existingLevel = $currentEnchants[$enchantKey] ?? 0;

        /** Combining / upgrade path */
        if ($existingLevel > 0) {
            if (!$combiningEnabled) {
                $player->sendMessage(C::colorize((string) $language->getNested("applying.already-applied")));
                self::playApplyFailSound($player, $config);


                return;
            }

            if (!$upgradeEnabled) {

                $player->sendMessage(C::colorize((string) $language->getNested("combining.something-went-wrong")));
                self::playApplyFailSound($player, $config);


                return;
            }

            if ($existingLevel >= $enchantment->getMaxLevel()) {

                $player->sendMessage(C::colorize((string) $language->getNested("combining.already-max-level")));
                self::playApplyFailSound($player, $config);


                return;
            }

            if ($level !== $existingLevel) {
                $player->sendMessage(
                    C::colorize(
                        str_replace(
                            ["{enchant}", "{level}"],
                            [$enchantment->getName(), GeneralUtils::getRomanNumeral($existingLevel)],

                            (string) $language->getNested("combining.requires-same-level")
                        )
                    )
                );
                self::playApplyFailSound($player, $config);


                return;
            }

            $targetLevel = min($existingLevel + 1, $enchantment->getMaxLevel());

            if (!$useChances || mt_rand(1, 100) <= $success) {
                CustomEnchantmentManager::applyEnchantment($item, $enchantment, $targetLevel);

                ItemApplyHelper::refreshTransmogDisplayName($item, $config);

                self::playApplySuccessSound($player, $config);


                $bookAction->getInventory()->setItem($bookAction->getSlot(), VanillaItems::AIR());
                $itemAction->getInventory()->setItem($itemAction->getSlot(), $item);

                $player->sendMessage(C::colorize(str_replace(
                    ["{enchant}", "{level}"],
                    [
                        CustomEnchantments::getEnchantmentDisplayName(
                            $enchantment->getName(),
                            Groups::translateGroupToColor($enchantment->getRarity())
                        ),
                        GeneralUtils::getRomanNumeral($targetLevel)
                    ],
                    (string) $language->getNested("combining.success")
                )));

                return;
            }

            self::playApplyFailSound($player, $config);
            $bookAction->getInventory()->setItem($bookAction->getSlot(), VanillaItems::AIR());
            $player->sendMessage(C::colorize((string) $language->getNested("combining.failure")));

            return;
        }

        /** First-time application */
        $enchCfg = GeneralUtils::getConfiguration(Loader::getInstance(), "enchantments.yml");
        if ($enchCfg === null) {
            $player->sendMessage(C::colorize((string) $language->getNested("commands.enchantment-not-found")));
            self::playApplyFailSound($player, $config);

            return;
        }

        $enchData = $enchCfg->get($enchantKey);
        if (!is_array($enchData)) {
            foreach ($enchCfg->getAll() as $k => $v) {
                if (strtolower((string) $k) === $enchantKey) {

                    $enchData = is_array($v) ? $v : null;
                    break;
                }
            }
        }

        $settings = is_array($enchData) ? ($enchData["settings"] ?? []) : [];

        $required = array_map("strtolower", (array) ($settings["required-enchants"] ?? []));
        $blockedWith = array_map("strtolower", (array) ($settings["not-applyable-with"] ?? []));


        $currentKeys = array_map("strtolower", array_keys($currentEnchants));

        if ($required !== []) {
            foreach ($required as $req) {

                if ($req === "") {
                    continue;
                }

                if (!in_array($req, $currentKeys, true)) {
                    $player->sendMessage(C::colorize(str_replace(
                        ["{enchant1}", "{enchant2}"],
                        [$enchantment->getName(), $req],
                        (string) $language->getNested("applying.requires-enchant")
                    )));
                    self::playApplyFailSound($player, $config);


                    return;
                }
            }
        }

        if ($blockedWith !== []) {
            foreach ($blockedWith as $blocked) {

                if ($blocked === "") {
                    continue;
                }

                if (in_array($blocked, $currentKeys, true)) {
                    $player->sendMessage(C::colorize(str_replace(
                        ["{enchant1}", "{enchant2}"],
                        [$enchantment->getName(), $blocked],
                        (string) $language->getNested("applying.not-applicable-with")
                    )));
                    self::playApplyFailSound($player, $config);


                    return;
                }
            }
        }

        if (count($currentEnchants) >= $maxSlots) {

            $player->sendMessage(C::colorize((string) $language->getNested("slots.limit-reached")));
            self::playApplyFailSound($player, $config);


            return;
        }

        if (!$enchantment->matches($item)) {

            $player->sendMessage(C::colorize((string) $language->getNested("applying.wrong-material")));
            self::playApplyFailSound($player, $config);


            return;
        }

        if (mt_rand(1, 100) <= $success) {
            $newLevel = min($level, $enchantment->getMaxLevel());
            CustomEnchantmentManager::applyEnchantment($item, $enchantment, $newLevel);

            ItemApplyHelper::refreshTransmogDisplayName($item, $config);

            self::playApplySuccessSound($player, $config);
            $player->sendMessage(C::colorize((string) $language->getNested("applying.applied")));

            $bookAction->getInventory()->setItem($bookAction->getSlot(), VanillaItems::AIR());
            $itemAction->getInventory()->setItem($itemAction->getSlot(), $item);


            return;
        }

        if (mt_rand(1, 100) <= $destroy) {

            if (ItemApplyHelper::hasWhiteScrollProtection($item)) {
                ItemApplyHelper::clearWhiteScrollProtection($item);
                $bookAction->getInventory()->setItem($bookAction->getSlot(), VanillaItems::AIR());
                $itemAction->getInventory()->setItem($itemAction->getSlot(), $item);
                $player->sendMessage(C::colorize((string) $language->getNested("items.white-scroll.item-saved")));
                self::playApplyFailSound($player, $config);
                return;
            }
            if (ItemApplyHelper::hasHolyWhiteScrollProtection($item)) {
                ItemApplyHelper::clearHolyWhiteScrollProtection($item);
                $bookAction->getInventory()->setItem($bookAction->getSlot(), VanillaItems::AIR());
                $itemAction->getInventory()->setItem($itemAction->getSlot(), $item);
                $holy = $language->getNested("items.holywhitescroll.item-saved");
                $msg = is_string($holy) && $holy !== "" && !str_starts_with($holy, "Translation not found:")
                    ? $holy
                    : (string) $language->getNested("items.white-scroll.item-saved");
                $player->sendMessage(C::colorize($msg));
                self::playApplyFailSound($player, $config);
                return;
            }
            $itemAction->getInventory()->setItem($itemAction->getSlot(), VanillaItems::AIR());
            $bookAction->getInventory()->setItem($bookAction->getSlot(), VanillaItems::AIR());
            $player->sendMessage(C::colorize((string) $language->getNested("destroy.book-failed")));
            self::playDestroySound($player, $config);


            return;
        }

        $bookAction->getInventory()->setItem($bookAction->getSlot(), VanillaItems::AIR());

        $player->sendMessage(C::colorize((string) $language->getNested("chances.book-failed")));
        self::playApplyFailSound($player, $config);
    }

    private static function playApplySuccessSound(Player $player, Config $config): void {
        $sound = (string) $config->getNested("settings.applying.cosmetics.applied.sound", "random.levelup");
        PlayerUtils::playSound($player, $sound);
    }

    private static function playApplyFailSound(Player $player, Config $config): void {
        $sound = (string) $config->getNested("settings.applying.cosmetics.failed.sound", "random.anvil_break");
        PlayerUtils::playSound($player, $sound);
    }

    /**
     * Stronger feedback when the item is destroyed (configurable; defaults to fail sound if unset).
     */
    private static function playDestroySound(Player $player, Config $config): void {
        $fallback = (string) $config->getNested("settings.applying.cosmetics.failed.sound", "random.anvil_break");
        $sound = (string) $config->getNested("settings.applying.cosmetics.destroy.sound", $fallback);
        PlayerUtils::playSound($player, $sound);
    }

    /**
     * Keeps transmog display suffix in sync when inventory contents change (runs after drag-drop handling).
     *
     * @priority MONITOR
     */
    public function onInventoryTransactionTransmog(InventoryTransactionEvent $event): void {
        $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");
        if (!($cfg instanceof Config)) {
            return;
        }

        foreach ($event->getTransaction()->getActions() as $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }

            $target = $action->getTargetItem();
            if ($target->isNull()) {
                continue;
            }

            $before = $target->getCustomName();
            if (!ItemApplyHelper::refreshTransmogDisplayName($target, $cfg)) {
                continue;
            }

            if ($target->getCustomName() !== $before) {
                $action->getInventory()->setItem($action->getSlot(), $target);
            }
        }
    }

    /**
     * Cancels damage to players from cosmetic {@see FireworkRocket} entities (enchanter-book fireworks).
     */
    public function onCosmeticFireworkDamage(EntityDamageByEntityEvent $event): void {
        $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");
        if (!($cfg instanceof Config)) {
            return;
        }

        if (!(bool) $cfg->getNested("settings.enchanter-books.cancel-firework-damage", true)) {
            return;
        }

        $entity = $event->getEntity();
        if (!$entity instanceof Player) {
            return;
        }

        if ($event->getDamager() instanceof FireworkRocket) {
            $event->cancel();
        }

    }
}
