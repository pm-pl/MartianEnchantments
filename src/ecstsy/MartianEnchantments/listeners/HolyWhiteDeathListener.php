<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\listeners;

use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianEnchantments\Loader;
use ecstsy\MartianEnchantments\utils\ItemApplyHelper;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as C;

/**
 * Optional death-save for Holy White Scroll ({@code items.holywhitescroll.settings.keep-after-death}).
 */
final class HolyWhiteDeathListener implements Listener {

    /** @var array<string, list<\pocketmine\item\Item>> */
    private static array $queued = [];

    /**
     * @priority HIGH
     */
    public function onDeath(PlayerDeathEvent $event): void {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");
        if (!($config instanceof Config)) {
            return;
        }

        if (!(bool) $config->getNested("items.holywhitescroll.settings.keep-after-death", false)) {
            return;
        }

        $player = $event->getPlayer();
        $drops = $event->getDrops();
        if ($drops === []) {
            return;
        }

        $kept = [];
        $rest = [];

        foreach ($drops as $drop) {
            if (self::tryConsumeHolySave($drop, $config)) {
                $kept[] = $drop;
                continue;
            }
            $rest[] = $drop;
        }

        if ($kept !== []) {
            $key = $player->getUniqueId()->toString();
            self::$queued[$key] = [...(self::$queued[$key] ?? []), ...$kept];
            $event->setDrops($rest);

            $msg = Loader::getInstance()->getLanguageManager()->getNested("items.apply.holy-death-kept");
            if (is_string($msg) && $msg !== "" && !str_starts_with($msg, "Translation not found:")) {
                $player->sendMessage(C::colorize($msg));
            }
        }
    }

    public function onRespawn(PlayerRespawnEvent $event): void {
        $player = $event->getPlayer();
        $key = $player->getUniqueId()->toString();
        $items = self::$queued[$key] ?? [];
        unset(self::$queued[$key]);
        if ($items === []) {
            return;
        }

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player, $items): void {
            if (!$player->isConnected()) {
                return;
            }

            foreach ($items as $it) {
                if ($player->getInventory()->canAddItem($it)) {
                    $player->getInventory()->addItem($it);
                } else {
                    $player->getWorld()->dropItem($player->getPosition()->add(0, 0.5, 0), $it);
                }
            }
        }), 1);
    }

    /**
     * If this Holy-primed Martian gear should survive Death drops, decrement primed/consumed state and return true (keep processed item in kept list).
     */
    private static function tryConsumeHolySave(\pocketmine\item\Item $item, Config $config): bool {
        $root = $item->getNamedTag();
        $m = $root->getCompoundTag("MartianEnchantments");
        if ($m === null) {
            return false;
        }

        $hasPrimedTag = $m->getTag("holyWhitePrimed") !== null;
        $primedVal = $m->getInt("holyWhitePrimed", 0);
        $legacyHoly = !$hasPrimedTag && $m->getInt("holyWhiteScroll", 0) > 0;
        if ($primedVal !== 1 && !$legacyHoly) {
            return false;
        }

        $maxDefault = max(1, (int) $config->getNested("items.holywhitescroll.settings.max-death-saves", 3));
        $max = max(1, $m->getInt("holyWhiteDeathMax", $maxDefault));
        $used = max(0, $m->getInt("holyWhiteDeathUsed", 0));

        if ($used >= $max) {
            return false;
        }

        $used++;

        $m->setInt("holyWhiteDeathUsed", $used);
        $m->setInt("holyWhitePrimed", 0);
        if ($used >= $max) {
            $m->setInt("holyWhiteCorrupted", 1);
        } else {
            $m->removeTag("holyWhiteCorrupted");
        }

        $root->setTag("MartianEnchantments", $m);
        $item->setNamedTag($root);

        ItemApplyHelper::stripHolyWhiteScrollLoreLine($item, $config);

        return true;
    }
}
