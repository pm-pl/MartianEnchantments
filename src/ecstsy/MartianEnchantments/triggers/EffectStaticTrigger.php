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

final class EffectStaticTrigger implements TriggerInterface {
    use TriggerHelper;

    public function execute(Entity $attacker, ?Entity $victim, array $enchantments, string $context, array $extraData = []): void {
        if (!$attacker instanceof Player) {
            return;
        }
        
        foreach ($enchantments as $enchantmentData) {
            $types = $enchantmentData['config']['type'] ?? [];
            if (!in_array("EFFECT_STATIC", array_map('strtoupper', $types), true)) {
                continue;
            }

            if (($enchantmentData['config']['applies-to'] ?? '') !== 'Armor') {
                continue;
            }
            
            $enchantmentName = strtolower((string) ($enchantmentData['name'] ?? 'unknown'));
            $level = (int) ($enchantmentData['level'] ?? 1);

            if (EnchantmentDisableManager::isEnchantmentDisabled($enchantmentName, $attacker->getName())) {
                continue;
            }

            /** @var array<string,mixed>|null $slice */
            $slice = Utils::resolveLevelSlice((array) ($enchantmentData['config']['levels'] ?? []), $level);
            if ($slice === null) {
                continue;
            }

            $vars = ['level' => $level];
            /** @var array<string,mixed> $resolved */
            $resolved = $slice;
            $resolved['cooldown'] = (int) \round(NumericExpr::evaluate($resolved['cooldown'] ?? 0, $vars, 0));

            $this->applyEffects($resolved, $attacker, null, "EFFECT_STATIC", $extraData, 100, $enchantmentName, false);
        }
    }
    
}
