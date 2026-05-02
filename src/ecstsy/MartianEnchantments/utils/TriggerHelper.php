<?php

namespace ecstsy\MartianEnchantments\utils;

use ecstsy\MartianEnchantments\utils\EffectChainState;
use ecstsy\MartianEnchantments\utils\EffectInterface;
use ecstsy\MartianEnchantments\utils\managers\CooldownManager;
use ecstsy\MartianEnchantments\utils\managers\EffectManager;
use ecstsy\MartianEnchantments\utils\registries\ConditionRegistry;
use pocketmine\entity\Entity;
use pocketmine\player\Player;

trait TriggerHelper {

    public function handleConditions(array $condition, Entity $attacker, ?Entity $victim, string $context, array &$extraData): bool {
        $target = $condition['target'] === 'victim' ? $victim : $attacker;
    
        if (!$target instanceof Player) {
            return false; 
        }

        $conditionType = $condition['type'] ?? '';
        $conditionMode = strtolower($condition['condition_mode'] ?? 'allow');
        $conditionHandler = ConditionRegistry::get($conditionType);

        if ($conditionHandler === null) {
            throw new \RuntimeException("Unknown condition type: $conditionType");
        }

        $result = $conditionHandler->check($attacker, $victim, $condition, $context, $extraData);

        switch ($conditionMode) {
            case 'force':
                if ($result) {
                    return true; 
                }
                break;

            case 'allow':
                if (!$result) {
                    return false; 
                }
                break;

            case 'continue':
                return true; 
                break;

            case 'stop':
                if ($result) {
                    return false; 
                }
                break;

            case 'chance':
                if ($result) {
                    $chanceAdjustment = $condition['chance'] ?? 0;
                    $extraData['chance'] = ($extraData['chance'] ?? 100) + $chanceAdjustment;
                }
                break;

            default:
                throw new \InvalidArgumentException("Unknown condition mode: $conditionMode");
        }

        return true;
    }   

    /**
     * Applies an array of effects to a target entity.
     *
     * @param array               $effects         Array of effect configurations.
     * @param Entity              $caster          The entity applying the effect.
     * @param Entity|null         $target          The entity to apply the effect to.
     * @param string              $triggerContext  The context of the effect trigger.
     * @param array               $additionalData  Additional data to pass to the effect.
     * @param int                 $effectChance    Activation chance for the entire effect chain (default: 100).
     *                                                Rolled once per proc — not independently per effect row.
     * @param string              $enchantmentId   The ID of the enchantment that triggered the effect.
     * @param bool                $forceMode       Force mode for the effect.
     *
     * @return void
     */
    public function applyEffects(array $effects, Entity $caster, ?Entity $target, string $triggerContext, array $additionalData = [], int $effectChance = 100, string $enchantmentId = '', bool $forceMode = false): void {
        $effectCooldown = $effects['cooldown'] ?? 0;

        if (!$forceMode && $caster instanceof Player && CooldownManager::isOnCooldown($caster, $enchantmentId)) {
            return; 
        }

        $effectsList = (array) ($effects['effects'] ?? []);
        if ($effectsList === []) {
            return;
        }

        if (!$forceMode && $effectChance > 0 && $effectChance < 100) {
            if (mt_rand(1, 100) > $effectChance) {
                return;
            }
        }

        EffectChainState::reset();

        foreach ($effectsList as $effectConfig) {
            if (EffectChainState::isAborted()) {
                break;
            }
            if (!is_array($effectConfig)) {
                continue;
            }

            $effectType = strtolower((string) ($effectConfig['type'] ?? ''));
            $effectClass = EffectManager::getEffectClass($effectType);

            if (!$effectClass || !class_exists($effectClass)) {
                continue;
            }

            /** @var EffectInterface $effect */
            $effect = new $effectClass();
            $effect->apply($caster, $target, $effectConfig, $effectConfig, $triggerContext, $additionalData);

            if (EffectChainState::isAborted()) {
                break;
            }
        }

        if (!EffectChainState::isAborted() && !$forceMode && $effectCooldown > 0 && $caster instanceof Player) {
            CooldownManager::setCooldown($caster, $enchantmentId, $effectCooldown);
        }
    }
}