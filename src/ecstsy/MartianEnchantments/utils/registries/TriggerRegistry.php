<?php

namespace ecstsy\MartianEnchantments\utils\registries;

use ecstsy\MartianEnchantments\utils\TriggerInterface;
use InvalidArgumentException;

class TriggerRegistry {

    private static array $triggers = [];

    public static function register(string $name, TriggerInterface $trigger): void {
        self::$triggers[$name] = $trigger;
    }

    public static function get(string $name): ?TriggerInterface {
        return self::$triggers[$name] ?? null;
    }
}