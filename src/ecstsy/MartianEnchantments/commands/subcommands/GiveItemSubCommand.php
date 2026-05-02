<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\commands\subcommands;

use ecstsy\MartianEnchantments\enchantments\Groups;
use ecstsy\MartianEnchantments\libs\CortexPE\Commando\args\IntegerArgument;
use ecstsy\MartianEnchantments\libs\CortexPE\Commando\args\RawStringArgument;
use ecstsy\MartianEnchantments\libs\CortexPE\Commando\BaseSubCommand;
use ecstsy\MartianEnchantments\Loader;
use ecstsy\MartianEnchantments\server\items\MartianItems;
use ecstsy\MartianEnchantments\utils\ScrollHandler;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class GiveItemSubCommand extends BaseSubCommand {

    /** @var list<string> */
    private array $availableItems = [
        "slotincreaser", "whitescroll", "mystery", "secret", "magic",
        "blackscroll", "randomizer", "renametag", "blocktrak",
        "stattrak", "soultracker", "mobtrak", "soulgem", "transmog",
        "holywhitescroll", "orb"
    ];
    
    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->setPermissionMessage(Loader::getInstance()->getLanguageManager()->getNested("commands.no-permission"));
        $this->registerArgument(0, new RawStringArgument("name", false));
        $this->registerArgument(1, new RawStringArgument("item", false));
        $this->registerArgument(2, new IntegerArgument("amount", false));

        $this->registerArgument(3, new RawStringArgument("extra1", true));
        $this->registerArgument(4, new IntegerArgument("extra2", true));
        $this->registerArgument(5, new IntegerArgument("extra3", true));
    }   

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $lang = Loader::getInstance()->getLanguageManager();
        $itemsList = implode(", ", $this->availableItems);

        if (!$sender instanceof Player) {
            if (empty($args)) {
                $message = (string) $lang->getNested("commands.main.giveitem.console", "");
                if ($message !== "" && $message !== "commands.main.giveitem.console") {
                    $sender->sendMessage(C::colorize(str_replace("{items}", $itemsList, $message)));
                } else {
                    $this->sendUsageLines($sender, $itemsList);
                }
                return;
            }           
            return;
        }

        if (!isset($args["name"]) && !isset($args["item"]) && !isset($args["amount"])) {
            $sender->sendMessage(C::colorize(str_replace("{usage}", C::colorize($this->getUsage()), $lang->getNested("commands.invalid-usage"))));
            return;
        }

        $player = isset($args["name"]) ? PlayerUtils::getPlayerByPrefix($args["name"]) : null;
        $amount = (int) ($args["amount"] ?? 1);
        $item = isset($args["item"]) ? strtolower((string) $args["item"]) : null;

        if ($player === null || !$player->isOnline()) {
            $sender->sendMessage(C::colorize($lang->getNested("commands.offline-player")));
            return;
        }

        if ($item === null || !in_array($item, $this->availableItems, true)) {
            $this->sendUsageLines($sender, $itemsList);
            return;
        }

        if ($item === "orb") {
            if (isset($args["extra1"], $args["extra2"], $args["extra3"])) {
                $orbType = (string) $args["extra1"];
                $max = (int) $args["extra2"];
                $success = (int) $args["extra3"];
                $orbItem = MartianItems::createOrb($orbType, $max, $success, $amount);

                if ($player->getInventory()->canAddItem($orbItem)) {
                    $player->getInventory()->addItem($orbItem);
                } else {
                    $player->getWorld()->dropItem($player->getLocation()->asVector3(), $orbItem);
                }
                $sender->sendMessage(C::colorize(str_replace(
                    ["{player}", "{item}", "{amount}"],
                    [$player->getName(), C::colorize($orbItem->getCustomName() ?? "Orb"), (string) $amount],
                    $lang->getNested("commands.main.giveitem.success")
                )));
                PlayerUtils::playSound($sender, "random.orb");
            } else {
                $this->sendUsageLines($sender, $itemsList);
            }
            return;
        }

        $groupFallback = Groups::getFallbackGroup();
        $scrollItem = ScrollHandler::createScrollFromGiveItem($item, $amount, $args, $groupFallback);

        if ($scrollItem === null) {
            if ($item === "randomizer" || $item === "secret") {
                $sender->sendMessage(C::colorize(str_replace(
                    ["{player}", "{item}", "{amount}"],
                    [$player->getName(), $item, (string) $amount],
                    $lang->getNested("commands.main.giveitem.needs-group")
                )));
            } else {
                $sender->sendMessage(C::colorize($lang->getNested("commands.main.giveitem.failed")));
            }

            return;
        }

        if ($player->getInventory()->canAddItem($scrollItem)) {
            $player->getInventory()->addItem($scrollItem);
        } else {
            $player->getWorld()->dropItem($player->getLocation()->asVector3(), $scrollItem);
        }
        $display = $scrollItem->getCustomName();
        if ($display === null || $display === "") {
            $display = $scrollItem->getName();
        }
        $sender->sendMessage(C::colorize(str_replace(
            ["{player}", "{item}", "{amount}"],
            [$player->getName(), C::colorize($display), (string) $amount],
            $lang->getNested("commands.main.giveitem.success")
        )));
        PlayerUtils::playSound($sender, "random.orb");
    }

    private function sendUsageLines(CommandSender $sender, string $itemsList): void {
        $lang = Loader::getInstance()->getLanguageManager();
        $lines = $lang->getNested("commands.main.giveitem.usage-lines");
        if (is_array($lines)) {
            foreach ($lines as $line) {
                $text = (string) $line;
                if (str_contains($text, "{items}")) {
                    $text = str_replace("{items}", $itemsList, $text);
                }
                $sender->sendMessage(C::colorize($text));
            }
        } else {
            $sender->sendMessage(C::colorize("/me giveitem <player> <item> <count> [extras] | " . $itemsList));
        }
    }

    public function getUsage(): string
    {
        $lang = Loader::getInstance()->getLanguageManager();

        return C::colorize((string) $lang->getNested(
            "commands.main.giveitem.usage-one-liner",
            "&c/me giveitem <player> <item> <amount> [extras] &8- See &f/me help &cfor item types."
        ));
    }

    public function getPermission(): string
    {
        return "martianenchantments.giveitem";
    }
}
