<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\server\items;

use ecstsy\MartianEnchantments\Loader;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\TextFormat;

final class MartianEnchantItemFactory {

    /** @var array<string, array> */
    public static array $definitions = [];

    public static function create(string $key, array $args = []): Item {
        $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");

        $path = MartianItemRegistry::getConfigPath($key);
        $itemCfg = $cfg->getNested($path);

        if ($itemCfg === null) {
            throw new \InvalidArgumentException("Unknown item config path: $path");
        }

        $type = $itemCfg['type']
            ?? $itemCfg['material']
            ?? $itemCfg['item']['type']
            ?? throw new \RuntimeException("Missing item type for '$key'");

        $item = StringToItemParser::getInstance()->parse($type)
            ?? throw new \RuntimeException("Invalid material '$type'");

        if (isset($itemCfg['name'])) {
            $item->setCustomName(TextFormat::colorize(
                self::format($itemCfg['name'], $args)
            ));
        }

        if (!empty($itemCfg['lore'])) {
            $lore = [];
            foreach ($itemCfg['lore'] as $line) {
                $lore[] = TextFormat::colorize(
                    self::format($line, $args)
                );
            }
            $item->setLore($lore);
        }

        $root = $item->getNamedTag();
        $tag = new CompoundTag();

        $tag->setString("martianItem", strtolower($key));

        foreach ($args as $k => $v) {
            is_int($v)
                ? $tag->setInt($k, $v)
                : $tag->setString($k, (string)$v);
        }

        $root->setTag("MartianEnchantments", $tag);

        return $item;
    }

    /**
     * Re-applies colored name/lore from config for a registry item (same placeholders as {@see create}), without overwriting NBT.
     */
    public static function refreshNameAndLore(string $registryKey, Item $item, array $args): void {
        $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");

        $path = MartianItemRegistry::getConfigPath($registryKey);
        $itemCfg = $cfg?->getNested($path);

        if ($itemCfg === null || !\is_array($itemCfg)) {
            return;
        }

        if (isset($itemCfg['name'])) {
            $item->setCustomName(TextFormat::colorize(
                self::format((string) $itemCfg['name'], $args)
            ));
        }

        if (!empty($itemCfg['lore']) && \is_array($itemCfg['lore'])) {
            $lore = [];
            foreach ($itemCfg['lore'] as $line) {
                $lore[] = TextFormat::colorize(
                    self::format((string) $line, $args)
                );
            }
            $item->setLore($lore);
        }
    }

    private static function format(string $text, array $args): string {
        foreach ($args as $k => $v) {
            $text = str_replace("{" . $k . "}", (string)$v, $text);
        }
        return $text;
    }
}
