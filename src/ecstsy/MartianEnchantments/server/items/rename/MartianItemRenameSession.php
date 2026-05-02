<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\server\items\rename;

use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianEnchantments\enchantments\CustomEnchantmentManager;
use ecstsy\MartianEnchantments\Loader;
use ecstsy\MartianEnchantments\server\items\MartianEnchantItemFactory;
use ecstsy\MartianEnchantments\utils\ItemApplyHelper;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as C;

/**
 * Two-step chat rename (Glacia-like): consumes name tag first, previews name, confirms with {@code confirm}.
 */
final class MartianItemRenameSession {

    private const STAGE_NAME = "name";
    private const STAGE_CONFIRM = "confirm";
    private const MAX_VISIBLE_LENGTH = 32;

    /** @var array<string, array{stage: string, preview?: string, slot?: int, refundPending: bool}> */
    private static array $sessions = [];

    private static ?Config $cachedConfig = null;

    /** After {@code /me reload} so blacklist / transmog format read fresh config. */
    public static function invalidateCachedConfig(): void {
        self::$cachedConfig = null;
    }

    public static function start(Player $player): void {
        if (self::hasSession($player)) {
            $player->sendMessage(C::colorize("&r&cYou already have a rename in progress. Type &acancel&r&cto get your tag back."));
            PlayerUtils::playSound($player, "note.bass");

            return;
        }

        $tag = $player->getInventory()->getItemInHand();
        if (ItemApplyHelper::martianId($tag) !== "itemnametag") {
            $player->sendMessage(C::colorize("&r&cHold an Item NameTag and right-click again."));
            PlayerUtils::playSound($player, "note.bass");

            return;
        }

        $tag->pop();
        $player->getInventory()->setItemInHand($tag);

        self::$sessions[$player->getUniqueId()->toString()] = [
            "stage" => self::STAGE_NAME,
            "refundPending" => true,
        ];

        $player->sendMessage(C::colorize("&r&6&l    Item Tag"));
        $player->sendMessage(C::colorize("&r&61. &7Hold the &fitem &7you want renamed."));
        $player->sendMessage(C::colorize("&r&62. &7Send the new name in chat (& format codes OK)."));
        $player->sendMessage(C::colorize("&r&63. &7Type &aconfirm &7to save, or &ccancel &7to abort."));
        PlayerUtils::playSound($player, "random.orb");
    }

    public static function hasSession(Player $player): bool {
        return isset(self::$sessions[$player->getUniqueId()->toString()]);
    }

    public static function clear(Player $player): void {
        unset(self::$sessions[$player->getUniqueId()->toString()]);
    }

    public static function abort(Player $player, bool $notify = false): void {
        $key = $player->getUniqueId()->toString();
        $session = self::$sessions[$key] ?? null;
        if ($session === null) {
            return;
        }

        self::refundTag($player, (bool) ($session["refundPending"] ?? false));
        unset(self::$sessions[$key]);

        if ($notify) {
            $player->sendMessage(C::colorize("&r&cRename cancelled."));
            PlayerUtils::playSound($player, "note.bass");
        }
    }

