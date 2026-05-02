<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\server\items;

use ecstsy\MartianEnchantments\enchantments\CustomEnchantment;
use ecstsy\MartianEnchantments\enchantments\CustomEnchantments;
use ecstsy\MartianEnchantments\enchantments\Groups;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\item\Item;
use pocketmine\utils\TextFormat as C;

final class MartianEnchantItems {

    public static function enchantmentBook(CustomEnchantment $enchantment, int $level = 1, ?int $forcedSuccessChance = null, ?int $forcedDestroyChance = null): Item {
        $appliesTo = array_map('ucfirst', $enchantment->getApplicableItems());
        $success = $forcedSuccessChance !== null ? $forcedSuccessChance : mt_rand(1, 100);
        $destroy = $forcedDestroyChance !== null ? $forcedDestroyChance : mt_rand(1, 100);

        $groupKey = Groups::getGroupNameById($enchantment->getRarity()) ?? Groups::getFallbackGroup();

        return MartianEnchantItemFactory::create('enchantment-book', [
            'enchantment' => CustomEnchantments::getEnchantmentDisplayName($enchantment->getName(), C::colorize(Groups::translateGroupToColor($enchantment->getRarity()))),
            'enchant-no-color' => ucfirst($enchantment->getName()),
            'level' => $level,
            'roman-level' => GeneralUtils::getRomanNumeral($level),
            'success' => $success,
            'destroy' => $destroy,
            'description' => $enchantment->getDescription(),
            'group-color' => C::colorize(Groups::translateGroupToColor($enchantment->getRarity())),
            'applies-to' => implode(", ", $appliesTo),
            'group' => $groupKey,
        ]);
    }

    public static function whiteScroll(): Item {
        return MartianEnchantItemFactory::create('white-scroll');
    }

    public static function blackScroll(int $success): Item {
        return MartianEnchantItemFactory::create('black-scroll', [
            'success' => $success
        ]);
    }

    public static function randomizationScroll(string $groupName, string $groupColor, string $groupKey): Item {
        return MartianEnchantItemFactory::create('randomization-scroll', [
            'group-name' => $groupName,
            'group-color' => $groupColor,
            'group' => $groupKey,
        ]);
    }

    public static function secretDust(string $groupName, string $groupColor, string $groupKey): Item {
        return MartianEnchantItemFactory::create('secret-dust', [
            'group-name' => $groupName,
            'group-color' => $groupColor,
            'group' => $groupKey,
        ]);
    }

    public static function mysteryDust(string $groupName, string $groupColor, int $percent, string $groupKey): Item {
        return MartianEnchantItemFactory::create('mystery-dust', [
            'group-name' => $groupName,
            'group-color' => $groupColor,
            'percent' => $percent,
            'group' => $groupKey,
        ]);
    }

    public static function magicDust(string $groupName, string $groupColor, int $percent, string $groupKey): Item {
        return MartianEnchantItemFactory::create('magic-dust', [
            'group-name' => $groupName,
            'group-color' => $groupColor,
            'percent' => $percent,
            'group' => $groupKey,
        ]);
    }

    public static function slotIncreaser(string $groupName, string $groupColor, int $count, string $groupKey): Item {
        return MartianEnchantItemFactory::create('slot-increaser', [
            'group-name' => $groupName,
            'group-color' => $groupColor,
            'count' => $count,
            'group' => $groupKey,
        ]);
    }

    public static function blockTrak(): Item {
        return MartianEnchantItemFactory::create('blocktrak');
    }

    public static function statTrak(): Item {
        return MartianEnchantItemFactory::create('stattrak');
    }

    public static function mobTrak(): Item {
        return MartianEnchantItemFactory::create('mobtrak');
    }


}
