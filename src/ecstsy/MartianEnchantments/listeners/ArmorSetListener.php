<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\listeners;

use ecstsy\MartianEnchantments\armor\ArmorSetEffectType;
use ecstsy\MartianEnchantments\armor\ArmorSetManager;
use ecstsy\MartianEnchantments\armor\ArmorSetRegistry;
use ecstsy\MartianEnchantments\Loader;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

/**
 * Config-driven armor set passives (damage multipliers, weapon hooks) and optional activation messages.
 */
final class ArmorSetListener implements Listener {

    /** @var array<string, string|null> player lowercase name => active set id */
    private array $activeSets = [];

    public function onJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();

        $onSlot = function (Inventory $inventory, int $slot, Item $oldItem): void {
            if (!$inventory instanceof ArmorInventory) {
                return;
            }

            $holder = $inventory->getHolder();
            if ($holder instanceof Player) {
                $this->checkArmorSetActivation($holder);
            }
        };

        $player->getArmorInventory()->getListeners()->add(new CallbackInventoryListener(
            $onSlot,
            function (Inventory $inventory, array $oldContents) use ($onSlot): void {
                if (!$inventory instanceof ArmorInventory) {
                    return;
                }

                foreach ($oldContents as $slot => $oldItem) {
                    if (!$oldItem->equals($inventory->getItem((int) $slot), false)) {
                        $onSlot($inventory, (int) $slot, $oldItem);
                    }
                }
            }
        ));

        $this->checkArmorSetActivation($player);
    }

    public function onQuit(PlayerQuitEvent $event): void {
        unset($this->activeSets[strtolower($event->getPlayer()->getName())]);
    }

    public function onDamageByEntity(EntityDamageByEntityEvent $event): void {
        $victim = $event->getEntity();
        $damager = $event->getDamager();

        if ($damager instanceof Player) {
            $setId = ArmorSetManager::getFullSet($damager);
            if ($setId !== null && !ArmorSetManager::isSetDisabledInWorld($damager, $setId)) {
                $def = ArmorSetRegistry::get($setId);
                if ($def !== null) {
                    $mult = 1.0;
                    foreach ($def->attackerFullSet as $row) {
                        if ($row["type"] === ArmorSetEffectType::OUTGOING_DAMAGE_BONUS) {
                            $mult += $row["amount"];
                        }
                    }

                    if (ArmorSetManager::heldWeaponMatchesSet($damager, $setId)) {
                        $heldKey = ArmorSetManager::readMartianItemKey($damager->getInventory()->getItemInHand());
                        if ($heldKey !== null) {
                            $list = $def->weaponBonuses[$heldKey] ?? [];
                            foreach ($list as $row) {
                                if ($row["type"] === ArmorSetEffectType::OUTGOING_DAMAGE_BONUS) {
                                    $mult += $row["amount"];
                                }
                                if (
                                    $row["type"] === ArmorSetEffectType::VICTIM_ARMOR_DURABILITY_DAMAGE
                                    && $victim instanceof Player
                                ) {
                                    $this->damageVictimArmor($victim, max(1, (int) round($row["amount"])));
                                }
                            }
                        }
                    }

                    if ($mult !== 1.0) {
                        $event->setBaseDamage($event->getBaseDamage() * $mult);
                    }
                }
            }
        }

        if ($victim instanceof Player) {
            $vSet = ArmorSetManager::getFullSet($victim);
            if ($vSet !== null && !ArmorSetManager::isSetDisabledInWorld($victim, $vSet)) {
                $def = ArmorSetRegistry::get($vSet);
                if ($def !== null) {
                    $mult = 1.0;
                    foreach ($def->defenderFullSet as $row) {
                        if ($row["type"] === ArmorSetEffectType::INCOMING_DAMAGE_REDUCTION) {
                            $amt = max(0.0, min(1.0, $row["amount"]));
                            $mult *= (1.0 - $amt);
                        }
                    }
                    if ($mult !== 1.0) {
                        $event->setBaseDamage($event->getBaseDamage() * $mult);
                    }
                }
            }
        }
    }

    private function damageVictimArmor(Player $victim, int $amount): void {
        $armorInventory = $victim->getArmorInventory();
        foreach ($armorInventory->getContents() as $slot => $piece) {
            if (!$piece instanceof Durable || $piece->isNull()) {
                continue;
            }
            $max = $piece->getMaxDurability();
            if ($max <= 0) {
                continue;
            }
            $piece->setDamage(min($max, $piece->getDamage() + $amount));
            $armorInventory->setItem((int) $slot, $piece);
        }
    }

    private function checkArmorSetActivation(Player $player): void {
        $key = strtolower($player->getName());
        $previous = $this->activeSets[$key] ?? null;
        $current = ArmorSetManager::getFullSet($player);
        if ($previous === $current) {
            return;
        }

        $lang = Loader::getInstance()->getLanguageManager();

        if ($previous !== null) {
            $msg = (string) $lang->getNested("armor-sets.deactivated", "&c{set} set bonus &fdeactivated.");
            $display = $this->formatSetDisplay($previous);
            $player->sendMessage(C::colorize(str_replace("{set}", $display, $msg)));
        }

        if ($current !== null) {
            $msg = (string) $lang->getNested("armor-sets.activated", "&a{set} set bonus &aactivated!");
            $display = $this->formatSetDisplay($current);
            $player->sendMessage(C::colorize(str_replace("{set}", $display, $msg)));
        }

        $this->activeSets[$key] = $current;
    }

    private function formatSetDisplay(string $setId): string {
        $def = ArmorSetRegistry::get($setId);

        return $def !== null ? C::clean(C::colorize($def->displayName)) : $setId;
    }
}
