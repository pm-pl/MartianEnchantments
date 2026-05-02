<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\server\items;

use ecstsy\MartianEnchantments\enchantments\Groups;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianEnchantments\Loader;
use ecstsy\MartianEnchantments\utils\Utils;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\TextFormat;

/** Orbs, scrolls, RC books — config-driven helpers around {@see MartianEnchantItemFactory} / {@see MartianEnchantItems}. */
final class MartianItems {

    private const FILE_CONFIG = "config.yml";

    private const NESTED_ENCHANTER_BOOKS = "settings.enchanter-books";
    private const NESTED_SLOTS_MAX = "settings.slots.max";

    /** @var array<string, string> giveitem key → martianItem registry key */
    private const REGISTRY_SCROLLS = [
        "whitescroll" => "white-scroll",
        "blackscroll" => "black-scroll",
        "transmog" => "transmogscroll",
        "soulgem" => "soulgem",
        "itemnametag" => "itemnametag",
        "blocktrak" => "blocktrak",
        "stattrak" => "stattrak",
        "mobtrak" => "mobtrak",
        "holywhitescroll" => "holywhitescroll",
        "soultracker" => "soul-tracker",
    ];

    public static function createOrb(string $type, int $max, int $success = 100, int $amount = 1): Item {
        $mainConfig = GeneralUtils::getConfiguration(Loader::getInstance(), self::FILE_CONFIG);
        $defaultMax = 9;
        if ($mainConfig !== null) {
            $defaultMax = (int) $mainConfig->getNested(self::NESTED_SLOTS_MAX, 9);
        }

        $new = max(0, $max - $defaultMax);
        $args = [
            "max" => $max,
            "new" => $new,
            "success" => $success,
        ];

        $key = match (strtolower($type)) {
            "weapon" => "weapon-orb",
            "armor" => "armor-orb",
            "tool" => "tool-orb",
            default => null,
        };

        if ($key === null) {
            return VanillaItems::AIR()->setCount($amount);
        }

        try {
            $item = MartianEnchantItemFactory::create($key, $args);
        } catch (\Throwable $e) {
            Loader::getInstance()->getLogger()->error("Orb: " . $e->getMessage());

            return VanillaItems::AIR()->setCount($amount);
        }

        $item->setCount($amount);

        return $item;
    }

    public static function createScroll(
        string $scroll,
        int $amount = 1,
        int $rate = 100,
        string $groupName = ""
    ): ?Item {
        $id = strtolower($scroll);
        if ($id === "renametag") {
            $id = "itemnametag";
        }

        $g = self::resolveGroup($groupName);
        $item = null;

        if (isset(self::REGISTRY_SCROLLS[$id])) {
            $regKey = self::REGISTRY_SCROLLS[$id];
            $args = [];
            if ($id === "blackscroll") {
                $args["success"] = $rate;
            } elseif ($id === "soulgem") {
                $args["count"] = max(1, $rate);
                $args["group"] = (string) $g["key"];
            }
            $item = self::fromRegistry($regKey, $args, $amount);
        } else {
            $item = match ($id) {
                "mystery", "magic" => self::withCount(
                    MartianEnchantItems::mysteryDust(
                        (string) $g["name"],
                        $g["color"],
                        max(1, $rate),
                        (string) $g["key"]
                    ),
                    $amount
                ),
                "secret" => self::withCount(
                    MartianEnchantItems::secretDust((string) $g["name"], $g["color"], (string) $g["key"]),
                    $amount
                ),
                "randomizer" => self::withCount(
                    MartianEnchantItems::randomizationScroll((string) $g["name"], $g["color"], (string) $g["key"]),
                    $amount
                ),
                "slotincreaser" => self::slotIncreaserItem($g, $amount),
                default => null,
            };
        }

        if ($item === null) {
            Loader::getInstance()->getLogger()->debug("Scroll: unknown type: {$id}");

            return null;
        }

        if ($item->getTypeId() === VanillaItems::AIR()->getTypeId()) {
            return null;
        }

        return $item;
    }

