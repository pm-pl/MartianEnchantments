<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\server\items;

final class MartianItemRegistry {

    /** @var array<string, string> */
    private static array $paths = [
        'enchantment-book' => 'settings.enchantment-book',
        'enchanter-book' => 'settings.enchanter-books',
        'soul-tracker' => 'settings.souls.item',

        'white-scroll' => 'items.white-scroll',
        'transmogscroll' => 'items.transmogscroll',
        'soulgem' => 'items.soulgem',
        'itemnametag' => 'items.itemnametag',
        'randomization-scroll' => 'items.randomization-scroll',
        'black-scroll' => 'items.black-scroll',      
        'secret-dust' => 'items.secret-dust',
        'mystery-dust' => 'items.mystery-dust',
        'magic-dust' => 'items.magic-dust',
        'slot-increaser' => 'items.slot-increaser',
        'stattrak' => 'items.stattrak',
        'mobtrak' => 'items.mobtrak',
        'blocktrak' => 'items.blocktrak',
        'holywhitescroll' => 'items.holywhitescroll',
        'weapon-orb' => 'items.orb.weapon',
        'armor-orb' => 'items.orb.armor',
        'tool-orb' => 'items.orb.tool',
    ];

    public static function getConfigPath(string $key): string {
        return self::$paths[$key] ?? "items.$key";
    }

    public static function register(string $key, string $path): void {
        if (isset(self::$paths[$key])) {
            throw new \LogicException("Item '$key' is already registered");
        }
        self::$paths[$key] = $path;
    }
}