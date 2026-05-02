<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\listeners;

use ecstsy\MartianEnchantments\triggers\GenericTrigger;
use ecstsy\MartianEnchantments\triggers\HeldTrigger;
use ecstsy\MartianEnchantments\utils\NumericExpr;
use ecstsy\MartianEnchantments\utils\TriggerHelper;
use ecstsy\MartianEnchantments\utils\Utils;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianEnchantments\utils\EffectTracker;
use ecstsy\MartianEnchantments\utils\EnchantEffectManager;
use ecstsy\MartianEnchantments\utils\managers\CooldownManager;
use ecstsy\MartianEnchantments\utils\managers\EnchantmentDisableManager;
use pocketmine\entity\Living;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\scheduler\ClosureTask;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;

final class EnchantmentListener implements Listener {

    use TriggerHelper;

    private Plugin $plugin;
    private EnchantEffectManager $effectManager;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->effectManager = new EnchantEffectManager();
    }

    /**
     * @param array{name?: string, level?: int, config: array<string, mixed>, applies_to?: mixed} $enchRow
     */
    private static function listHasTrigger(array $enchRow, string $triggerUppercase): bool {
        foreach ((array) ($enchRow['config']['type'] ?? []) as $t) {
            if (\strtoupper((string) $t) === $triggerUppercase) {
                return true;
            }
        }

        return false;
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();

        $player->getArmorInventory()->getListeners()->add(new CallbackInventoryListener(
            function (Inventory $inventory, int $slot, Item $oldItem): void {
                if ($inventory instanceof ArmorInventory) {
                    $this->effectManager->onArmorSlotChange($inventory, $slot, $oldItem);
                    $holder = $inventory->getHolder();
                    if ($holder instanceof Player) {
                        $this->queueEffectRefresh($holder);
                    }
                }
            },
            function (Inventory $inventory, array $oldContents): void {}
        ));

        $player->getInventory()->getListeners()->add(new CallbackInventoryListener(
            function (Inventory $inventory, int $slot, Item $oldItem): void {
                if ($inventory instanceof PlayerInventory) {
                    $this->effectManager->onInventorySlotChange($inventory, $slot, $oldItem);
                    $holder = $inventory->getHolder();
                    if ($holder instanceof Player) {
                        $this->queueEffectRefresh($holder);
                    }
                }
            },
            function (Inventory $inventory, array $oldContents): void {}
        ));

        $this->queueEffectRefresh($player);
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();

        $this->effectManager->clearPlayerState($player);
        EffectTracker::clearPlayerEffects($player);
        CooldownManager::clearEntityCooldowns($player);
        EnchantmentDisableManager::clearPlayer($player->getName());
    }

    /**
     * @priority HIGHEST
     */
    public function onPlayerItemHeld(PlayerItemHeldEvent $event): void {
        $player = $event->getPlayer();
        $inv = $player->getInventory();

        $oldItem = $inv->getItemInHand();
        $newItem = $inv->getItem($event->getSlot());

        if (!$oldItem->isNull()) {
            $oldEnchantments = Utils::extractEnchantmentsFromItems([$oldItem]);
            foreach ($oldEnchantments as $enchantment) {
                if (in_array("HELD", array_map('strtoupper', $enchantment['config']['type'] ?? []))) {
                    EffectTracker::removeEnchantmentEffects($player, $enchantment['name']);
                }
            }
        }

        if (!$newItem->isNull()) {
            $newEnchantments = Utils::extractEnchantmentsFromItems([$newItem]);
            if (!empty($newEnchantments)) {
                (new HeldTrigger())->execute($player, null, $newEnchantments, "HELD", []);
            }
        }

        $this->queueEffectRefresh($player);
    }

    /**
     * @priority HIGHEST
     */
    public function onInventoryTransaction(InventoryTransactionEvent $event): void {
        $transaction = $event->getTransaction();
        $player = $transaction->getSource();
        if (!$player instanceof Player) return;

        foreach ($transaction->getActions() as $action) {
            if ($action instanceof SlotChangeAction) {
                $inv = $action->getInventory();
                if ($inv instanceof ArmorInventory) {
                    $this->effectManager->onArmorSlotChange($inv, $action->getSlot(), $action->getSourceItem());
                }
            }
        }

        $this->queueEffectRefresh($player);
    }

    public function onPlayerItemUse(PlayerItemUseEvent $event): void {
        $this->queueEffectRefresh($event->getPlayer());
    }

    private function queueEffectRefresh(Player $player): void {
        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player): void {
            if (!$player->isOnline()) {
                return;
            }

            $this->effectManager->refreshPlayerEffects($player);
        }), 1);
    }

    /**
     * @priority HIGHEST
     */
    public function onPlayerAttack(EntityDamageByEntityEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }

        $attacker = $event->getDamager();
        $victim = $event->getEntity();
    
        if (!$attacker instanceof Player || !$victim instanceof Player || $attacker->getInventory()->getItemInHand()->isNull()) {
            return;
        }
    
        $item = $attacker->getInventory()->getItemInHand();
        $enchantments = Utils::extractEnchantmentsFromItems([$item]);
    
        if (empty($enchantments)) {
            return;
        }
    
        $filtered = [];
        foreach ($enchantments as $cfg) {
            if (self::listHasTrigger($cfg, 'ATTACK')) {
                $filtered[] = $cfg;
            }
        }

        if ($filtered !== []) {
            (new GenericTrigger())->execute($attacker, $victim, $filtered, 'ATTACK', []);
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onAttackMob(EntityDamageByEntityEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }
        
        $attacker = $event->getDamager();
        $victim = $event->getEntity();
        
        if (!$attacker instanceof Player || $victim instanceof Player || $attacker->getInventory()->getItemInHand()->isNull()) {
            return;
        }    
        
        $item = $attacker->getInventory()->getItemInHand();
        $enchantments = Utils::extractEnchantmentsFromItems([$item]);

        if (empty($enchantments)) {
            return;
        }
        
        $filtered = [];
        foreach ($enchantments as $cfg) {
            if (self::listHasTrigger($cfg, 'ATTACK_MOB')) {
                $filtered[] = $cfg;
            }
        }

        if ($filtered !== []) {
            (new GenericTrigger())->execute($attacker, $victim, $filtered, 'ATTACK_MOB', []);
        }
    }
    
    /**
     * @priority HIGHEST
     */
    public function onPlayerDefend(EntityDamageByEntityEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        $attacker = $event->getDamager();

        if (!$victim instanceof Living) {
            return;
        }

        $armorItems = $victim->getArmorInventory()->getContents();
        $enchantments = Utils::extractEnchantmentsFromItems($armorItems);

        if (empty($enchantments)) {
            return;
        }

        $filtered = [];
        foreach ($enchantments as $cfg) {
            if (self::listHasTrigger($cfg, 'DEFENSE')) {
                $filtered[] = $cfg;
            }
        }

        if ($filtered !== []) {
            (new GenericTrigger())->execute($attacker, $victim, $filtered, 'DEFENSE', []);
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onMobDefend(EntityDamageByEntityEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }
    
        $victim = $event->getEntity();
        $attacker = $event->getDamager();
    
        if (!$victim instanceof Living) {
            return;
        }
    
        if ($attacker instanceof Living) {
            $armorItems = $victim->getArmorInventory()->getContents();
            $enchantments = Utils::extractEnchantmentsFromItems($armorItems);
    
            if (empty($enchantments)) {
                return;
            }
    
            $filtered = [];
            foreach ($enchantments as $cfg) {
                if (self::listHasTrigger($cfg, 'DEFENSE_MOB')) {
                    $filtered[] = $cfg;
                }
            }

            if ($filtered !== []) {
                (new GenericTrigger())->execute($attacker, $victim, $filtered, 'DEFENSE_MOB', []);
            }
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onAnyProjectileHit(ProjectileHitEntityEvent $event): void {
        $projectile = $event->getEntity();
        $victim = $event->getEntityHit();
        $shooter = $projectile->getOwningEntity();

        if (!$projectile instanceof Projectile) {
            return;
        }

        if (!$victim instanceof Living) {
            return;
        }

        if (!$shooter instanceof Player) {
            return;
        }

        $armorItems = $victim->getArmorInventory()->getContents();
        $enchants = Utils::extractEnchantmentsFromItems($armorItems);

        $filtered = [];
        foreach ($enchants as $cfg) {
            if (self::listHasTrigger($cfg, 'DEFENSE_PROJECTILE')) {
                $filtered[] = $cfg;
            }
        }

        if ($filtered !== []) {
            (new GenericTrigger())->execute($shooter, $victim, $filtered, 'DEFENSE_PROJECTILE', []);
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onPlayerEat(PlayerItemConsumeEvent $event): void {
        $player = $event->getPlayer();

        $armorItems = $player->getArmorInventory()->getContents();
        $enchantments = Utils::extractEnchantmentsFromItems($armorItems);

        if (empty($enchantments)) {
            return;
        }

        $filtered = [];

        foreach ($enchantments as $cfg) {
            if (self::listHasTrigger($cfg, 'EAT')) {
                $filtered[] = $cfg;
            }
        }

        if ($filtered !== []) {
            (new GenericTrigger())->execute($player, null, $filtered, 'EAT', []);
        }
    }
    
    /**
     * @priority HIGHEST
     */
    public function onEntityMiscDamage(EntityDamageEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }

        $entity = $event->getEntity();
        if (!$entity instanceof Living) {
            return;
        }

        $cause = $event->getCause();
        $armorItems = $entity->getArmorInventory()->getContents();

        $triggers = [
            "FALL_DAMAGE" => EntityDamageEvent::CAUSE_FALL,
            "EXPLOSION" => [EntityDamageEvent::CAUSE_BLOCK_EXPLOSION, EntityDamageEvent::CAUSE_ENTITY_EXPLOSION],
            "FIRE" => [EntityDamageEvent::CAUSE_FIRE, EntityDamageEvent::CAUSE_FIRE_TICK],
        ];

        foreach ($triggers as $trigger => $expected) {
            $matchesCause = is_array($expected)
                ? in_array($cause, $expected, true)
                : $cause === $expected;
            if (!$matchesCause) {
                continue;
            }

            $allEnchants = Utils::extractEnchantmentsFromItems($armorItems);
            $toTrigger = [];
            /** @var string $triggerUpper */
            $triggerUpper = \strtoupper((string) $trigger);

            foreach ($allEnchants as $enchant) {
                if (!self::listHasTrigger($enchant, $triggerUpper)) {
                    continue;
                }

                $level = (int) ($enchant['level'] ?? 1);
                /** @var array<string, mixed> $levelCfg */
                $levelCfg = Utils::resolveLevelSlice((array) ($enchant['config']['levels'] ?? []), $level) ?? [];
                $name = \strtolower((string) ($enchant['name'] ?? 'unknown'));
                $vars = ['level' => $level];
                $chancePct = (int) \round(NumericExpr::chancePercent($levelCfg['chance'] ?? 100, $vars, 100));

                foreach ((array) ($levelCfg['effects'] ?? []) as $effect) {
                    if (($effect['type'] ?? '') !== "CANCEL_EVENT") {
                        continue;
                    }

                    if (CooldownManager::isOnCooldown($entity, $name)) {
                        continue;
                    }

                    if (\mt_rand(1, 100) > $chancePct) {
                        continue;
                    }

                    $condsRaw = $effect['conditions'] ?? [];
                    $condsMet = true;
                    $ctx = [
                        'enchant-name' => $name,
                        'enchant-level' => $level,
                        'chance' => $chancePct,
                    ];
                    if (!empty($condsRaw)) {
                        if (isset($condsRaw['type'])) {
                            $condsMet = $this->handleConditions((array) $condsRaw, $entity, null, $triggerUpper, $ctx);
                        } else {
                            foreach ((array) $condsRaw as $c) {
                                if (!\is_array($c)) {
                                    continue;
                                }
                                if (!$this->handleConditions($c, $entity, null, $triggerUpper, $ctx)) {
                                    $condsMet = false;
                                    break;
                                }
                            }
                        }
                    }

                    if ($condsMet) {
                        $event->cancel();
                        $cd = (int) \round(NumericExpr::evaluate($levelCfg['cooldown'] ?? 0, $vars, 0));
                        CooldownManager::setCooldown($entity, $name, $cd);

                        return;
                    }
                }

                $toTrigger[] = $enchant;
            }

            if ($toTrigger !== []) {
                (new GenericTrigger())->execute($entity, null, $toTrigger, $triggerUpper, []);
            }
        }
    }
            
    /**
     * @priority HIGHEST
     */
    public function onEntityDamageModification(EntityDamageByEntityEvent $event): void {
        $victim = $event->getEntity();
        $attacker = $event->getDamager();
    
        if ($event->isCancelled()) {
            return;
        }
    
        if (!$victim instanceof Living || !$attacker instanceof Player) {
            return;
        }
    
        $config = GeneralUtils::getConfiguration($this->plugin, "enchantments.yml");
        $armorItems = $victim->getArmorInventory()->getContents();
        $weapon = $attacker->getInventory()->getItemInHand();
    
        $defenseEffects = Utils::getEffectsFromItems($armorItems, "DEFENSE", $config);
        $defenseConditions = Utils::getConditionsFromItems($armorItems, "DEFENSE", $config);
    
        foreach ($defenseEffects as $effectGroup) {
            foreach ($effectGroup as $effect) {
                if ($effect['type'] === "DECREASE_DAMAGE") {
                    foreach ($defenseConditions as $conditionGroup) {
                        foreach ($conditionGroup as $condition) {
                            $chance = $effectGroup['chance'] ?? 100;
                            $extraData = ['chance' => $chance];
                            $conditionsMet = $this->handleConditions($condition, $attacker, $victim, "DEFENSE", $extraData);
    
                            if ($conditionsMet) {
                                $finalDamage = $event->getFinalDamage();
                                $percentageReduction = $effect['amount'] ?? 0;
                                $damageReduction = $finalDamage * ($percentageReduction / 100);
    
                                $event->setBaseDamage($event->getBaseDamage() - $damageReduction);
                            }
                        }
                    }
                }
            }
        }
    
        if (!$weapon->isNull()) {
            $attackEffects = Utils::getEffectsFromItems([$weapon], "ATTACK", $config);
            $attackConditions = Utils::getConditionsFromItems([$weapon], "ATTACK", $config);
    
            foreach ($attackEffects as $effectGroup) {
                foreach ($effectGroup as $effect) {
                    if ($effect['type'] === "INCREASE_DAMAGE") {
                        foreach ($attackConditions as $conditionGroup) {
                            foreach ($conditionGroup as $condition) {
                                $chance = $effectGroup['chance'] ?? 100;
                                $extraData = ['chance' => $chance];
                                $conditionsMet = $this->handleConditions($condition, $attacker, $victim, "ATTACK", $extraData);
    
                                if ($conditionsMet) {
                                    $finalDamage = $event->getFinalDamage();
                                    $percentageIncrease = $effect['amount'] ?? 0;
                                    $damageIncrease = $finalDamage * ($percentageIncrease / 100);
    
                                    $event->setBaseDamage($event->getBaseDamage() + $damageIncrease);
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
    /**
     * @priority HIGHEST
     */
    public function onArrowHit(ProjectileHitEntityEvent $event): void {
        $projectile = $event->getEntity();
        $hitEntity = $event->getEntityHit();
        $shooter = $projectile->getOwningEntity();

        if (!$projectile instanceof Arrow || !$shooter instanceof Player || !$hitEntity instanceof Living) {
            return;
        }

        $bow = $shooter->getInventory()->getItemInHand();
        if (!$bow->isNull()) {
            Utils::handleArrowHitEnchants($shooter, $hitEntity, [$bow]);
        } 

        $armorItems = $hitEntity->getArmorInventory()->getContents();
        Utils::handleArrowHitEnchants($shooter, $hitEntity, $armorItems);
    }

    /**
     * @priority HIGHEST
     */
    public function onEntityDeath(EntityDeathEvent $event): void {
        $victim = $event->getEntity();

        if (!$victim instanceof Living || $victim->isAlive()) {
            return;
        }

        if (!$victim instanceof Player) {
            CooldownManager::clearEntityCooldowns($victim);
        }

        $cause = $victim->getLastDamageCause();
        $attacker = null;

        if ($cause instanceof EntityDamageByEntityEvent) {
            $damager = $cause->getDamager();
            if ($damager instanceof Player) {
                $attacker = $damager;
            }
        }

        $victimItems = [];
        if ($victim instanceof Player) {
            $victimItems[] = $victim->getInventory()->getItemInHand();
        }
        $victimItems = array_merge($victimItems, $victim->getArmorInventory()->getContents());

        $victimEnchants = [];
        foreach (Utils::extractEnchantmentsFromItems($victimItems) as $cfg) {
            if (self::listHasTrigger($cfg, 'DEATH')) {
                $victimEnchants[] = $cfg;
            }
        }

        if ($victimEnchants !== []) {
            (new GenericTrigger())->execute($victim, $attacker, $victimEnchants, "DEATH");
        }

        if ($attacker instanceof Player) {
            $attackerItems = [$attacker->getInventory()->getItemInHand()];
            $attackerEnchants = [];
            foreach (Utils::extractEnchantmentsFromItems($attackerItems) as $cfg) {
                if (self::listHasTrigger($cfg, 'DEATH')) {
                    $attackerEnchants[] = $cfg;
                }
            }

            if ($attackerEnchants !== []) {
                (new GenericTrigger())->execute($attacker, $victim, $attackerEnchants, "DEATH");
            }
        }
    }
}
