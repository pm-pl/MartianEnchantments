<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\armor;

use ecstsy\MartianEnchantments\Loader;
use pocketmine\utils\Config;

/**
 * Loads {@code plugin_data/.../armorSets/*.yml} into memory. Call {@see self::load()} on enable and after reload.
 */
final class ArmorSetRegistry {

    /** @var array<string, ArmorSetDefinition> */
    private static array $sets = [];

    public static function load(): void {
        self::$sets = [];
        $dir = Loader::getInstance()->getDataFolder() . "armorSets" . DIRECTORY_SEPARATOR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $files = glob($dir . "*.yml") ?: [];
        foreach ($files as $path) {
            $cfg = new Config($path, Config::YAML);
            $data = $cfg->getAll();
            if (!\is_array($data) || $data === []) {
                Loader::getInstance()->getLogger()->warning("Armor set file empty or unreadable: " . basename((string) $path));

                continue;
            }

            try {
                $def = self::parseDefinition($data);
                self::$sets[$def->id] = $def;
            } catch (\Throwable $e) {
                Loader::getInstance()->getLogger()->error("Armor set '" . ($data["id"] ?? basename((string) $path)) . "': " . $e->getMessage());
            }
        }

        $logger = Loader::getInstance()->getLogger();
        if (self::$sets !== []) {
            $logger->debug("Armor sets loaded: " . implode(", ", array_keys(self::$sets)));
        } elseif ($files !== []) {
            $logger->warning("Found " . \count($files) . " armorSets/*.yml file(s) but none parsed — fix YAML/errors above.");
        }
    }

    public static function reload(): void {
        self::load();
    }

    public static function get(string $id): ?ArmorSetDefinition {
        return self::$sets[strtolower($id)] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function allIds(): array {
        return array_keys(self::$sets);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function parseDefinition(array $data): ArmorSetDefinition {
        $id = strtolower(trim((string) ($data["id"] ?? "")));
        if ($id === "") {
            throw new \InvalidArgumentException("Missing 'id'.");
        }

        $display = (string) ($data["display-name"] ?? $id);
        $tier = strtoupper(trim((string) ($data["material-tier"] ?? "LEATHER")));
        if ($tier === "GOLD") {
            $tier = "GOLDEN";
        }

        $unbreakable = (bool) ($data["unbreakable"] ?? false);
        $leatherRgb = null;
        if (isset($data["leather-dye"]) && \is_string($data["leather-dye"])) {
            $parts = array_map("trim", explode(",", $data["leather-dye"]));
            if (\count($parts) === 3) {
                $leatherRgb = [
                    max(0, min(255, (int) $parts[0])),
                    max(0, min(255, (int) $parts[1])),
                    max(0, min(255, (int) $parts[2])),
                ];
            }
        }

        $piecesIn = $data["pieces"] ?? null;
        if (!\is_array($piecesIn)) {
            throw new \InvalidArgumentException("Missing 'pieces' section.");
        }

        $pieces = [];
        foreach (["helmet", "chestplate", "leggings", "boots"] as $slot) {
            $row = $piecesIn[$slot] ?? null;
            if (!\is_array($row)) {
                throw new \InvalidArgumentException("Missing piece definition for '{$slot}'.");
            }

            $pieceId = trim((string) ($row["id"] ?? ""));
            if ($pieceId === "") {
                throw new \InvalidArgumentException("pieces.{$slot}.id is required.");
            }

            $pieces[$slot] = $row;
        }

        $weaponsIn = $data["weapons"] ?? [];
        $weapons = \is_array($weaponsIn) ? $weaponsIn : [];

        $effectsIn = $data["effects"] ?? [];
        if (!\is_array($effectsIn)) {
            $effectsIn = [];
        }

        $attacker = self::normalizeEffectList($effectsIn["attacker-full-set"] ?? []);
        $defender = self::normalizeEffectList($effectsIn["defender-full-set"] ?? []);

        $weaponBonuses = [];
        $wb = $effectsIn["weapon-bonuses"] ?? [];
        if (\is_array($wb)) {
            foreach ($wb as $weaponKey => $list) {
                $wk = strtolower(trim((string) $weaponKey));
                if ($wk === "") {
                    continue;
                }
                $weaponBonuses[$wk] = self::normalizeEffectList(\is_array($list) ? $list : []);
            }
        }

        return new ArmorSetDefinition(
            $id,
            $display,
            $tier,
            $unbreakable,
            $leatherRgb,
            $pieces,
            $weapons,
            $attacker,
            $defender,
            $weaponBonuses
        );
    }

    /**
     * @param mixed $raw
     * @return list<array{type: string, amount: float}>
     */
    private static function normalizeEffectList(mixed $raw): array {
        if (!\is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $type = strtolower(trim((string) ($row["type"] ?? "")));
            if ($type === "") {
                continue;
            }
            $amount = (float) ($row["amount"] ?? 0);
            $out[] = ["type" => $type, "amount" => $amount];
        }

        return $out;
    }
}
