<?php

namespace ecstsy\MartianEnchantments\effects;

use ecstsy\MartianEnchantments\utils\EffectInterface;
use ecstsy\MartianEnchantments\utils\Utils;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\player\Player;

class StealHealthEffect implements EffectInterface {

    public function apply(Entity $attacker, ?Entity $victim, array $data, array $effectData, string $context, array $extraData): void
    {
        $target = $effectData['target'] === 'victim' ? $victim : $attacker;
        
        if (!$target instanceof Living) {
            return; 
        }
        
        $effectType = $effectData['type'] ?? 'unknown';
        $enchantName = $extraData['enchant-name'];
        $errorMessages = [];
        
        if (!isset($effectData['amount'])) {
            $errorMessages[] = "Missing 'amount' key under effect type '{$effectType}' in enchantment '{$enchantName}'.";
        }

        if (!isset($effectData['target'])) {
            $errorMessages[] = "Missing 'target' key under effect type '{$effectType}' in enchantment '{$enchantName}'.";
        }
        
        if (!empty($errorMessages)) {
            $contextInfo = [
                "effect" => $effectType,
                'enchant-name' => $enchantName
            ];

            if ($attacker instanceof Player) {
                foreach ($errorMessages as $message) {
                    Utils::sendError($attacker, $message, $contextInfo);
                }
            }

            if ($victim instanceof Player) {
                foreach ($errorMessages as $message) {
                    Utils::sendError($victim, $message, $contextInfo);
                }
            }

            return;
        }

        $amount = GeneralUtils::parseRandomNumber($effectData['amount']);

        if ($victim !== null && $victim instanceof Living) {
            $currentVictimHealth = $victim->getHealth();
            $healthToSteal = min($amount, $currentVictimHealth); 

            $victim->setHealth($currentVictimHealth - $healthToSteal);
        } else {
            $healthToSteal = 0; 
        }

        $currentTargetHealth = $target->getHealth();
        $maxTargetHealth = $target->getMaxHealth();
        $newTargetHealth = min($maxTargetHealth, $currentTargetHealth + $healthToSteal); 

        $target->setHealth($newTargetHealth);
    }
}
