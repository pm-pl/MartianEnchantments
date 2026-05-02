<?php

namespace ecstsy\MartianEnchantments\utils;

use pocketmine\entity\Entity;

interface TriggerInterface {
    public function execute(Entity $attacker, ?Entity $victim, array $enchantments, string $context, array $exteraData = []): void ;
}