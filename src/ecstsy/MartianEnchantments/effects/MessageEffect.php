<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\effects;

use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianEnchantments\utils\EffectChainState;
use ecstsy\MartianEnchantments\utils\EffectInterface;
use ecstsy\MartianEnchantments\utils\Utils;
use pocketmine\entity\Entity;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

/**
 * Sends a chat line to attacker or victim. Skipped automatically when USE_SOUL aborts earlier in the chain.
 */
final class MessageEffect implements EffectInterface {

    public function apply(Entity $attacker, ?Entity $victim, array $data, array $effectData, string $context, array $extraData): void {
        if (EffectChainState::isAborted()) {
            return;
        }

        $target = (($effectData['target'] ?? 'attacker') === 'victim') ? $victim : $attacker;

        if (!$target instanceof Player) {
            return;
        }

        $effectType = (string) ($effectData['type'] ?? 'message');
        $enchantName = (string) ($extraData['enchant-name'] ?? '');

        if (!isset($effectData['text']) || trim((string) $effectData['text']) === '') {
            Utils::sendError($target, "Missing non-empty 'text' for effect '$effectType'", [
                'effect' => $effectType,
                'enchant-name' => $enchantName,
            ]);

            return;
        }

        $text = GeneralUtils::parseDynamicMessage((string) $effectData['text']);
        $attackerName = $attacker instanceof Player ? $attacker->getName() : 'unknown';
        $victimName = $victim instanceof Player ? $victim->getName() : 'unknown';

        $target->sendMessage(C::colorize(str_replace(["{attacker}", "{victim}"], [$attackerName, $victimName], $text)));
    }
}
