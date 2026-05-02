<?php

namespace ecstsy\MartianEnchantments\effects;

use ecstsy\MartianEnchantments\utils\EffectInterface;
use ecstsy\MartianEnchantments\utils\Utils;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\utils\TextFormat as C;
use pocketmine\entity\Entity;
use pocketmine\player\Player;

class ActionBarEffect  implements EffectInterface {

    public function apply(Entity $attacker, ?Entity $victim, array $data, array $effectData, string $context, array $extraData): void
    {
        $target = $effectData['target'] === 'victim' ? $victim : $attacker;
    
        if (!$target instanceof Player) {
            return; 
        }

        $effectType = $effectData['type'] ?? 'unknown';
        $enchantName = $extraData['enchant-name'];
        $errorMessages = [];
        
        if (!isset($effectData['text'])) {
            $errorMessages[] = "Missing 'text' key under effect type '{$effectType}' in enchantment '{$enchantName}'.";
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

        $text = GeneralUtils::parseDynamicMessage($effectData['text']);
        $attackerName = $attacker instanceof Player ? $attacker->getName() : 'unknown';
        $victimName = $victim instanceof Player ? $victim->getName() : 'unknown';
        
        $finalMessage = str_replace(["{attacker}", "{victim}"], [$attackerName, $victimName], $text);

        $target->sendActionBarMessage(C::colorize($finalMessage));    
    }
}