<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\listeners;

use ecstsy\MartianEnchantments\Loader;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\entity\Living;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as C;

/**
 * StatTrak / MobTrak / BlockTrak and soul-kill line updates.
 */
final class TrakListener implements Listener {

    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();
        if ($item->isNull()) {
            return;
        }
        if (!self::itemTrackType($item, "blocktrak")) {
            return;
        }
        $this->incrementTrack($player, $item, "blocktrak");
    }

    public function onEntityDeath(EntityDeathEvent $event): void {
        $victim = $event->getEntity();
        if (!($victim instanceof Living)) {
            return;
        }

        $attacker = $this->getPlayerKiller($victim);
        if ($attacker === null) {
            return;
        }

        if ($victim === $attacker) {
            return;
        }

        $item = $attacker->getInventory()->getItemInHand();
        if ($item->isNull()) {
            return;
        }

        $tt = $item->getNamedTag()->getCompoundTag("MartianEnchantments")?->getString("trackType", "") ?? "";
        if ($tt === "stattrak") {
            $this->incrementTrack($attacker, $item, "stattrak");
        } elseif ($tt === "mobtrak" && !($victim instanceof Player)) {
            $this->incrementTrack($attacker, $item, "mobtrak");
        }

        $this->incrementSoulsKilled($attacker);
    }

    private function getPlayerKiller(Living $victim): ?Player {
        $cause = $victim->getLastDamageCause();
        if (!($cause instanceof EntityDamageByEntityEvent)) {
            return null;
        }
        $damager = $cause->getDamager();
        if ($damager instanceof Projectile) {
            $owner = $damager->getOwningEntity();
            return $owner instanceof Player ? $owner : null;
        }
        if ($damager instanceof Player) {
            return $damager;
        }

        return null;
    }

    private static function itemTrackType(Item $item, string $want): bool {
        $m = $item->getNamedTag()->getCompoundTag("MartianEnchantments");
        return $m !== null && $m->getString("trackType", "") === $want;
    }

    private function incrementTrack(Player $player, Item $item, string $type): void {
        $m = $item->getNamedTag()->getCompoundTag("MartianEnchantments");
        if ($m === null || $m->getString("trackType", "") !== $type) {
            return;
        }
        $n = $m->getInt("trackCount", 0) + 1;
        $m->setInt("trackCount", $n);
        $item->getNamedTag()->setTag("MartianEnchantments", $m);
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");
        if ($config instanceof Config) {
            self::rewriteTrakLoreLine($item, $n, $type, $config);
        }
        $player->getInventory()->setItemInHand($item);
    }

    public static function rewriteTrakLoreLine(Item $item, int $count, string $trakType, Config $config): void {
        $lineTemplate = (string) $config->getNested("items.{$trakType}.settings.lore-display", "");
        if ($lineTemplate === "") {
            return;
        }
        $keyword = match ($trakType) {
            "stattrak" => "StatTrak",
            "mobtrak" => "MobTrak",
            "blocktrak" => "BlockTrak",
            default => "Trak",
        };
        $lore = $item->getLore();
        $lore = array_values(array_filter($lore, static function (string $L) use ($keyword): bool {
            return C::clean($L) === "" || !str_contains(C::clean($L), $keyword);
        }));
        $lore[] = C::colorize(str_replace("{stats}", (string) $count, $lineTemplate));
        $item->setLore($lore);
    }

    private function incrementSoulsKilled(Player $attacker): void {
        $item = $attacker->getInventory()->getItemInHand();
        if ($item->isNull()) {
            return;
        }
        $m = $item->getNamedTag()->getCompoundTag("MartianEnchantments");
        if ($m === null || $m->getInt("soulTrack", 0) < 1) {
            return;
        }
        $n = $m->getInt("soulsKilled", 0) + 1;
        $m->setInt("soulsKilled", $n);
        $item->getNamedTag()->setTag("MartianEnchantments", $m);
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");
        if (!($config instanceof Config)) {
            $attacker->getInventory()->setItemInHand($item);
            return;
        }
        $line = (string) $config->getNested("settings.souls.lore", "");
        if ($line !== "") {
            $line = C::colorize(str_replace("{souls}", (string) $n, $line));
            $lore = $item->getLore();
            $lore = array_values(array_filter($lore, static function (string $L): bool {
                return C::clean($L) === "" || !str_contains(C::clean($L), "Souls Collected");
            }));
            $lore[] = $line;
            $item->setLore($lore);
        }
        $attacker->getInventory()->setItemInHand($item);
    }
}
