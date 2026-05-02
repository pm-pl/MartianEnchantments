<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\commands;

use ecstsy\MartianEnchantments\enchantments\CustomEnchantment;
use ecstsy\MartianEnchantments\enchantments\CustomEnchantments;
use ecstsy\MartianEnchantments\enchantments\Groups;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianEnchantments\Loader;
use pocketmine\utils\TextFormat as C;

final class EnchantmentInfoPresenter {

    /**
     * @return list<string> Lines without leading color codes for {@see C::colorize}.
     */
    public static function lines(CustomEnchantment $e, bool $forMutedFormBg = false): array {
        $enchantCfg = GeneralUtils::getConfiguration(Loader::getInstance(), "enchantments.yml");
        $yaml = [];
        if ($enchantCfg !== null) {
            $raw = $enchantCfg->get(strtolower($e->getName()), []);
            $yaml = is_array($raw) ? $raw : [];
        }

        $appliesRaw = (array) ($yaml['applies'] ?? []);
        if ($appliesRaw !== []) {
            $pieces = [];
            foreach ($appliesRaw as $a) {
                $pieces[] = str_replace('_', ' ', ucfirst(trim((string) $a)));
            }
            $appliesPretty = implode(', ', $pieces);
        } else {
            $cos = $yaml['applies-to'] ?? '—';
            $appliesPretty = is_array($cos) ? implode(', ', array_map(static fn ($x): string => (string) $x, $cos)) : (string) $cos;
        }

        $color = Groups::translateGroupToColor($e->getRarity());
        $header = '&8━━━━━━━━ &dEnchantment &f' . ucfirst($e->getName()) . ' &8━━━━━━━━';

        $desc = preg_replace('/\s+/', ' ', str_replace(["\r\n", "\n"], [' ', ' '], trim($e->getDescription()))) ?? '';

        $m = $forMutedFormBg ? '&r&8' : '&7';

        return [
            $header,
            '',
            $m . 'Display:&r ' . CustomEnchantments::getEnchantmentDisplayName($e->getName(), $color) . '&r',
            $m . 'Rarity:&r ' . ($color ?: $m) . ((string) (Groups::getGroupName($e->getRarity()) ?? '')),
            $m . 'Max level:&r &f' . $e->getMaxLevel() . '&r &8(' . GeneralUtils::getRomanNumeral($e->getMaxLevel()) . '&8)',
            $m . 'Applies (config):&r &f' . $appliesPretty,
            '',
            $m . 'Description:&r',
            '&f' . $desc,
        ];
    }

    public static function asFormContent(CustomEnchantment $e): string {
        return implode("\n", array_map(static fn(string $line): string => C::colorize($line), self::lines($e, true)));
    }
}
