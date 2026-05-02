<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\commands;

use ecstsy\MartianEnchantments\libs\CortexPE\Commando\args\IntegerArgument;
use ecstsy\MartianEnchantments\libs\CortexPE\Commando\BaseCommand;
use ecstsy\MartianEnchantments\commands\subcommands\AboutSubCommand;
use ecstsy\MartianEnchantments\commands\subcommands\EnchantSubCommand;
use ecstsy\MartianEnchantments\commands\subcommands\GiveArmorSetSubCommand;
use ecstsy\MartianEnchantments\commands\subcommands\GiveBookSubCommand;
use ecstsy\MartianEnchantments\commands\subcommands\GiveItemSubCommand;
use ecstsy\MartianEnchantments\commands\subcommands\GiveRCBookSubCommand;
use ecstsy\MartianEnchantments\commands\subcommands\InfoSubCommand;
use ecstsy\MartianEnchantments\commands\subcommands\ListSubCommand;
use ecstsy\MartianEnchantments\commands\subcommands\ReloadSubCommand;
use ecstsy\MartianEnchantments\commands\subcommands\UnenchantSubCommand;
use ecstsy\MartianEnchantments\Loader;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class MECommand extends BaseCommand {

    private const ITEMS_PER_PAGE = 14;
    private const LANG_HELP = "commands.main.help";

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new IntegerArgument('page', true));

        $lang = Loader::getInstance()->getLanguageManager();
        $h = self::LANG_HELP . ".sub.";

        $this->registerSubCommand(new GiveItemSubCommand(Loader::getInstance(), "giveitem", (string) $lang->getNested($h . "giveitem")));
        $this->registerSubCommand(new AboutSubCommand(Loader::getInstance(), "about", (string) $lang->getNested($h . "about")));
        $this->registerSubCommand(new EnchantSubCommand(Loader::getInstance(), "enchant", (string) $lang->getNested($h . "enchant")));
        $this->registerSubCommand(new UnenchantSubCommand(Loader::getInstance(), "unenchant", (string) $lang->getNested($h . "unenchant")));
        $this->registerSubCommand(new ListSubCommand(Loader::getInstance(), "list", (string) $lang->getNested($h . "list")));
        $this->registerSubCommand(new GiveBookSubCommand(Loader::getInstance(), "givebook", (string) $lang->getNested($h . "givebook")));
        $this->registerSubCommand(new InfoSubCommand(Loader::getInstance(), "info", (string) $lang->getNested($h . "info")));
        $this->registerSubCommand(new ReloadSubCommand(Loader::getInstance(), "reload", (string) $lang->getNested($h . "reload")));
        $this->registerSubCommand(new GiveRCBookSubCommand(Loader::getInstance(), "givercbook", (string) $lang->getNested($h . "givercbook")));
        $this->registerSubCommand(new GiveArmorSetSubCommand(Loader::getInstance(), "givearmorset", (string) $lang->getNested($h . "givearmorset")));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $lang = Loader::getInstance()->getLanguageManager();
        $h = self::LANG_HELP;

        if (!$sender instanceof Player) {
            $lines = $lang->getNested($h . ".console");
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    $sender->sendMessage(C::colorize((string) $line));
                }
            }
            return;
        }

        $page = (int) ($args['page'] ?? 1);

        $body = $lang->getNested($h . ".player");

        /** @var list<string> */
        $lines = [];
        if (is_array($body)) {
            foreach ($body as $line) {
                $lines[] = (string) $line;
            }
        }

        $totalItems = count($lines);
        $totalPages = max(1, (int) ceil($totalItems / self::ITEMS_PER_PAGE));

        if ($page < 1 || $page > $totalPages) {
            $sender->sendMessage(C::colorize(str_replace("{max}", (string) $totalPages, (string) $lang->getNested($h . ".invalid-page"))));
            return;
        }

        $start = ($page - 1) * self::ITEMS_PER_PAGE;
        $end = min($start + self::ITEMS_PER_PAGE, $totalItems);

        $headerStr = (string) $lang->getNested($h . ".header");
        $headerStr = str_replace("{page}", (string) $page, $headerStr);
        $footerStr = (string) $lang->getNested($h . ".footer");

        $sender->sendMessage(C::colorize($headerStr));
        $sender->sendMessage(" ");

        for ($i = $start; $i < $end; $i++) {
            $sender->sendMessage(C::colorize((string) $lines[$i]));
        }

        if ($page === 1) {
            $sender->sendMessage(" ");
            $sender->sendMessage(C::colorize((string) $lang->getNested($h . ".args-hint")));
        }

        if ($totalPages > 1) {
            $next = $page < $totalPages ? $page + 1 : 1;
            $pag = (string) $lang->getNested($h . ".pagination");
            $pag = str_replace(["{current}", "{total}", "{next}"], [(string) $page, (string) $totalPages, (string) $next], $pag);
            $sender->sendMessage(C::colorize($pag));
        }
        $sender->sendMessage(C::colorize($footerStr));
    }

    public function getUsage(): string {
        return Loader::getInstance()->getLanguageManager()->getNested("commands.main.unknown-command");
    }
    public function getPermission(): string {
        return 'martianenchantments.default';
    }
}
