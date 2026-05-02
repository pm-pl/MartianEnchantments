<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\server\items;

use ecstsy\MartianEnchantments\enchantments\CustomEnchantment;
use ecstsy\MartianEnchantments\enchantments\CustomEnchantments;
use ecstsy\MartianEnchantments\enchantments\Groups;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianEnchantments\Loader;
use ecstsy\MartianEnchantments\utils\Utils;
use pocketmine\item\Item;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

/** Builds enchantment books: base item + config-driven display from enchantments.yml + config.yml. */
final class MartianEnchantmentBooks {

    private const FILE_CONFIG = "config.yml";
    private const FILE_ENCHANTMENTS = "enchantments.yml";

    private const NESTED_CHANCES = "settings.chances";
    private const NESTED_ENCHANTMENT_BOOK = "settings.enchantment-book";

    public static function create(CustomEnchantment $enchantment, int $level = 1, ?int $forcedSuccessChance = null, ?int $forcedDestroyChance = null): ?Item {
        $mainConfig = self::mainConfig();
        if ($mainConfig === null) {
            return null;
        }

        $bookConfig = (array) $mainConfig->getNested(self::NESTED_ENCHANTMENT_BOOK, []);
        $chancesConfig = (array) $mainConfig->getNested(self::NESTED_CHANCES, []);

        $enchantName = $enchantment->getName();
        $enchantData = self::yamlDataForEnchant($enchantName);

        $rarity = $enchantment->getRarity();
        $color = Groups::translateGroupToColor($rarity);
        $roman = GeneralUtils::getRomanNumeral($level);

        [$successChance, $destroyChance] = Utils::resolveBookChances(
            $chancesConfig,
            $forcedSuccessChance,
            $forcedDestroyChance
        );

        try {
            $item = MartianEnchantItems::enchantmentBook($enchantment, $level, $successChance, $destroyChance);
        } catch (\Throwable $e) {
            Loader::getInstance()->getLogger()->error("Enchantment book: " . $e->getMessage());

            return null;
        }

        $displayShort = CustomEnchantments::stripDisplayOrnaments(str_replace(
            "{group-color}",
            $color,
            $enchantData["display"] ?? ucfirst($enchantName)
        ));

        $placeholders = self::placeholderMap(
            $enchantment,
            $enchantData,
            $level,
            $color,
            $roman,
            (string) $displayShort,
            $successChance,
            $destroyChance
        );

        $nameTpl = (string) ($bookConfig["name"] ?? "{group-color}{enchant-no-color} {roman-level}");
        $item->setCustomName(TextFormat::colorize(self::applyTemplate($nameTpl, $placeholders)));

        $loreLines = (array) ($bookConfig["lore"] ?? []);
        $lore = [];
        foreach ($loreLines as $line) {
            $lore[] = TextFormat::colorize(self::applyTemplate((string) $line, $placeholders));
        }
        $item->setLore($lore);

        return $item;
    }

    private static function mainConfig(): ?Config {
        return GeneralUtils::getConfiguration(Loader::getInstance(), self::FILE_CONFIG);
    }

    /**
     * @return array<string, mixed>
     */
    private static function yamlDataForEnchant(string $enchantName): array {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), self::FILE_ENCHANTMENTS);
        if ($config === null) {
            return [];
        }

        $data = $config->get($enchantName);
        if (is_array($data)) {
            return $data;
        }

        $lower = strtolower($enchantName);
        foreach ($config->getAll() as $key => $row) {
            if (is_string($key) && strtolower($key) === $lower && is_array($row)) {
                return $row;
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $enchantData
     *
     * @return array<string, string>
     */
    private static function placeholderMap(
        CustomEnchantment $enchantment,
        array $enchantData,
        int $level,
        string $groupColor,
        string $romanLevel,
        string $displayForEnchantNoColorSlot,
        int $successChance,
        int $destroyChance
    ): array {
        $enchantName = $enchantment->getName();
        $applies = isset($enchantData["applies-to"])
            ? (string) $enchantData["applies-to"]
            : (implode(", ", $enchantment->getApplicableItems()) ?: "Unknown");

        $desc = $enchantment->getDescription();
        if ($desc === "" && isset($enchantData["description"])) {
            if (\is_array($enchantData["description"])) {
                $desc = implode("\n", $enchantData["description"]);
            } else {
                $desc = (string) $enchantData["description"];
            }
        }

        $coloredName = CustomEnchantments::getEnchantmentDisplayName($enchantName, $groupColor);

        return [
            "{enchantment}" => $coloredName,
            "{enchant-no-color}" => $displayForEnchantNoColorSlot,
            "{group-color}" => $groupColor,
            "{level}" => $romanLevel,
            "{roman-level}" => $romanLevel,
            "{level-numeric}" => (string) $level,
            "{max-level}" => (string) $enchantment->getMaxLevel(),
            "{max-level-roman}" => GeneralUtils::getRomanNumeral($enchantment->getMaxLevel()),
            "{success}" => (string) $successChance,
            "{destroy}" => (string) $destroyChance,
            "{applies-to}" => $applies,
            "{description}" => $desc,
        ];
    }

    /** @param array<string, string> $placeholders */
    private static function applyTemplate(string $text, array $placeholders): string {
        if ($placeholders === []) {
            return $text;
        }
        \uksort($placeholders, static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a));

        return \str_replace(\array_keys($placeholders), \array_values($placeholders), $text);
    }
}
