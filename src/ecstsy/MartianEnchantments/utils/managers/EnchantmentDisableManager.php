<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\utils\managers;

class EnchantmentDisableManager {
    /** @var array<string, array<string, int>> player name → enchant id → unix time when re-enabled */
    private static array $disabledEnchantments = [];

    /**
     * Check if an enchantment is currently disabled for a player.
     */
    public static function isEnchantmentDisabled(string $enchantmentId, string $playerName): bool {
        if (!isset(self::$disabledEnchantments[$playerName][$enchantmentId])) {
            return false;
        }
        $until = self::$disabledEnchantments[$playerName][$enchantmentId];
        if ($until <= time()) {
            unset(self::$disabledEnchantments[$playerName][$enchantmentId]);
            if (self::$disabledEnchantments[$playerName] === []) {
                unset(self::$disabledEnchantments[$playerName]);
            }

            return false;
        }

        return true;
    }

    /**
     * Get the time until an enchantment is re-enabled for a player.
     */
    public static function getDisabledUntilTime(string $enchantmentId, string $playerName): int {
        if (!isset(self::$disabledEnchantments[$playerName][$enchantmentId])) {
            return 0;
        }
        $until = self::$disabledEnchantments[$playerName][$enchantmentId];
        if ($until <= time()) {
            unset(self::$disabledEnchantments[$playerName][$enchantmentId]);
            if (self::$disabledEnchantments[$playerName] === []) {
                unset(self::$disabledEnchantments[$playerName]);
            }

            return 0;
        }

        return $until;
    }

    /**
     * Remove the disabled state of an enchantment for a player.
     */
    public static function removeDisableState(string $enchantmentId, string $playerName): void {
        unset(self::$disabledEnchantments[$playerName][$enchantmentId]);
        if (isset(self::$disabledEnchantments[$playerName]) && self::$disabledEnchantments[$playerName] === []) {
            unset(self::$disabledEnchantments[$playerName]);
        }
    }

    /**
     * Disable an enchantment for a player for a specified duration.
     */
    public static function disableEnchantmentForPlayer(string $enchantmentId, string $playerName, int $duration): void {
        self::$disabledEnchantments[$playerName][$enchantmentId] = time() + $duration;
    }

    public static function clearPlayer(string $playerName): void {
        unset(self::$disabledEnchantments[$playerName]);
    }
}