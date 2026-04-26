<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\utils;

use pocketmine\player\Player;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\Server;

final class EffectTracker {

    /** @var array<string, array<string, array<string, array<string, EffectInstance>>>> */
    private static array $playerEffects = [];

    public static function addEffect(Player $player, EffectInstance $effect, string $enchantmentName, ?int $slot = null): void {
        $playerName = $player->getName();
        $effectName = Server::getInstance()->getLanguage()->translate($effect->getType()->getName());
        $slotKey = self::getSlotKey($slot);

        self::$playerEffects[$playerName][$slotKey][$enchantmentName][$effectName] = $effect;
        $player->getEffects()->add($effect);
    }

    public static function removeEnchantmentEffects(Player $player, string $enchantmentName): void {
        $playerName = $player->getName();
        if (!isset(self::$playerEffects[$playerName])) {
            return;
        }

        foreach (self::$playerEffects[$playerName] as $slotKey => $enchantmentsByName) {
            if (!isset($enchantmentsByName[$enchantmentName])) {
                continue;
            }

            foreach ($enchantmentsByName[$enchantmentName] as $effect) {
                $player->getEffects()->remove($effect->getType());
            }

            unset(self::$playerEffects[$playerName][$slotKey][$enchantmentName]);

            if (empty(self::$playerEffects[$playerName][$slotKey])) {
                unset(self::$playerEffects[$playerName][$slotKey]);
            }
        }

        if (empty(self::$playerEffects[$playerName])) {
            unset(self::$playerEffects[$playerName]);
        }
    }

    public static function clearSlotEffects(Player $player, int $slot): void {
        $playerName = $player->getName();
        $slotKey = self::getSlotKey($slot);

        if (!isset(self::$playerEffects[$playerName][$slotKey])) {
            return;
        }

        foreach (self::$playerEffects[$playerName][$slotKey] as $effectsByName) {
            foreach ($effectsByName as $effect) {
                $player->getEffects()->remove($effect->getType());
            }
        }

        unset(self::$playerEffects[$playerName][$slotKey]);

        if (empty(self::$playerEffects[$playerName])) {
            unset(self::$playerEffects[$playerName]);
        }
    }

    public static function clearPlayerEffects(Player $player): void {
        $playerName = $player->getName();

        if (!isset(self::$playerEffects[$playerName])) {
            return;
        }

        foreach (self::$playerEffects[$playerName] as $effectsByEnchantment) {
            foreach ($effectsByEnchantment as $effectsByName) {
                foreach ($effectsByName as $effect) {
                    $player->getEffects()->remove($effect->getType());
                }
            }
        }

        unset(self::$playerEffects[$playerName]);
    }

    public static function hasEffect(Player $player, string $enchantmentName, string $effectName): bool {
        $playerName = $player->getName();
        if (!isset(self::$playerEffects[$playerName])) {
            return false;
        }

        foreach (self::$playerEffects[$playerName] as $effectsByEnchantment) {
            if (isset($effectsByEnchantment[$enchantmentName][$effectName])) {
                return true;
            }
        }

        return false;
    }

    private static function getSlotKey(?int $slot): string {
        return $slot === null ? 'global' : (string) $slot;
    }
}
