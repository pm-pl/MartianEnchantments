<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\utils;

use ecstsy\MartianEnchantments\enchantments\Groups;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\PlayerUtils;
use ecstsy\MartianEnchantments\Loader;
use ecstsy\MartianEnchantments\server\items\MartianEnchantItems;
use ecstsy\MartianEnchantments\server\items\MartianItems;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

/**
 * Right-click secret dust + {@see MartianItems::createScroll} mapping for {@see GiveItemSubCommand}.
 */
final class ScrollHandler {

    /**
     * Secret dust: consumes one item from stack and grants mystery or magic dust from the tagged group.
     */
    public static function openSecretDust(Player $player, Item $item): void {
        $lang = Loader::getInstance()->getLanguageManager();

        $mt = $item->getNamedTag()->getCompoundTag("MartianEnchantments");
        if ($mt === null) {
            return;
        }

        $gRaw = strtoupper(trim($mt->getString("group", "")));
        if ($gRaw === "") {
            $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.secret-invalid")));
            PlayerUtils::playSound($player, "note.bass");

            return;
        }

        $gid = Groups::getGroupId($gRaw);
        $groupName = trim($mt->getString("group-name", "")) !== ""
            ? (string) $mt->getString("group-name")
            : ($gid !== null ? (string) (Groups::getGroupName($gid) ?? $gRaw) : $gRaw);
        $rawColor = $mt->getString("group-color", "");
        $groupColor = $rawColor !== "" ? C::colorize($rawColor) : ($gid !== null ? Groups::translateGroupToColor($gid) : "");

        $mystPct = mt_rand(1, 25);
        $reward = mt_rand(0, 1) === 1
            ? MartianEnchantItems::mysteryDust($groupName, $groupColor, $mystPct, $gRaw)
            : MartianEnchantItems::magicDust($groupName, $groupColor, $mystPct, $gRaw);

        $item->pop();
        $player->getInventory()->setItemInHand($item);

        if ($player->getInventory()->canAddItem($reward)) {
            $player->getInventory()->addItem($reward);
        } else {
            $player->getWorld()->dropItem($player->getPosition()->add(0, 0.5, 0), $reward);
        }

        PlayerUtils::playSound($player, "random.levelup");
        $player->sendMessage(C::colorize((string) $lang->getNested("items.apply.secret-opened")));
    }

    /**
     * Builds a scroll/dust/trak registry item for {@see GiveItemSubCommand} (non-orb branch).
     *
     * @param array<string, mixed> $args raw Commando args including {@code extra1}–{@code extra3}
     */
    public static function createScrollFromGiveItem(string $item, int $amount, array $args, string $groupFallback): ?Item {
        $item = strtolower($item);

        if ($item === "randomizer" || $item === "secret") {
            return isset($args["extra1"])
                ? MartianItems::createScroll($item, $amount, 100, (string) $args["extra1"])
                : null;
        }

        if ($item === "blackscroll") {
            $rate = (int) ($args["extra1"] ?? 95);

            return MartianItems::createScroll("blackscroll", $amount, $rate, "");
        }

        if ($item === "soulgem") {
            $souls = (int) ($args["extra1"] ?? 1);
            $gForGem = isset($args["extra2"]) ? (string) $args["extra2"] : "";

            return MartianItems::createScroll("soulgem", $amount, max(1, $souls), $gForGem);
        }

        if ($item === "mystery" || $item === "magic") {
            $group = (string) ($args["extra1"] ?? $groupFallback);
            $pct = (int) ($args["extra2"] ?? 25);

            return MartianItems::createScroll($item, $amount, max(1, $pct), $group);
        }

        $group = (string) ($args["extra1"] ?? $groupFallback);

        return MartianItems::createScroll($item, $amount, 100, $group);
    }
}
