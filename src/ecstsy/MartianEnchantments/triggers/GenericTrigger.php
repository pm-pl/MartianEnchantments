<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\triggers;

use ecstsy\MartianEnchantments\utils\NumericExpr;
use ecstsy\MartianEnchantments\utils\TriggerHelper;
use ecstsy\MartianEnchantments\utils\TriggerInterface;
use ecstsy\MartianEnchantments\utils\Utils;
use ecstsy\MartianEnchantments\utils\managers\CooldownManager;
use ecstsy\MartianEnchantments\utils\managers\EnchantmentDisableManager;
use pocketmine\entity\Entity;
use pocketmine\player\Player;

final class GenericTrigger implements TriggerInterface {
    use TriggerHelper;

    public function execute(Entity $attacker, ?Entity $victim, array $enchantments, string $context, array $extraData = []): void {
        if ($victim === null) {
            $victim = $attacker;
        }

        foreach ($enchantments as $enchantmentData) {
            $rootCfg = isset($enchantmentData['config']) && \is_array($enchantmentData['config'])
                ? $enchantmentData['config']
                : [];
            $types = array_map('strtoupper', (array) ($rootCfg['type'] ?? []));

            if (\in_array("HELD", $types, true) || \in_array("EFFECT_STATIC", $types, true)) {
                continue;
            }

            $level = (int) ($enchantmentData['level'] ?? 1);

            /** @var array<string,mixed> $config */
            $config = $rootCfg;
            $levelConfig = Utils::resolveLevelSlice((array) ($config['levels'] ?? []), $level);

            if ($levelConfig === null) {
                continue;
            }

            $vars = ['level' => $level];
            $baseChancePct = NumericExpr::chancePercent($levelConfig['chance'] ?? 100, $vars, 100.0);

            $enchantmentName = strtolower((string) ($enchantmentData['name'] ?? 'unknown'));

            $localExtra = $extraData;
            $localExtra['enchant-name'] = $enchantmentName;
            $localExtra['enchant-level'] = $level;
            $localExtra['chance'] = (int) \max(0, \min(100, (int) \round($baseChancePct)));

            $conditionsMet = true;
            $forceTriggered = false;

            if ($attacker instanceof Player && EnchantmentDisableManager::isEnchantmentDisabled($enchantmentName, $attacker->getName())) {
                $disabledUntil = EnchantmentDisableManager::getDisabledUntilTime($enchantmentName, $attacker->getName());
                if ($disabledUntil > \time()) {
                    continue;
                }

                EnchantmentDisableManager::removeDisableState($enchantmentName, $attacker->getName());
            }

            if ($victim instanceof Player && EnchantmentDisableManager::isEnchantmentDisabled($enchantmentName, $victim->getName())) {
                $disabledUntil = EnchantmentDisableManager::getDisabledUntilTime($enchantmentName, $victim->getName());
                if ($disabledUntil > \time()) {
                    continue;
                }

                EnchantmentDisableManager::removeDisableState($enchantmentName, $victim->getName());
            }

            if (!empty($levelConfig['conditions'])) {
                foreach ((array) $levelConfig['conditions'] as $condition) {
                    if (!$this->handleConditions(\is_array($condition) ? $condition : [], $attacker, $victim, $context, $localExtra)) {
                        $conditionsMet = false;
                        break;
                    }
                }
            }

            if (!$conditionsMet && !$forceTriggered) {
                continue;
            }

            /** @var array<string,mixed> $levelCfgForEffects */
            $levelCfgForEffects = $levelConfig;
            $levelCfgForEffects['cooldown'] = (int) \round(NumericExpr::evaluate($levelCfgForEffects['cooldown'] ?? 0, $vars, 0.0));

            $adjustedChance = (int) \max(0, \min(100, (int) ($localExtra['chance'] ?? $baseChancePct)));

            $cooldownSecs = (int) $levelCfgForEffects['cooldown'];

            if (!$forceTriggered && $cooldownSecs > 0 && CooldownManager::isOnCooldown($attacker, $enchantmentName)) {
                continue;
            }

            if ($forceTriggered) {
                $this->applyEffects($levelCfgForEffects, $attacker, $victim, $context, $localExtra, $adjustedChance, $enchantmentName, true);
            } else {
                $this->applyEffects($levelCfgForEffects, $attacker, $victim, $context, $localExtra, $adjustedChance, $enchantmentName, false);
            }
        }
    }
}
