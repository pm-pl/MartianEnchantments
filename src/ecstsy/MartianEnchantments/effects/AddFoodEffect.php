<?php

namespace ecstsy\MartianEnchantments\effects;

use ecstsy\MartianEnchantments\utils\EffectInterface;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\entity\Entity;
use pocketmine\player\Player;

class AddFoodEffect implements EffectInterface {

    public function apply(Entity $attacker, ?Entity $victim, array $data, array $effectData, string $context, array $extraData): void
    {
        if (isset($effectData['amount'])) {
            $target = $effectData['target'] === 'victim' ? $victim : $attacker;

            if (!$target instanceof Player) {
                return;
            }

            $amount = GeneralUtils::parseRandomNumber($effectData['amount']);
            $newFoodLevel = $target->getHungerManager()->getFood() + $amount;
            $newFoodLevel = max(0, min(20, $newFoodLevel));
            
            $target->getHungerManager()->setFood($newFoodLevel);        
        }
    }
}