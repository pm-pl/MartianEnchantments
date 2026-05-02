<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\armor;

/**
 * Passive armor-set effect identifiers (parsed from armorSets/*.yml).
 *
 * Extensible: unknown types are ignored at runtime until implemented.
 */
final class ArmorSetEffectType {

    public const OUTGOING_DAMAGE_BONUS = "outgoing_damage_bonus";
    /** Defender takes {@code (1 - amount)} of incoming damage per entry (multiplicative). */
    public const INCOMING_DAMAGE_REDUCTION = "incoming_damage_reduction";
    /** When attacking a player, damages each worn armor piece's durability by {@code amount}. */
    public const VICTIM_ARMOR_DURABILITY_DAMAGE = "victim_armor_durability_damage";
}
