<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\armor;

use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;

/**
 * Maps {@code material-tier} + armor slot to a vanilla item id string for {@see StringToItemParser}.
 */
final class ArmorSetTierResolver {

    /**
     * @return list<string> candidates tried in order
     */
    public static function armorCandidates(string $tier, string $slot): array {
        $t = strtoupper(trim($tier));
        if ($t === "GOLD") {
            $t = "GOLDEN";
        }

        $slot = strtolower($slot);

        $rows = [
            "LEATHER" => [
                "helmet" => ["leather_helmet", "minecraft:leather_helmet", "leather_cap"],
                "chestplate" => ["leather_chestplate", "minecraft:leather_chestplate", "leather_tunic"],
                "leggings" => ["leather_leggings", "minecraft:leather_leggings", "leather_pants"],
                "boots" => ["leather_boots", "minecraft:leather_boots"],
            ],
            "CHAINMAIL" => [
                "helmet" => ["chainmail_helmet", "minecraft:chainmail_helmet"],
                "chestplate" => ["chainmail_chestplate", "minecraft:chainmail_chestplate"],
                "leggings" => ["chainmail_leggings", "minecraft:chainmail_leggings"],
                "boots" => ["chainmail_boots", "minecraft:chainmail_boots"],
            ],
            "IRON" => [
                "helmet" => ["iron_helmet", "minecraft:iron_helmet"],
                "chestplate" => ["iron_chestplate", "minecraft:iron_chestplate"],
                "leggings" => ["iron_leggings", "minecraft:iron_leggings"],
                "boots" => ["iron_boots", "minecraft:iron_boots"],
            ],
            "GOLDEN" => [
                "helmet" => ["golden_helmet", "gold_helmet", "minecraft:golden_helmet"],
                "chestplate" => ["golden_chestplate", "minecraft:golden_chestplate"],
                "leggings" => ["golden_leggings", "minecraft:golden_leggings"],
                "boots" => ["golden_boots", "minecraft:golden_boots"],
            ],
            "DIAMOND" => [
                "helmet" => ["diamond_helmet", "minecraft:diamond_helmet"],
                "chestplate" => ["diamond_chestplate", "minecraft:diamond_chestplate"],
                "leggings" => ["diamond_leggings", "minecraft:diamond_leggings"],
                "boots" => ["diamond_boots", "minecraft:diamond_boots"],
            ],
            "NETHERITE" => [
                "helmet" => ["netherite_helmet", "minecraft:netherite_helmet"],
                "chestplate" => ["netherite_chestplate", "minecraft:netherite_chestplate"],
                "leggings" => ["netherite_leggings", "minecraft:netherite_leggings"],
                "boots" => ["netherite_boots", "minecraft:netherite_boots"],
            ],
        ];

        return $rows[$t][$slot] ?? self::fallbackDiamond($slot);
    }

    /**
     * @return list<string>
     */
    private static function fallbackDiamond(string $slot): array {
        return match ($slot) {
            "helmet" => ["diamond_helmet"],
            "chestplate" => ["diamond_chestplate"],
            "leggings" => ["diamond_leggings"],
            "boots" => ["diamond_boots"],
            default => ["diamond_helmet"],
        };
    }

    public static function parseArmorPiece(string $tier, string $slot): Item {
        foreach (self::armorCandidates($tier, $slot) as $try) {
            $parsed = StringToItemParser::getInstance()->parse($try);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return match (strtolower($slot)) {
            "chestplate" => VanillaItems::DIAMOND_CHESTPLATE(),
            "leggings" => VanillaItems::DIAMOND_LEGGINGS(),
            "boots" => VanillaItems::DIAMOND_BOOTS(),
            default => VanillaItems::DIAMOND_HELMET(),
        };
    }

    /**
     * @param list<string> $candidates
     */
    public static function parseFirst(array $candidates): ?Item {
        foreach ($candidates as $try) {
            $parsed = StringToItemParser::getInstance()->parse($try);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }
}
