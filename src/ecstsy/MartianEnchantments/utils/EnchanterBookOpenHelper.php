<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\utils;

use ecstsy\MartianEnchantments\enchantments\CustomEnchantment;
use ecstsy\MartianEnchantments\enchantments\CustomEnchantments;
use ecstsy\MartianEnchantments\enchantments\Groups;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\PlayerUtils;
use ecstsy\MartianEnchantments\Loader;
use ecstsy\MartianEnchantments\server\items\MartianEnchantmentBooks;
use pocketmine\block\utils\DyeColor;
use pocketmine\entity\object\FireworkRocket;
use pocketmine\item\FireworkRocketExplosion;
use pocketmine\item\FireworkRocketType;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as C;

/**
 * Right-click “RC” / enchanter-book: random book from group + optional fireworks (Glacia-like).
 */
final class EnchanterBookOpenHelper {

    public static function tryOpen(Player $player, Item $item): void {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");
        if (!($config instanceof Config)) {
            return;
        }

        $tag = $item->getNamedTag()->getCompoundTag("MartianEnchantments");
        if ($tag === null) {
            return;
        }

        $groupRaw = strtoupper(trim($tag->getString("group", "")));
        if ($groupRaw === "") {
            $groupRaw = Groups::getFallbackGroup();
        }

        $groupId = Groups::getGroupId($groupRaw);
        if ($groupId === null) {
            return;
        }

        $pool = CustomEnchantments::getEnchantmentsForGroup($groupId);
        if ($pool === []) {
            $lang = Loader::getInstance()->getLanguageManager();
            $tpl = (string) $lang->getNested(
                "interact.enchanter-book.empty-group",
                "&cThere are no enchantments in that group (&f{group}&c)."
            );
            if (str_starts_with($tpl, "Translation not found:")) {
                $tpl = "&cThere are no enchantments in that group (&f{group}&c).";
            }
            $player->sendMessage(C::colorize(str_replace("{group}", $groupRaw, $tpl)));
            PlayerUtils::playSound($player, "note.bass");

            return;
        }

        /** @var CustomEnchantment $pick */
        $pick = $pool[array_rand($pool)];
        $level = mt_rand(1, max(1, $pick->getMaxLevel()));
        $success = mt_rand(1, 100);
        $destroy = mt_rand(1, 100);

        $book = MartianEnchantmentBooks::create($pick, $level, $success, $destroy);
        if ($book === null) {
            return;
        }

        $leftover = $player->getInventory()->addItem($book);
        foreach ($leftover as $drop) {
            if (!$drop->isNull()) {
                $player->getWorld()->dropItem($player->getPosition()->add(0, 0.5, 0), $drop);
            }
        }

        $item->pop();
        $player->getInventory()->setItemInHand($item);

        $sound = (string) $config->getNested("settings.enchanter-books.cosmetics.sound", "random.levelup");
        PlayerUtils::playSound($player, $sound);

        $particle = (string) $config->getNested("settings.enchanter-books.cosmetics.particle", "");
        if ($particle !== "") {
            PlayerUtils::spawnParticleEffectFor($player, $player->getPosition()->add(0, 1, 0), $particle);
        }

        if ((bool) $config->getNested("settings.enchanter-books.firework-on-open", true)) {
            self::spawnGroupFirework($player, $groupRaw, $config);
        }

        if ((bool) $config->getNested("settings.enchanter-books.display-right-click-message", true)) {
            $lang = Loader::getInstance()->getLanguageManager();
            $tpl = (string) $lang->getNested("interact.enchanter-book.success", "");
            if (!is_string($tpl) || trim($tpl) === "" || str_starts_with($tpl, "Translation not found:")) {
                $fallback = $config->getNested("settings.enchanter-books.message");
                if (is_array($fallback) && isset($fallback[0])) {
                    $tpl = (string) $fallback[0];
                } else {
                    $tpl = "&r&7You examined {group-color}{group-name}&r&7 book and found {enchant-color} {level}";
                }
            }

            $gName = (string) (Groups::getGroupName($groupId) ?? $groupRaw);
            $gColor = Groups::translateGroupToColor($groupId);
            $enchColor = CustomEnchantments::getEnchantmentDisplayName($pick->getName(), Groups::translateGroupToColor($pick->getRarity()));
            $roman = GeneralUtils::getRomanNumeral($level);

            $player->sendMessage(C::colorize(str_replace(
                ["{group-color}", "{group-name}", "{enchant-color}", "{level}", "{enchant}"],
                [$gColor, $gName, $enchColor, $roman, $pick->getName()],
                $tpl
            )));
        }
    }

    private static function spawnGroupFirework(Player $player, string $groupUpper, Config $config): void {
        $flight = max(1, (int) $config->getNested("settings.enchanter-books.firework-flight-duration", 10));
        $burst = max(1, (int) $config->getNested("settings.enchanter-books.firework-explosion-count", 3));

        [$mainColors, $fadeColors, $type] = self::paletteForGroup($groupUpper);

        $explosions = [];
        for ($i = 0; $i < $burst; $i++) {
            $explosions[] = new FireworkRocketExplosion(
                $type,
                $mainColors,
                $fadeColors,
                true,
                true
            );
        }

        try {
            $rocket = new FireworkRocket($player->getLocation(), $flight, $explosions);
            $rocket->spawnToAll();
        } catch (\Throwable) {
            // Cosmetic only
        }
    }

    /**
     * @return array{0: list<DyeColor>, 1: list<DyeColor>, 2: FireworkRocketType}
     */
    private static function paletteForGroup(string $groupUpper): array {
        $g = strtoupper($groupUpper);

        return match ($g) {
            "SIMPLE" => [
                [DyeColor::LIGHT_GRAY(), DyeColor::GRAY()],
                [DyeColor::GRAY(), DyeColor::LIGHT_GRAY()],
                FireworkRocketType::LARGE_BALL,
            ],
            "UNIQUE" => [
                [DyeColor::GREEN(), DyeColor::LIME()],
                [DyeColor::LIME(), DyeColor::GREEN()],
                FireworkRocketType::LARGE_BALL,
            ],
            "ELITE" => [
                [DyeColor::CYAN(), DyeColor::BLUE()],
                [DyeColor::BLUE(), DyeColor::CYAN()],
                FireworkRocketType::LARGE_BALL,
            ],
            "ULTIMATE" => [
                [DyeColor::YELLOW(), DyeColor::ORANGE()],
                [DyeColor::ORANGE(), DyeColor::YELLOW()],
                FireworkRocketType::LARGE_BALL,
            ],
            "LEGENDARY" => [
                [DyeColor::ORANGE(), DyeColor::RED()],
                [DyeColor::RED(), DyeColor::ORANGE()],
                FireworkRocketType::LARGE_BALL,
            ],
            "FABLED" => [
                [DyeColor::PINK(), DyeColor::MAGENTA()],
                [DyeColor::MAGENTA(), DyeColor::PINK()],
                FireworkRocketType::LARGE_BALL,
            ],
            default => [
                [DyeColor::WHITE(), DyeColor::LIGHT_GRAY()],
                [DyeColor::LIGHT_GRAY(), DyeColor::WHITE()],
                FireworkRocketType::SMALL_BALL,
            ],
        };
    }
}
