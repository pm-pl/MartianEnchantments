<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\utils;

/**
 * Lets one-shot effects (e.g. USE_SOUL) abort remaining effects for the same proc.
 */
final class EffectChainState {

    private static bool $abort = false;

    public static function reset(): void {
        self::$abort = false;
    }

    public static function abort(): void {
        self::$abort = true;
    }

    public static function isAborted(): bool {
        return self::$abort;
    }
}
