<?php

namespace ecstsy\MartianEnchantments\effects;

use ecstsy\MartianEnchantments\utils\EffectInterface;
use ecstsy\MartianEnchantments\utils\EffectTracker;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\entity\Entity;
use pocketmine\player\Player;

class AddPotionEffect implements EffectInterface {

    public function apply(Entity $attacker, ?Entity $victim, array $data, array $effectData, string $context, array $extraData): void
    {
        $target = $effectData['target'] === 'victim' ? $victim : $attacker;
    
        if (!$target instanceof Player) {
            return; 
        }

        $potion = StringToEffectParser::getInstance()->parse($effectData['potion'] ?? '');
        
        if ($potion === null) return;

        $amplifier = (int) ($effectData['amplifier'] ?? 0);
        $duration = (int) ($effectData['duration'] ?? 2147483647);
        $effect = new EffectInstance($potion, $duration, $amplifier);

        $enchantmentName = $extraData['enchant-name'] ?? 'unknown';
        $slot = isset($extraData['slot']) ? (int) $extraData['slot'] : null;

        EffectTracker::addEffect($target, $effect, $enchantmentName, $slot);
    }
}
