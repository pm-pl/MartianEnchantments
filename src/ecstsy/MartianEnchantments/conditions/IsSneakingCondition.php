<?php

namespace ecstsy\MartianEnchantments\conditions;

use ecstsy\MartianEnchantments\utils\ConditionInterface;
use pocketmine\entity\Entity;
use pocketmine\player\Player;

class IsSneakingCondition implements ConditionInterface {

    public function check(Entity $attacker, ?Entity $victim, array $conditionData, string $context, array $extraData): bool
    {

        $target = $conditionData['target'] === 'victim' ? $victim : $attacker;
    
        if (!$target instanceof Player) {
            return false; 
        }
        
        if ($target instanceof Player) {
            return $target->isSneaking();  
        }

        return false;
    }
}