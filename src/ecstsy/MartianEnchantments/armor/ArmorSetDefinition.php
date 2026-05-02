<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\armor;

/**
 * Parsed armor set — pieces, weapons, and effect lists from YAML.
 *
 * @phpstan-type EnchantRow array{enchant: string, level: int}
 * @phpstan-type CustomEnchantRow array{name: string, level: int}
 * @phpstan-type EffectRow array{type: string, amount: float}
 */
final class ArmorSetDefinition {

    /**
     * @param array<string, array<string, mixed>> $pieces helmet|chestplate|leggings|boots
     * @param array<string, array<string, mixed>> $weapons weaponKey => piece config
     * @param list<EffectRow> $attackerFullSet
     * @param list<EffectRow> $defenderFullSet
     * @param array<string, list<EffectRow>> $weaponBonuses martianItem key => effects
     */
    public function __construct(
        public readonly string $id,
        public readonly string $displayName,
        public readonly string $materialTier,
        public readonly bool $unbreakable,
        public readonly ?array $leatherRgb,
        public readonly array $pieces,
        public readonly array $weapons,
        public readonly array $attackerFullSet,
        public readonly array $defenderFullSet,
        public readonly array $weaponBonuses,
    ) {
    }

    public function expectedPieceId(string $armorSlot): ?string {
        $slot = strtolower($armorSlot);
        $p = $this->pieces[$slot] ?? null;

        return \is_array($p) ? (isset($p["id"]) ? (string) $p["id"] : null) : null;
    }

    /**
     * @return list<string>
     */
    public function armorSlots(): array {
        return ["helmet", "chestplate", "leggings", "boots"];
    }
}
