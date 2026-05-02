<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\enchantments;

use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\item\enchantment\ItemEnchantmentTagRegistry;
use pocketmine\item\Item;

final class CustomEnchantment {

    private string $name;
    private int $rarity;
    private string $description;
    private int $maxLevel;

    /** @var string[] */
    private array $tags;

    public function __construct(
        string $name,
        int $rarity,
        string $description,
        int $maxLevel,
        array $tags = []
    ) {
        $this->name = $name;
        $this->rarity = $rarity;
        $this->description = $description;
        $this->maxLevel = $maxLevel;
        $this->tags = $tags;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getRarity(): int {
        return $this->rarity;
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function getMaxLevel(): int {
        return $this->maxLevel;
    }

    /**
     * Lore line used on items
     */
    public function getLoreLine(int $level): string {
        $color = Groups::translateGroupToColor($this->rarity);
        $group = Groups::getGroupName($this->rarity);

        return $color . $group . " " . GeneralUtils::getRomanNumeral($level);
    }

    /**
     * Raw enchantment tags (ALL_SWORD, ALL_ARMOR, etc.)
     *
     * @return string[]
     */
    public function getTags(): array {
        return $this->tags;
    }

    /**
     * Alias for readability
     *
     * @return string[]
     */
    public function getApplicableItems(): array {
        return $this->tags;
    }

    /**
     * Determines if this enchantment can be applied to an item
     */
    public function matches(Item $item): bool {
        if ($this->tags === []) {
            return true;
        }

        return ItemEnchantmentTagRegistry::getInstance()->isTagArrayIntersection($item->getEnchantmentTags(), $this->tags);
    }
}
