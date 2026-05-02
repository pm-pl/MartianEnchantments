<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\enchantments;

final class CustomEnchantmentInstance {

    private CustomEnchantment $enchantment;
    private int $level;

    public function __construct(CustomEnchantment $enchantment, int $level) {
        $this->enchantment = $enchantment;
        $this->level = $level;
    }

    public function getEnchantment(): CustomEnchantment {
        return $this->enchantment;
    }

    public function getLevel(): int {
        return $this->level;
    }
    
    public function getLoreLine(): string {
        return $this->enchantment->getLoreLine($this->level);
    }
}
