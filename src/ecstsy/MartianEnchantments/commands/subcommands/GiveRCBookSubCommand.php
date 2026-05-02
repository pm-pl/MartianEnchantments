<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\commands\subcommands;

use ecstsy\MartianEnchantments\libs\CortexPE\Commando\args\IntegerArgument;
use ecstsy\MartianEnchantments\libs\CortexPE\Commando\args\RawStringArgument;
use ecstsy\MartianEnchantments\libs\CortexPE\Commando\BaseSubCommand;
use ecstsy\MartianEnchantments\Loader;
use ecstsy\MartianEnchantments\server\items\MartianItems;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

class GiveRCBookSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("type", false));
        $this->registerArgument(1, new RawStringArgument("name", false));
        $this->registerArgument(2, new IntegerArgument("amount", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&7In-game only."));
            return;
        }

        $type = isset($args["type"]) ? strtolower($args["type"]) : null;
        $name = isset($args["name"]) ? $args["name"] : null;
        $amount = isset($args["amount"]) ? $args["amount"] : null;
        
        $player = PlayerUtils::getPlayerByPrefix($name);
        $groupConfig = GeneralUtils::getConfiguration(Loader::getInstance(), "groups.yml")->getAll();

        $groupMap = array_change_key_case($groupConfig['groups'], CASE_LOWER);
        $groupMapUpper = array_change_key_case($groupConfig['groups'], CASE_UPPER);
        $availableGroups = array_keys($groupMapUpper);

        if ($type !== null) {
            if (!in_array(strtoupper($type), $availableGroups)) {
                $sender->sendMessage(C::colorize("&r&cCould not find specified group. Maybe try one of these:"));
                $sender->sendMessage(C::colorize("&r&f" . implode(", ", $availableGroups)));
                return;
            }

            $originalGroupName = $groupConfig['groups'][strtoupper($type)]['group-name'];

            if ($name !== null) {
                if ($amount !== null) {
                    if ($player !== null) {
                        $rcBook = MartianItems::createRCBook($originalGroupName, $amount);
                        if ($player->getInventory()->canAddItem($rcBook)) {
                            $player->getInventory()->addItem($rcBook);
                            $sender->sendMessage(C::colorize(str_replace(
                                [
                                    "{player}", "{amount}", "{group}",
                                ],
                                [
                                    $player->getName(), $amount, strtoupper($type)
                                ],
                                Loader::getInstance()->getLanguageManager()->getNested("commands.main.givercbook.success"))));
                                PlayerUtils::playSound($sender, "random.orb");
                                
                        }
                    } else {
                        $sender->sendMessage(C::colorize(Loader::getInstance()->getLanguageManager()->getNested("commands.offline-player")));
                    }
                } else {
                    $sender->sendMessage(C::colorize(Loader::getInstance()->getLanguageManager()->getNested("commands.invalid-amount")));
                }
            } else {
                $sender->sendMessage(C::colorize(Loader::getInstance()->getLanguageManager()->getNested("commands.offline-player")));
            }
        } else {
            $sender->sendMessage(C::colorize("&r&cCould not find specified group. Maybe try one of these:"));
            $sender->sendMessage(C::colorize("&r&f" . implode(", ", $availableGroups)));
        }
    }

    public function getUsage(): string
    {
        return "/ae givercbook <type> <player> <amount>";
    }
    public function getPermission(): string {
        return "martianenchantments.give-rcbook";
    }
}