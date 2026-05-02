<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\triggers;

use ecstsy\MartianEnchantments\utils\NumericExpr;
use ecstsy\MartianEnchantments\utils\Utils;
use ecstsy\MartianEnchantments\utils\managers\EnchantmentDisableManager;
use ecstsy\MartianEnchantments\utils\TriggerHelper;
use ecstsy\MartianEnchantments\utils\TriggerInterface;
use pocketmine\entity\Entity;
use pocketmine\player\Player;

final class HeldTrigger implements TriggerInterface {
    use TriggerHelper;

    public function execute(Entity $attacker, ?Entity $victim, array $enchantments, string $context, array $extraData = []): void {
        if (!$attacker instanceof Player) {
            return;
        }
        
        foreach ($enchantments as $enchantmentData) {
            $types = $enchantmentData['config']['type'] ?? [];
            if (!in_array("HELD", array_map('strtoupper', $types), true)) {
                continue;
            }
            
            $enchantmentName = strtolower((string) ($enchantmentData['name'] ?? 'unknown'));
            $level = (int) ($enchantmentData['level'] ?? 1);

            /** @var array<string,mixed> $levelConfig */
            $levelConfig = Utils::resolveLevelSlice((array) ($enchantmentData['config']['levels'] ?? []), $level) ?? [];

            $vars = ['level' => $level];
            $ctx = \array_merge($extraData, [
                'enchant-name' => $enchantmentName,
                'enchant-level' => $level,
                'chance' => (int) \max(0, \min(100, (int) \round(NumericExpr::chancePercent($levelConfig['chance'] ?? 100, $vars, 100)))),
            ]);

            if (EnchantmentDisableManager::isEnchantmentDisabled($enchantmentName, $attacker->getName())) {
                continue;
            }

            $conditionsMet = true;
            if (!empty($levelConfig['conditions'])) {
                foreach ($levelConfig['conditions'] as $condition) {
                    if (!$this->handleConditions($condition, $attacker, null, $context, $ctx)) {
                        $conditionsMet = false;
                        break;
                    }
                }
            }

            if ($conditionsMet) {
                /** @var array<string,mixed> $lvl */
                $lvl = $levelConfig;
                $lvl['cooldown'] = (int) \round(NumericExpr::evaluate($lvl['cooldown'] ?? 0, $vars, 0));

                $effectChance = (int) \max(0, \min(100, (int) ($ctx['chance'] ?? 100)));
                $this->applyEffects($lvl, $attacker, null, "HELD", $ctx, $effectChance, $enchantmentName, false);
            }
        }
    }
}
