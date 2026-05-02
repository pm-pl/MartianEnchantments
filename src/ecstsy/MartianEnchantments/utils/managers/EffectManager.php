<?php

namespace ecstsy\MartianEnchantments\utils\managers;

use ecstsy\MartianEnchantments\effects\ActionBarEffect;
use ecstsy\MartianEnchantments\effects\AddAirEffect;
use ecstsy\MartianEnchantments\effects\AddFoodEffect;
use ecstsy\MartianEnchantments\effects\AddHealthEffect;
use ecstsy\MartianEnchantments\effects\AddPotionEffect;
use ecstsy\MartianEnchantments\effects\BloodEffect;
use ecstsy\MartianEnchantments\effects\BurnEffect;
use ecstsy\MartianEnchantments\effects\DisableActivationEffect;
use ecstsy\MartianEnchantments\effects\StealHealthEffect;
use ecstsy\MartianEnchantments\effects\MessageEffect;
use ecstsy\MartianEnchantments\effects\UseSoulEffect;

class EffectManager {

    private static $effectMap = [
        "action_bar" => ActionBarEffect::class,
        "message" => MessageEffect::class,
        "use_soul" => UseSoulEffect::class,
        "add_potion" => AddPotionEffect::class,
        "add_air" => AddAirEffect::class,
        "add_food" => AddFoodEffect::class,
        "add_health" => AddHealthEffect::class,
        "blood" => BloodEffect::class,
        "burn" => BurnEffect::class,
        "disable_activation" => DisableActivationEffect::class,
        "steal_health" => StealHealthEffect::class,
        
    ];

    public static function getEffectClass(string $effectType): ?string {
        return self::$effectMap[$effectType] ?? null;
    }
}