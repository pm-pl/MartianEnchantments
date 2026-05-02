<?php

namespace ecstsy\MartianEnchantments\utils\registries;

use ecstsy\MartianEnchantments\utils\ConditionInterface;

class ConditionRegistry {

    private static array $conditions = [];

    public static function register(string $name, ConditionInterface $condition): void {
        self::$conditions[$name] = $condition;
    }

    public static function get(string $name): ?ConditionInterface {
        return self::$conditions[$name] ?? null;
    }
}