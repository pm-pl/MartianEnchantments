<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\commands\subcommands;

use ecstsy\MartianEnchantments\libs\CortexPE\Commando\BaseSubCommand;
use ecstsy\MartianEnchantments\Loader;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianEnchantments\armor\ArmorSetRegistry;
use ecstsy\MartianEnchantments\server\items\rename\MartianItemRenameSession;
use ecstsy\MartianEnchantments\utils\SoulGemSessionManager;
use ecstsy\MartianEnchantments\utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class ReloadSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->setPermissionMessage(Loader::getInstance()->getLanguageManager()->getNested("commands.no-permission"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $startTime = microtime(true);

        $lang = Loader::getInstance()->getLanguageManager();

        $configFiles = ["config.yml", "enchantments.yml", "groups.yml"];

        GeneralUtils::clearConfigurationCache();

        foreach ($configFiles as $file) {
            $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), $file);
            if ($cfg !== null) {
                $cfg->reload();
            } else {
                $sender->sendMessage(C::colorize("&cFailed to reload configuration file: {$file}"));
            }
        }

        SoulGemSessionManager::clearCachedParticleResolver();
        SoulGemSessionManager::deactivateOnlineChannelsWhenSoulsDisabled();
        MartianItemRenameSession::invalidateCachedConfig();

        Utils::upgradePackagedLocaleFiles(Loader::getInstance());
        GeneralUtils::clearConfigurationCache();

        $localeDir = Loader::getInstance()->getDataFolder() . "locale/";
        $localeFiles = glob($localeDir . "*.yml") ?: [];
        foreach ($localeFiles as $filePath) {
            $relativeFile = str_replace(Loader::getInstance()->getDataFolder(), "", (string) $filePath);

            $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), $relativeFile);
            if ($cfg !== null) {
                $cfg->reload();
            } else {
                $sender->sendMessage(C::colorize("&cFailed to reload locale file: {$relativeFile}"));
            }
        }

        GeneralUtils::clearConfigurationCache();

        $armorSetsDir = Loader::getInstance()->getDataFolder() . "armorSets/";
        $armorSetFiles = glob($armorSetsDir . "*.yml") ?: [];
        foreach ($armorSetFiles as $filePath) {
            $relativeFile = str_replace(Loader::getInstance()->getDataFolder(), "", (string) $filePath);

            $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), $relativeFile);
            if ($cfg !== null) {
                $cfg->reload();
            } else {
                $sender->sendMessage(C::colorize("&cFailed to reload armor set file: {$relativeFile}"));
            }
        }

        GeneralUtils::clearConfigurationCache();
        ArmorSetRegistry::reload();

        $lang->reload();

        $armorSetsCount = count($armorSetFiles);
        $loadedSetIds = ArmorSetRegistry::allIds();
        $timeTaken = (microtime(true) - $startTime) * 1000;

        $notice = (string) $lang->getNested("commands.reload.notice-scope", "");
        if (trim($notice) !== "") {
            $sender->sendMessage(C::colorize($notice));
        }

        $setsForChat = $loadedSetIds !== [] ? implode(", ", $loadedSetIds) : "&cnone (see console)";

        $ok = str_replace(
            ["{ms}", "{armor}", "{sets}"],
            [(string) round($timeTaken, 2), (string) $armorSetsCount, $setsForChat],
            (string) $lang->getNested("commands.reload.success", "")
        );
        if (trim($ok) !== "") {
            $sender->sendMessage(C::colorize($ok));
        }

        Loader::getInstance()->getLogger()->debug(
            "Reload: {$armorSetsCount} armor YAML on disk, sets: "
            . ($loadedSetIds !== [] ? implode(", ", $loadedSetIds) : "(none)")
            . " (~" . round($timeTaken, 2) . "ms)"
        );
    }

    public function getPermission(): string {
        return "martianenchantments.reload";
    }
}
