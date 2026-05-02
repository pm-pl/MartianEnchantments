<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\utils\managers;

use pocketmine\entity\Entity;

class CooldownManager {

    /** @var array<int, array<string, int>> entity runtime id → enchant id → unix end time */
    private static array $cooldowns = [];

    /**
     * Checks if the enchantment is on cooldown for the given entity.
     *
     * @return int Remaining cooldown time in seconds, or 0 if no cooldown is active.
     */
    public static function getRemainingCooldown(Entity $entity, string $enchantmentId): int
    {
        $entityId = $entity->getId();
        if (!isset(self::$cooldowns[$entityId][$enchantmentId])) {
            return 0;
        }

        $cooldownEnd = self::$cooldowns[$entityId][$enchantmentId];
        $currentTime = time();
        if ($currentTime < $cooldownEnd) {
            return $cooldownEnd - $currentTime;
        }

        unset(self::$cooldowns[$entityId][$enchantmentId]);
        if (self::$cooldowns[$entityId] === []) {
            unset(self::$cooldowns[$entityId]);
        }

        return 0;
    }

    /**
     * Checks if the enchantment is on cooldown.
     *
     * @param Entity $entity
     * @param string $enchantmentId
     * @return bool True if the enchantment is on cooldown, false otherwise.
     */
    public static function isOnCooldown(Entity $entity, string $enchantmentId): bool
    {
        return self::getRemainingCooldown($entity, $enchantmentId) > 0;
    }

    /**
     * Sets a cooldown for the enchantment for a specific entity.
     *
     * @param Entity $entity
     * @param string $enchantmentId
     * @param int $cooldown Duration of the cooldown in seconds.
     */
    public static function setCooldown(Entity $entity, string $enchantmentId, int $cooldown): void
    {
        $cooldownEnd = time() + $cooldown;
        self::$cooldowns[$entity->getId()][$enchantmentId] = $cooldownEnd;
    }

    public static function clearEntityCooldowns(Entity $entity): void
    {
        unset(self::$cooldowns[$entity->getId()]);
    }
}
