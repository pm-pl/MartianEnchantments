<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\armor;

use ecstsy\MartianEnchantments\enchantments\CustomEnchantmentManager;
use ecstsy\MartianEnchantments\enchantments\CustomEnchantments;
use pocketmine\color\Color;
use pocketmine\item\Armor;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\TextFormat;

final class ArmorSetItemBuilder {

    /**
     * @param array<string, mixed> $piece yaml fragment for one armor slot
     */
    public static function createArmorPiece(ArmorSetDefinition $set, string $slot, array $piece): Item {
        $slot = strtolower($slot);
        $override = isset($piece["item"]) ? trim((string) $piece["item"]) : "";
        if ($override !== "") {
            $item = StringToItemParser::getInstance()->parse($override)
                ?? ArmorSetTierResolver::parseArmorPiece($set->materialTier, $slot);
        } else {
            $item = ArmorSetTierResolver::parseArmorPiece($set->materialTier, $slot);
        }

        $pieceId = (string) ($piece["id"] ?? "");
        self::applyCommonTags($item, $set, $pieceId, $slot);

        $name = (string) ($piece["name"] ?? $pieceId);
        $item->setCustomName(TextFormat::colorize($name));

        if (!empty($piece["lore"]) && \is_array($piece["lore"])) {
            $lore = [];
            foreach ($piece["lore"] as $line) {
                $lore[] = TextFormat::colorize((string) $line);
            }
            $item->setLore($lore);
        }

        self::applyVanillaEnchantList($item, $piece["vanilla-enchantments"] ?? null);
        self::applyCustomEnchantList($item, $piece["custom-enchantments"] ?? null);

        if ($set->unbreakable && method_exists($item, "setUnbreakable")) {
            $item->setUnbreakable(true);
        }

        if (
            $item instanceof Armor
            && strtoupper($set->materialTier) === "LEATHER"
            && $set->leatherRgb !== null
        ) {
            [$r, $g, $b] = $set->leatherRgb;
            $item->setCustomColor(new Color($r, $g, $b));
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $weapon yaml weapon fragment; key is weapon id (martianItem)
     */
    public static function createWeapon(string $weaponKey, ArmorSetDefinition $set, array $weapon): Item {
        $weaponKey = strtolower(trim($weaponKey));
        $override = isset($weapon["item"]) ? trim((string) $weapon["item"]) : "";
        if ($override !== "") {
            $item = StringToItemParser::getInstance()->parse($override)
                ?? VanillaItems::DIAMOND_SWORD();
        } else {
            $item = VanillaItems::DIAMOND_SWORD();
        }

        self::applyCommonTags($item, $set, $weaponKey, "weapon");

        $name = (string) ($weapon["name"] ?? $weaponKey);
        $item->setCustomName(TextFormat::colorize($name));

        if (!empty($weapon["lore"]) && \is_array($weapon["lore"])) {
            $lore = [];
            foreach ($weapon["lore"] as $line) {
                $lore[] = TextFormat::colorize((string) $line);
            }
            $item->setLore($lore);
        }

        self::applyVanillaEnchantList($item, $weapon["vanilla-enchantments"] ?? null);
        self::applyCustomEnchantList($item, $weapon["custom-enchantments"] ?? null);

        if ($set->unbreakable && method_exists($item, "setUnbreakable")) {
            $item->setUnbreakable(true);
        }

        return $item;
    }

    private static function applyCommonTags(Item $item, ArmorSetDefinition $set, string $martianItemId, string $armorPiece): void {
        $root = $item->getNamedTag();
        $tag = new CompoundTag();
        $tag->setInt("martianArmor", 1);
        $tag->setString("armorSet", $set->id);
        $tag->setString("armorPiece", $armorPiece);
        $tag->setString("martianItem", strtolower($martianItemId));
        $root->setTag("MartianEnchantments", $tag);
        $item->setNamedTag($root);
    }

    private static function applyVanillaEnchantList(Item $item, mixed $list): void {
        if (!\is_array($list)) {
            return;
        }

        foreach ($list as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $id = trim((string) ($row["enchant"] ?? $row["id"] ?? ""));
            $lvl = (int) ($row["level"] ?? 1);
            if ($id === "") {
                continue;
            }

            $ench = StringToEnchantmentParser::getInstance()->parse($id);
            if ($ench === null) {
                continue;
            }

            $item->addEnchantment(new EnchantmentInstance($ench, max(1, $lvl)));
        }
    }

    private static function applyCustomEnchantList(Item $item, mixed $list): void {
        if (!\is_array($list)) {
            return;
        }

        foreach ($list as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $name = strtolower(trim((string) ($row["name"] ?? "")));
            $lvl = (int) ($row["level"] ?? 1);
            if ($name === "") {
                continue;
            }

            $ce = CustomEnchantments::getEnchantmentByName($name);
            if ($ce === null) {
                continue;
            }

            CustomEnchantmentManager::applyEnchantment($item, $ce, max(1, $lvl));
        }
    }
}