    public static function handleChat(Player $player, string $message): bool {
        $key = $player->getUniqueId()->toString();
        $session = self::$sessions[$key] ?? null;
        if ($session === null) {
            return false;
        }

        $message = trim(str_replace(["\n", "\r"], " ", $message));
        $lower = strtolower($message);

        if ($lower === "cancel") {
            self::abort($player, true);

            return true;
        }

        $stage = $session["stage"] ?? self::STAGE_NAME;

        if ($stage === self::STAGE_NAME) {
            if ($lower === "confirm") {
                $player->sendMessage(C::colorize("&r&cType the new name first, or type &lcancel&r&c."));
                PlayerUtils::playSound($player, "note.bass");

                return true;
            }

            $target = $player->getInventory()->getItemInHand();
            $validation = self::validateTargetItem($target);
            if ($validation !== null) {
                $player->sendMessage($validation);
                PlayerUtils::playSound($player, "note.bass");

                return true;
            }

            $colored = C::colorize($message);
            $clean = trim(C::clean($colored));

            if ($clean === "") {
                $player->sendMessage(C::colorize("&r&cThat name is empty or invalid."));
                PlayerUtils::playSound($player, "note.bass");

                return true;
            }

            if (strlen($clean) > self::MAX_VISIBLE_LENGTH) {
                $player->sendMessage(C::colorize("&r&cName too long (max &f" . self::MAX_VISIBLE_LENGTH . "&r&c visible chars)."));
                PlayerUtils::playSound($player, "note.bass");

                return true;
            }

            if ($err = self::blacklistViolation($clean)) {
                $player->sendMessage($err);
                PlayerUtils::playSound($player, "note.bass");

                return true;
            }

            self::$sessions[$key] = [
                "stage" => self::STAGE_CONFIRM,
                "preview" => $colored,
                "slot" => $player->getInventory()->getHeldItemIndex(),
                "refundPending" => (bool) ($session["refundPending"] ?? true),
            ];

            $player->sendMessage(C::colorize("&r&l&ePreview: ") . $colored);
            $player->sendMessage(C::colorize("&r&7Type &aconfirm &7to save, or &ccancel &r&7to choose another."));
            PlayerUtils::playSound($player, "random.click");

            return true;
        }

        if ($lower !== "confirm") {
            $player->sendMessage(C::colorize("&r&7Type &aconfirm &r&7or &ccancel&r&7."));
            PlayerUtils::playSound($player, "note.bass");

            return true;
        }

        $target = $player->getInventory()->getItemInHand();
        $validation = self::validateTargetItem($target);
        if ($validation !== null) {
            self::refundTag($player, (bool) ($session["refundPending"] ?? false));
            self::clear($player);
            $player->sendMessage($validation);
            PlayerUtils::playSound($player, "note.bass");

            return true;
        }

        $expectedSlot = $session["slot"] ?? $player->getInventory()->getHeldItemIndex();
        if ($player->getInventory()->getHeldItemIndex() !== $expectedSlot) {
            self::refundTag($player, (bool) ($session["refundPending"] ?? false));
            self::clear($player);
            $player->sendMessage(C::colorize("&r&cYou swapped hotbar slots. Your Item NameTag was returned."));
            PlayerUtils::playSound($player, "note.bass");

            return true;
        }

        $preview = (string) ($session["preview"] ?? "");
        $root = $target->getNamedTag();
        $mGear = $root->getCompoundTag("MartianEnchantments");
        if ($mGear !== null && trim($mGear->getString("transmogBaseDisplay", "")) !== "") {
            $cfg = self::cachedMainConfig();
            $fmt = (string) ($cfg?->getNested("items.transmogscroll.enchants-count-formatting", "&d[&b{count}&d]") ?? "&d[&b{count}&d]");
            $count = count(CustomEnchantmentManager::getEnchantments($target));
            $suffixRaw = str_replace("{count}", (string) $count, $fmt);
            $suffix = $suffixRaw !== "" ? C::colorize($suffixRaw) : "";
            $mGear->setString("transmogBaseDisplay", $preview);
            $root->setTag("MartianEnchantments", $mGear);
            $target->setNamedTag($root);
            $target->setCustomName($preview . ($suffix !== "" ? " " . $suffix : ""));
        } else {
            $target->setCustomName($preview);
        }
        $player->getInventory()->setItemInHand($target);

        self::clear($player);
        $player->sendMessage(C::colorize("&r&aRenamed to: ") . $preview);
        PlayerUtils::playSound($player, "random.anvil_use");

        return true;
    }

    private static function cachedMainConfig(): ?Config {
        if (self::$cachedConfig instanceof Config) {
            return self::$cachedConfig;
        }
        self::$cachedConfig = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");

        return self::$cachedConfig;
    }

    private static function validateTargetItem(\pocketmine\item\Item $item): ?string {
        if ($item->isNull()) {
            return C::colorize("&r&cHold the gear you want to rename.");
        }

        if (ItemApplyHelper::martianId($item) === "itemnametag") {
            return C::colorize("&r&cYou cannot rename another name tag.");
        }

        if ($item->getCount() > 1) {
            return C::colorize("&r&cHold a single stack (count 1) to rename.");
        }

        return null;
    }

    private static function blacklistViolation(string $visibleCleanLower): ?string {
        $cfg = self::cachedMainConfig();
        /** @var mixed $words */
        $words = $cfg?->getNested("items.itemnametag.settings.word-blacklist");
        if (!is_array($words) || $words === []) {
            return null;
        }
        foreach ($words as $w) {
            if (!is_string($w) || $w === "") {
                continue;
            }
            $needle = strtolower($w);
            if ($needle !== "" && str_contains($visibleCleanLower, $needle)) {
                return C::colorize("&r&cThat name contains a blocked word.");
            }
        }

        return null;
    }

    private static function refundTag(Player $player, bool $shouldRefund): void {
        if (!$shouldRefund) {
            return;
        }
        try {
            $refund = MartianEnchantItemFactory::create("itemnametag");
        } catch (\Throwable) {
            return;
        }
        $refund->setCount(1);

        $inv = $player->getInventory();
        if ($inv->canAddItem($refund)) {
            $inv->addItem($refund);

            return;
        }

        $player->getWorld()->dropItem($player->getPosition()->add(0, 0.5, 0), $refund);
    }
}
