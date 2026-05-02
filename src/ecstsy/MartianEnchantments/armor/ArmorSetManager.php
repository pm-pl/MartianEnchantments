<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\armor;

use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianEnchantments\Loader;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\Config;

/**
 * Full armor set detection (4/4 matching pieces) and world disable checks.
 */
final class ArmorSetManager {

    public static function readMartianItemKey(Item $item): ?string {
        $m = $item->getNamedTag()->getCompoundTag("MartianEnchantments");
        if ($m === null) {
            return null;
        }

        $id = $m->getString("martianItem", "");

        return $id !== "" ? strtolower($id) : null;
    }

    public static function getArmorSetTag(Item $item): ?string {
        $m = $item->getNamedTag()->getCompoundTag("MartianEnchantments");
        if ($m === null) {
            return null;
        }

        $s = $m->getString("armorSet", "");

        return $s !== "" ? strtolower($s) : null;
    }

    /**
     * True for armor / weapon items created by {@see ArmorSetItemBuilder}.
     */
    public static function isMartianArmorItem(Item $item): bool {
        $m = $item->getNamedTag()->getCompoundTag("MartianEnchantments");

        return $m !== null && $m->getInt("martianArmor", 0) === 1;
    }

    public static function getArmorPieceSlot(Item $item): ?string {
        $m = $item->getNamedTag()->getCompoundTag("MartianEnchantments");
        if ($m === null) {
            return null;
        }

        $p = $m->getString("armorPiece", "");

        return $p !== "" ? strtolower($p) : null;
    }

    /**
     * Returns set id when all four armor slots match the same loaded definition and piece ids.
     */
    public static function getFullSet(Player $player): ?string {
        $inv = $player->getArmorInventory();
        /** @var array<string, Item> $slots */
        $slots = [
            "helmet" => $inv->getHelmet(),
            "chestplate" => $inv->getChestplate(),
            "leggings" => $inv->getLeggings(),
            "boots" => $inv->getBoots(),
        ];

        $first = null;

        foreach ($slots as $slotName => $item) {
            if ($item->isNull() || !self::isMartianArmorItem($item)) {
                return null;
            }

            $pieceKind = self::getArmorPieceSlot($item);
            if ($pieceKind !== $slotName) {
                return null;
            }

            $setId = self::getArmorSetTag($item);
            $mid = self::readMartianItemKey($item);
            if ($setId === null || $mid === null) {
                return null;
            }

            $def = ArmorSetRegistry::get($setId);
            if ($def === null) {
                return null;
            }

            $expected = $def->expectedPieceId($slotName);
            if ($expected === null || strtolower($expected) !== $mid) {
                return null;
            }

            if ($first === null) {
                $first = $setId;
            } elseif ($first !== $setId) {
                return null;
            }
        }

        return $first;
    }

    public static function isSetDisabledInWorld(Player $player, string $setId): bool {
        $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");
        if (!($cfg instanceof Config)) {
            return false;
        }

        $world = $player->getWorld()->getFolderName();
        $node = $cfg->getNested("settings.disable-armorsets.$world");
        if (!\is_array($node)) {
            return false;
        }

        $want = strtolower($setId);
        foreach ($node as $entry) {
            if (strtolower((string) $entry) === $want) {
                return true;
            }
        }

        return false;
    }

    /**
     * Weapon bonus applies only if the held item belongs to the same set as {@see getFullSet()}.
     */
    public static function heldWeaponMatchesSet(Player $player, string $activeSetId): bool {
        $held = $player->getInventory()->getItemInHand();
        if ($held->isNull()) {
            return false;
        }

        $tagSet = self::getArmorSetTag($held);
        if ($tagSet === null || $tagSet !== strtolower($activeSetId)) {
            return false;
        }

        $piece = self::getArmorPieceSlot($held);

        return $piece === "weapon";
    }
}
