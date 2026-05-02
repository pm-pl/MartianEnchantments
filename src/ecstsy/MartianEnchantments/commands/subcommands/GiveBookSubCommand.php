<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\commands\subcommands;

use ecstsy\MartianEnchantments\enchantments\CustomEnchantmentManager;
use ecstsy\MartianEnchantments\libs\CortexPE\Commando\args\IntegerArgument;
use ecstsy\MartianEnchantments\libs\CortexPE\Commando\args\RawStringArgument;
use ecstsy\MartianEnchantments\libs\CortexPE\Commando\BaseSubCommand;
use ecstsy\MartianEnchantments\Loader;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\PlayerUtils;
use ecstsy\MartianEnchantments\server\items\MartianEnchantItems;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;
use pocketmine\player\Player;

final class GiveBookSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->setPermissionMessage(Loader::getInstance()->getLanguageManager()->getNested("commands.no-permission"));
        $this->registerArgument(0, new RawStringArgument("name", false));
        $this->registerArgument(1, new RawStringArgument("enchantment", false));
        $this->registerArgument(2, new IntegerArgument("level", false));
        $this->registerArgument(3, new IntegerArgument("amount", false));
        $this->registerArgument(4, new IntegerArgument("success", false));
        $this->registerArgument(5, new IntegerArgument("destroy", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $lang = Loader::getInstance()->getLanguageManager();

        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&7In-game only!"));
            return;
        }

        $playerName = $args["name"] ?? null;
        $enchantKey = $args["enchantment"] ?? null;
        $level = $args["level"] ?? null;
        $amount = $args["amount"] ?? 1;
        $success = $args["success"] ?? null;
        $destroy = $args["destroy"] ?? null;

        if ($playerName === null) {
            $sender->sendMessage(C::colorize($lang->getNested("commands.invalid-player")));
            return;
        }

        $player = PlayerUtils::getPlayerByPrefix($playerName);
        if ($player === null) {
            $sender->sendMessage(C::colorize($lang->getNested("commands.invalid-player")));
            return;
        }

        if ($enchantKey === null) {
            $sender->sendMessage(C::colorize($lang->getNested("commands.invalid-enchantment")));
            return;
        }

        if ($level === null) {
            $sender->sendMessage(C::colorize($lang->getNested("commands.invalid-level")));
            return;
        }

        if ($success === null || $destroy === null) {
            $sender->sendMessage(C::colorize($lang->getNested("commands.invalid-usage")));
            return;
        }

        $enchantment = CustomEnchantmentManager::getEnchantment($enchantKey);
        if ($enchantment === null) {
            $sender->sendMessage(C::colorize($lang->getNested("commands.invalid-enchantment")));
            return;
        }

        $book = MartianEnchantItems::enchantmentBook($enchantment, $level, $success, $destroy)->setCount($amount);

        if ($player->getInventory()->canAddItem($book)) {
            $player->getInventory()->addItem($book);
        } else {
            $sender->getWorld()->dropItem(
                $sender->getPosition()->asVector3(),
                $book
            );
        }

        $sender->sendMessage(C::colorize(str_replace(
            ["{enchant}", "{level}", "{player}", "{amount}"],
            [$enchantKey, $level, $player->getName(), $amount],
            $lang->getNested("commands.main.givebook.success")
        )));

        PlayerUtils::playSound($sender, "random.orb");
    }

    public function getUsage(): string {
        return "/me givebook <player> <enchant> <level> <amount> <success> <destroy>";
    }

    public function getPermission(): ?string
    {
        return "martianenchantments.give";
    }
}