    public static function createRCBook(string $group, int $amount = 1): Item {
        $groupId = Groups::getGroupId($group);
        if ($groupId === null) {
            Loader::getInstance()->getLogger()->error("RC book: invalid group: {$group}");

            return VanillaItems::AIR();
        }

        $config = GeneralUtils::getConfiguration(Loader::getInstance(), self::FILE_CONFIG);
        if ($config === null) {
            return VanillaItems::AIR();
        }

        $bookConfig = $config->getNested(self::NESTED_ENCHANTER_BOOKS);
        if (!\is_array($bookConfig)) {
            Loader::getInstance()->getLogger()->error("RC book: missing " . self::NESTED_ENCHANTER_BOOKS);

            return VanillaItems::AIR();
        }

        $type = $bookConfig["type"] ?? null;
        $name = $bookConfig["name"] ?? null;
        $lore = $bookConfig["lore"] ?? null;
        if ($type === null || $name === null || !\is_array($lore)) {
            Loader::getInstance()->getLogger()->error("RC book: incomplete enchanter-books config.");

            return VanillaItems::AIR();
        }

        $color = Groups::translateGroupToColor($groupId);
        $name = \str_replace(["{group-color}", "{group-name}"], [$color, ucfirst($group)], (string) $name);
        $lore = \array_map(static function (mixed $line) use ($color, $group): string {
            return \str_replace(["{group-color}", "{group-name}"], [$color, ucfirst($group)], (string) $line);
        }, $lore);

        $item = StringToItemParser::getInstance()->parse((string) $type);
        if ($item === null) {
            Loader::getInstance()->getLogger()->error("RC book: invalid type: {$type}");

            return VanillaItems::AIR();
        }

        $item->setCount($amount);
        $item->setCustomName(TextFormat::colorize($name));
        $item->setLore(\array_map(static fn (string $line): string => TextFormat::colorize($line), $lore));

        if (!empty($bookConfig["force-glow"])) {
            Utils::applyDisplayEnchant($item);
        }

        $root = $item->getNamedTag();
        $rcBookTag = new CompoundTag();
        $rcBookTag->setString("martianItem", "enchanter-book");
        $rcBookTag->setString("group", \strtoupper($group));
        $root->setTag("MartianEnchantments", $rcBookTag);

        return $item;
    }

    /**
     * @return array{key: string, id: int, color: string, name: string}
     */
    private static function resolveGroup(string $groupName): array {
        $g = \strtoupper(\trim($groupName));
        if ($g === "") {
            $g = Groups::getFallbackGroup();
        }
        if (Groups::getGroupData($g) === null) {
            $g = Groups::getFallbackGroup();
        }
        $id = Groups::getGroupId($g);
        if ($id === null) {
            $g = Groups::getFallbackGroup();
            $id = Groups::getGroupId($g) ?? 1;
        }

        return [
            "key" => $g,
            "id" => $id,
            "color" => Groups::translateGroupToColor($id),
            "name" => (string) (Groups::getGroupName($id) ?? $g),
        ];
    }

    /**
     * @param array{key: string, id: int, color: string, name: string} $g
     */
    private static function slotIncreaserItem(array $g, int $amount): ?Item {
        $groupData = Groups::getGroupData($g["key"]) ?? [];
        $slots = (int) ($groupData["slot_increaser"]["slots"] ?? 1);

        try {
            $item = MartianEnchantItems::slotIncreaser((string) $g["name"], $g["color"], $slots, (string) $g["key"]);
            $item->setCount($amount);

            return $item;
        } catch (\Throwable $e) {
            Loader::getInstance()->getLogger()->debug("Slot increaser: " . $e->getMessage());

            return null;
        }
    }

    /** @param array<string, int|string> $args */
    private static function fromRegistry(string $registryKey, array $args, int $amount): ?Item {
        try {
            $item = MartianEnchantItemFactory::create($registryKey, $args);
            $item->setCount($amount);

            return $item;
        } catch (\Throwable $e) {
            Loader::getInstance()->getLogger()->debug("Scroll ({$registryKey}): " . $e->getMessage());

            return null;
        }
    }

    private static function withCount(Item $item, int $count): Item {
        $item->setCount($count);

        return $item;
    }
}
