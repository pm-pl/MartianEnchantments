<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\commands\subcommands;

use ecstsy\MartianEnchantments\armor\ArmorSetDefinition;
use ecstsy\MartianEnchantments\armor\ArmorSetItemBuilder;
use ecstsy\MartianEnchantments\armor\ArmorSetRegistry;
use ecstsy\MartianEnchantments\libs\CortexPE\Commando\args\RawStringArgument;
use ecstsy\MartianEnchantments\libs\CortexPE\Commando\BaseSubCommand;
use ecstsy\MartianEnchantments\Loader;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\utils\TextFormat as C;

final class GiveArmorSetSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->setPermissionMessage(Loader::getInstance()->getLanguageManager()->getNested("commands.no-permission"));
        $this->registerArgument(0, new RawStringArgument("player", false));
        $this->registerArgument(1, new RawStringArgument("set", false));
        $this->registerArgument(2, new RawStringArgument("piece", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $lang = Loader::getInstance()->getLanguageManager();

        $targetName = isset($args["player"]) ? (string) $args["player"] : "";
        $setId = isset($args["set"]) ? strtolower(trim((string) $args["set"])) : "";
        $piece = isset($args["piece"]) ? strtolower(trim((string) $args["piece"])) : "";

        $player = PlayerUtils::getPlayerByPrefix($targetName);
        if ($player === null || !$player->isOnline()) {
            $sender->sendMessage(C::colorize((string) $lang->getNested("commands.offline-player")));

            return;
        }

        if ($setId === "" || $piece === "") {
            $sender->sendMessage(C::colorize((string) $lang->getNested("commands.main.givearmorset.usage-one-liner")));

            return;
        }

        $def = ArmorSetRegistry::get($setId);
        if ($def === null) {
            $sender->sendMessage(C::colorize(str_replace(
                "{set}",
                $setId,
                (string) $lang->getNested("commands.main.givearmorset.unknown-set", "&cUnknown armor set: &f{set}")
            )));

            return;
        }

        $items = $this->resolveItems($def, $piece);
        if ($items === []) {
            $sender->sendMessage(C::colorize((string) $lang->getNested("commands.main.givearmorset.bad-piece")));

            return;
        }

        foreach ($items as $it) {
            if ($player->getInventory()->canAddItem($it)) {
                $player->getInventory()->addItem($it);
            } else {
                $player->getWorld()->dropItem($player->getPosition()->add(0, 0.5, 0), $it);
            }
        }

        $sender->sendMessage(C::colorize(str_replace(
            ["{player}", "{set}", "{piece}"],
            [$player->getName(), $def->id, $piece],
            (string) $lang->getNested("commands.main.givearmorset.success")
        )));
        PlayerUtils::playSound($sender, "random.orb");
    }

    /**
     * @return list<Item>
     */
    private function resolveItems(ArmorSetDefinition $def, string $piece): array {
        if ($piece === "full") {
            $out = [];
            foreach (["helmet", "chestplate", "leggings", "boots"] as $slot) {
                $p = $def->pieces[$slot] ?? null;
                if (\is_array($p)) {
                    $out[] = ArmorSetItemBuilder::createArmorPiece($def, $slot, $p);
                }
            }

            return $out;
        }

        if ($piece === "all") {
            $out = $this->resolveItems($def, "full");
            foreach ($def->weapons as $key => $w) {
                if (\is_array($w)) {
                    $out[] = ArmorSetItemBuilder::createWeapon((string) $key, $def, $w);
                }
            }

            return $out;
        }

        if (\in_array($piece, ["helmet", "chestplate", "leggings", "boots"], true)) {
            $p = $def->pieces[$piece] ?? null;

            return \is_array($p) ? [ArmorSetItemBuilder::createArmorPiece($def, $piece, $p)] : [];
        }

        $w = $def->weapons[$piece] ?? null;
        if (\is_array($w)) {
            return [ArmorSetItemBuilder::createWeapon($piece, $def, $w)];
        }

        return [];
    }

    public function getPermission(): string {
        return "martianenchantments.asets-give";
    }
}
