<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\commands\subcommands;

use ecstsy\MartianEnchantments\libs\CortexPE\Commando\BaseSubCommand;
use ecstsy\MartianEnchantments\Loader;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

final class AboutSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $v = Loader::getInstance()->getDescription()->getVersion();
        $sender->sendMessage(TextFormat::colorize("&r&3&lMartian&bEnchantments&r &8v&7" . $v));
        $sender->sendMessage(TextFormat::colorize("&7Custom enchantments for &fPocketMine-MP&7."));
    }

    public function getPermission(): string
    {
        return "martianenchantments.default";
    }
}