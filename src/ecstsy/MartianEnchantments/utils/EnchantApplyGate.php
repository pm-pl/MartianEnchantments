<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\utils;

use ecstsy\MartianEnchantments\enchantments\CustomEnchantments;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianEnchantments\Loader;
use pocketmine\item\enchantment\ItemEnchantmentTagRegistry;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\utils\Config;

final class EnchantApplyGate {

    public static function passesGlobalGearAllowlist(Item $gear): bool {
        $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");
        if (!$cfg instanceof Config) {
            return true;
        }
        /** @var mixed $allowed */
        $allowed = $cfg->getNested("items.settings.can-apply-to");
        if (!is_array($allowed) || $allowed === []) {
            return true;
        }
        $yamlTokensForTags = [];
        $allowEnchantedBooks = false;
        $allowEdibleGear = false;
        foreach ($allowed as $t) {
            if (!is_string($t)) {
                continue;
            }
            $u = strtoupper(trim($t));
            if ($u === "") {
                continue;
            }
            if ($u === "BOOK") {
                $allowEnchantedBooks = true;

                continue;
            }
            if ($u === "ALL_EDIBLE" || $u === "EDIBLE" || $u === "FOOD") {
                $allowEdibleGear = true;

                continue;
            }
            $yamlTokensForTags[] = $u;
        }

        // Nothing usable remained (blank strings only): do not tighten beyond "empty list".
        if ($yamlTokensForTags === [] && !$allowEnchantedBooks && !$allowEdibleGear) {
            return true;
        }

        $pmTags = CustomEnchantments::parseAppliesField($yamlTokensForTags);
        if ($pmTags !== [] && ItemEnchantmentTagRegistry::getInstance()->isTagArrayIntersection($gear->getEnchantmentTags(), $pmTags)) {
            return true;
        }
        if ($allowEnchantedBooks && !$gear->isNull()
            && $gear->getTypeId() === VanillaItems::ENCHANTED_BOOK()->getTypeId()) {
            return true;
        }
        if ($allowEdibleGear && !$gear->isNull()) {
            if (method_exists($gear, "getFood") && $gear->getFood() !== null) {
                return true;
            }
        }

        return false;
    }
}
