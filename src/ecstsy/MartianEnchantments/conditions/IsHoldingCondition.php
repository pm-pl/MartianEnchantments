<?php

namespace ecstsy\MartianEnchantments\conditions;

use ecstsy\MartianEnchantments\utils\ConditionInterface;
use pocketmine\entity\Entity;
use pocketmine\item\ItemTypeIds;
use pocketmine\player\Player;

class IsHoldingCondition implements ConditionInterface {

    public function check(Entity $attacker, ?Entity $victim, array $conditionData, string $context, array $extraData): bool
    {

        $target = $conditionData['target'] === 'victim' ? $victim : $attacker;
    
        if (!$target instanceof Player) {
            return false; 
        }
    

        $itemInHand = $target->getInventory()->getItemInHand();
    
        $requiredItem = $conditionData['value'];
    
        $validItems = [
            'sword' => [ItemTypeIds::WOODEN_SWORD, ItemTypeIds::STONE_SWORD, ItemTypeIds::IRON_SWORD, ItemTypeIds::GOLDEN_SWORD, ItemTypeIds::DIAMOND_SWORD, ItemTypeIds::NETHERITE_SWORD],
            'axe' => [ItemTypeIds::WOODEN_AXE, ItemTypeIds::STONE_AXE, ItemTypeIds::IRON_AXE, ItemTypeIds::GOLDEN_AXE, ItemTypeIds::DIAMOND_AXE, ItemTypeIds::NETHERITE_AXE],
            'bow' => [ItemTypeIds::BOW],
            'pickaxe' => [ItemTypeIds::WOODEN_PICKAXE, ItemTypeIds::STONE_PICKAXE, ItemTypeIds::IRON_PICKAXE, ItemTypeIds::GOLDEN_PICKAXE, ItemTypeIds::DIAMOND_PICKAXE, ItemTypeIds::NETHERITE_PICKAXE],
            'shovel' => [ItemTypeIds::WOODEN_SHOVEL, ItemTypeIds::STONE_SHOVEL, ItemTypeIds::IRON_SHOVEL, ItemTypeIds::GOLDEN_SHOVEL, ItemTypeIds::DIAMOND_SHOVEL, ItemTypeIds::NETHERITE_SHOVEL],
        ];
        
        if (isset($validItems[$requiredItem])) {
            if (in_array($itemInHand->getTypeId(), $validItems[$requiredItem])) {
                return true;
            }
        }
    
        return false;
    }
}
