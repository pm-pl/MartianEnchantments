<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\commands\subcommands;

use ecstsy\MartianEnchantments\utils\screens\enchants\EnchantmentListScreen;
use ecstsy\MartianEnchantments\libs\CortexPE\Commando\BaseSubCommand;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ListSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize('&cOpen this menu in-game: &7/me list'));

            return;
        }

        EnchantmentListScreen::open($sender);
    }

    public function getPermission(): ?string {
        return "martianenchantments.list";
    }
}
