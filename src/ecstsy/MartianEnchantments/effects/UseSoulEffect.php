<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\effects;

use ecstsy\MartianEnchantments\enchantments\CustomEnchantments;
use ecstsy\MartianEnchantments\enchantments\Groups;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianEnchantments\Loader;
use ecstsy\MartianEnchantments\utils\EffectChainState;
use ecstsy\MartianEnchantments\utils\EffectInterface;
use ecstsy\MartianEnchantments\utils\SoulGemSessionManager;
use pocketmine\entity\Entity;
use pocketmine\player\Player;

/**
 * Pays soul cost from an active Soul Gem session. Put first in {@code effects:} when an enchant requires souls.
 * Optional gem-vs-book group lock: settings.soulsgem.require-matching-book-group (default false).
 */
final class UseSoulEffect implements EffectInterface {

    public function apply(Entity $attacker, ?Entity $victim, array $data, array $effectData, string $context, array $extraData): void {
        $tar = strtolower((string) ($effectData['target'] ?? 'attacker'));
        $player = ($tar === 'victim') ? ($victim instanceof Player ? $victim : null) : ($attacker instanceof Player ? $attacker : null);
        if ($player === null) {
            EffectChainState::abort();

            return;
        }

        $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");
        if ($cfg !== null && !(bool) $cfg->getNested("settings.souls.enabled", true)) {
            EffectChainState::abort();

            return;
        }

        $amount = (int) ($effectData['amount'] ?? 1);
        if ($amount < 1) {
            $amount = 1;
        }

        $enchKey = strtolower((string) ($extraData['enchant-name'] ?? ''));
        $ce = CustomEnchantments::getEnchantmentByName($enchKey);
        if ($ce === null) {
            EffectChainState::abort();

            return;
        }

        $groupKey = strtoupper((string) (Groups::getGroupNameById($ce->getRarity()) ?? Groups::getFallbackGroup()));

        if (!SoulGemSessionManager::tryConsumeSouls($player, $amount, $groupKey)) {
            EffectChainState::abort();
        }
    }
}
