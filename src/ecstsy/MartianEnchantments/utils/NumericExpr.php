<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\utils;

use muqsit\arithmexp\Parser;
use muqsit\arithmexp\expression\Expression;
use Throwable;
use function class_exists;
use function is_numeric;
use function round;
use function trim;

/**
 * Resolves YAML numbers or {@link https://github.com/Muqsit/arithmexp arithmexp} strings ({@code level} variable, etc.).
 */
final class NumericExpr {

    private static ?Parser $_parser = null;

    /** @var array<string, Expression> */
    private static array $compiled = [];

    private static function parser(): ?Parser {
        if (!class_exists(Parser::class)) {
            return null;
        }

        return self::$_parser ??= Parser::createDefault();
    }

    /**
     * @param array<string, int|float|bool|string> $vars Substitution map (e.g. {@code ["level" => 3]}).
     */
    public static function evaluate(mixed $raw, array $vars, float $default = 0.0): float {
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }

        if (!is_string($raw)) {
            return $default;
        }

        $s = trim($raw);
        if ($s === "") {
            return $default;
        }

        if (is_numeric($s)) {
            return (float) $s;
        }

        $parser = self::parser();
        if ($parser === null) {
            return $default;
        }

        try {
            $expr = self::$compiled[$s] ??= $parser->parse($s);
            $out = $expr->evaluate($vars);
            if (is_bool($out)) {
                return $out ? 1.0 : 0.0;
            }
            if (is_int($out) || is_float($out)) {
                return (float) $out;
            }

            return $default;
        } catch (Throwable) {
            return $default;
        }
    }

    /**
     * @param array<string, int|float|bool|string> $vars
     */
    public static function chancePercent(mixed $raw, array $vars, float $defaultPercent = 100.0): float {
        $v = self::evaluate($raw, $vars, $defaultPercent);
        if ($v < 0.0) {
            return 0.0;
        }
        if ($v > 100.0) {
            return 100.0;
        }

        return $v;
    }
}
