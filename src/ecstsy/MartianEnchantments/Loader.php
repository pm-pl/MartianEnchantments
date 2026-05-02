<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments;

use ecstsy\MartianEnchantments\utils\Utils;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\managers\LanguageManager;
use pocketmine\plugin\PluginBase;
use pocketmine\resourcepacks\ZippedResourcePack;
use pocketmine\utils\SingletonTrait;

final class Loader extends PluginBase {
    use SingletonTrait;

    private static ?ZippedResourcePack $pack;
    public const TYPE_DYNAMIC_PREFIX = "martianenchants:customsizedinvmenu_"; # The entire custom sized inv is from muqsit

    public function onLoad(): void {
        self::setInstance($this);

        $autoload = dirname($this->getFile()) . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php";
        if (is_file($autoload)) {
            require_once $autoload;
        }
    }

    public function onEnable(): void {
        Utils::initAll();
    } 

    public function getDisable(): void {

    }

    public function getLanguageManager(): LanguageManager {
        return Utils::$languageManager;
    }
}
