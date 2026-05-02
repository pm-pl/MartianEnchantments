<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\commands\subcommands;

use ecstsy\MartianEnchantments\commands\EnchantmentInfoPresenter;
use ecstsy\MartianEnchantments\libs\CortexPE\Commando\args\RawStringArgument;
use ecstsy\MartianEnchantments\libs\CortexPE\Commando\BaseSubCommand;
use ecstsy\MartianEnchantments\enchantments\CustomEnchantment;
use ecstsy\MartianEnchantments\enchantments\CustomEnchantments;
use ecstsy\MartianEnchantments\Loader;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class InfoSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("enchantment", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&7In-game only."));

            return;
        }

        $enchant = isset($args["enchantment"]) ? (string) $args["enchantment"] : '';
        $lang = Loader::getInstance()->getLanguageManager();

        if ($enchant === '') {
            $sender->sendMessage(C::colorize((string) $lang->getNested("commands.invalid-usage", "&7Usage: /me info <enchantment>")));

            return;
        }

        $enchantment = CustomEnchantments::getEnchantmentByName($enchant);
        if ($enchantment instanceof CustomEnchantment) {
            foreach (EnchantmentInfoPresenter::lines($enchantment) as $line) {
                $sender->sendMessage(C::colorize($line));
            }
            PlayerUtils::playSound($sender, "random.orb");
        } else {
            $sender->sendMessage(C::colorize(str_replace("{enchant}", $enchant, (string) $lang->getNested("commands.invalid-enchant"))));
            PlayerUtils::playSound($sender, "note.bass");
        }
    }

    public function getPermission(): string {
        return "martianenchantments.info";
    }
}
