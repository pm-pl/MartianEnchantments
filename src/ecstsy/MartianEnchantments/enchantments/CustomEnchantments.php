<?php

namespace ecstsy\MartianEnchantments\enchantments;

use ecstsy\MartianEnchantments\Loader;
use ecstsy\MartianEnchantments\utils\Utils;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianEnchantments\libs\muqsit\simplepackethandler\SimplePacketHandler;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\event\EventPriority;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\ItemEnchantmentTags;
use pocketmine\item\enchantment\ItemFlags;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\InventoryContentPacket;
use pocketmine\network\mcpe\protocol\InventorySlotPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\utils\RegistryTrait;
use pocketmine\utils\TextFormat;

final class CustomEnchantments {
    use RegistryTrait;

    /** @var array<string, int|string> id map */
    public static array $ids = [];

    /** @var array<int, list<string>> rarity id → enchant keys */
    public static array $rarities = [];

    /** @var array<string, CustomEnchantment> keyed by uppercase id */
    public static array $enchants = [];

    protected static function setup(): void {
        SimplePacketHandler::createInterceptor(Loader::getInstance(), EventPriority::HIGH)
            ->interceptOutgoing(function (InventoryContentPacket $pk, NetworkSession $destination): bool {
                foreach ($pk->items as $i => $item) {
                    $pk->items[$i] = new ItemStackWrapper($item->getStackId(), self::display($item->getItemStack()));
                }
                return true;
            })
            ->interceptOutgoing(function (InventorySlotPacket $pk, NetworkSession $destination): bool {
                $pk->item = new ItemStackWrapper($pk->item->getStackId(), self::display($pk->item->getItemStack()));
                return true;
            })
            ->interceptOutgoing(function (InventoryTransactionPacket $pk, NetworkSession $destination): bool {
                $transaction = $pk->trData;
    
                foreach ($transaction->getActions() as $action) {
                    $action->oldItem = new ItemStackWrapper($action->oldItem->getStackId(), self::filter($action->oldItem->getItemStack()));
                    $action->newItem = new ItemStackWrapper($action->newItem->getStackId(), self::filter($action->newItem->getItemStack()));
                }
                return true;
            });
    
        EnchantmentIdMap::getInstance()->register(Utils::FAKE_ENCH_ID, new Enchantment("", -1, 1, ItemFlags::ALL, ItemFlags::NONE));

        self::registerEnchantments(); 
    }
    
    protected static function register(string $name, CustomEnchantment $enchantment): void {
        $key = strtoupper($name);
        
        self::$enchants[$key] = $enchantment;
        
        self::$rarities[$enchantment->getRarity()][] = $key;
        self::_registryRegister($name, $enchantment);
    }
    
    public static function getIdFromName(string $name) : ?int {
        return self::$ids[$name] ?? null;
    }

    public static function getAll() : array{
        /**
         * @var CustomEnchantment[] $result
         * @phpstan-var array<string, CustomEnchantment> $result
         */
        $result = self::_registryGetAll();
        return $result;
    }

    public static function display(ItemStack $itemStack): ItemStack {
        $item = TypeConverter::getInstance()->netItemStackToCore($itemStack);
        $root = $item->getNamedTag();
        $martianCES = $root->getCompoundTag("MartianCES");

        $loreEnchantLines = [];

        if ($martianCES !== null) {
            foreach ($martianCES->getValue() as $enchantName => $levelTag) {
                $key = strtoupper($enchantName);
                if (!isset(self::getAll()[$key])) {
                    continue;
                }
                $enchantment = self::getEnchantmentByName($enchantName);
                if ($enchantment instanceof CustomEnchantment) {
                    $level = $levelTag->getValue();
                    $groupId = $enchantment->getRarity();
                    $color = Groups::translateGroupToColor($groupId);
                    $displayName = self::getEnchantmentDisplayName($enchantment->getName(), $color);
                    $roman = GeneralUtils::getRomanNumeral($level);
                    $loreEnchantLines[] = TextFormat::RESET . TextFormat::colorize($displayName)
                        . TextFormat::RESET . TextFormat::WHITE . ' ' . $roman;
                }
            }

            self::preserveOriginalDisplayTag($item);

            $existingLore = $item->getLore();
            $item->setLore(array_merge($loreEnchantLines, $existingLore));

            /* Bedrock renders extra lines glued into custom-name with `\n` as smaller “subtitle”; keep one title row only. */
            if ($item->getCustomName() !== "") {
                $item->setCustomName(TextFormat::RESET . trim((string) preg_replace('/\R+/u', ' ', $item->getCustomName())));
            } else {
                $item->setCustomName(TextFormat::RESET . TextFormat::AQUA . $item->getName());
            }
        }
    
        Utils::updateGlowEffect($item);
    
        return TypeConverter::getInstance()->coreItemStackToNet($item);
    }
    

    /**
     * Decorative glyphs that users often dislike on tooltips and that some clients render oddly.
     */
    public static function stripDisplayOrnaments(string $text): string {
        foreach (["✦", "✧", "★", "☆", "⋆", "⊹", "❖", "◆", "⚔", "⚡"] as $g) {
            $text = str_replace($g, '', $text);
        }

        return trim((string) preg_replace('/\s{2,}/u', ' ', $text));
    }

    /**
     * Retrieves the enchantment display name from the configuration.
     *
     * @param string $enchantmentName
     * @param string $color
     */
    public static function getEnchantmentDisplayName(string $enchantmentName, string $color): string {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "enchantments.yml");
        if ($config === null) {
            return self::stripDisplayOrnaments($enchantmentName);
        }

        if ($config->exists($enchantmentName)) {
            $displayName = $config->getNested($enchantmentName . ".display");
            if ($displayName !== null && is_string($displayName)) {
                return self::stripDisplayOrnaments(str_replace("{group-color}", $color, $displayName));
            }
        } else {
            $lower = strtolower($enchantmentName);
            foreach ($config->getAll() as $k => $row) {
                if (is_string($k) && strtolower($k) === $lower && is_array($row) && isset($row["display"]) && is_string($row["display"])) {
                    return self::stripDisplayOrnaments(str_replace("{group-color}", $color, $row["display"]));
                }
            }
        }

        return self::stripDisplayOrnaments($enchantmentName);
    }

    /**
     * Saves the original display tag to prevent overwriting vanilla item properties.
     * 
     * @param Item $item
     * @return void
     */
    private static function preserveOriginalDisplayTag(Item $item): void {
        $namedTag = $item->getNamedTag();
        
        if ($namedTag->getTag(Item::TAG_DISPLAY)) {
            $namedTag->setTag("OriginalDisplayTag", $namedTag->getTag(Item::TAG_DISPLAY)->safeClone());
        }
    }

    public static function filter(ItemStack $itemStack): ItemStack {
        $item = TypeConverter::getInstance()->netItemStackToCore($itemStack);
        $tag = $item->getNamedTag();
        if (count($item->getEnchantments()) > 0) $tag->removeTag(Item::TAG_DISPLAY);

        if ($tag->getTag("OriginalDisplayTag") instanceof CompoundTag) {
            $tag->setTag(Item::TAG_DISPLAY, $tag->getTag("OriginalDisplayTag"));
            $tag->removeTag("OriginalDisplayTag");
        }
        $item->setNamedTag($tag);
        return TypeConverter::getInstance()->coreItemStackToNet($item);
    }

    /**
     * @param EnchantmentInstance[] $enchantments
     * @return EnchantmentInstance[]
     */
    public static function sortEnchantmentsByRarity(array $enchantments): array
    {
        usort($enchantments, function (EnchantmentInstance $enchantmentInstance, EnchantmentInstance $enchantmentInstanceB) {
            $type = $enchantmentInstance->getType();
            $typeB = $enchantmentInstanceB->getType();
    
            $rarityA = ($type instanceof CustomEnchantment) ? $type->getRarity() : 0; 
            $rarityB = ($typeB instanceof CustomEnchantment) ? $typeB->getRarity() : 0; 
    
            return $rarityB - $rarityA; 
        });
    
        return $enchantments;
    }
    
    protected static function registerEnchantments(): void {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "enchantments.yml");
        $enchantments = $config->getAll();

        /** @var list<string> */
        $skippedInvalid = [];

        foreach ($enchantments as $enchantmentName => $enchantmentData) {
            if (!\is_array($enchantmentData)) {
                continue;
            }

            if (!isset($enchantmentData['display'], $enchantmentData['description'], $enchantmentData['group'])) {
                $skippedInvalid[] = (string) $enchantmentName;
                continue;
            }
    
            $name = strval($enchantmentName);
            $descriptionArray = (array) $enchantmentData['description'];
            $description = implode("\n", $descriptionArray);
            $rarity = (int) Groups::getGroupId($enchantmentData['group']);
            $maxLevel = self::getMaxLevel($enchantmentData);
            if (isset($enchantmentData['applies']) && $enchantmentData['applies'] !== []) {
                $tags = self::parseAppliesField($enchantmentData['applies']);
            } else {
                $tags = self::parseTags($enchantmentData['applies-to'] ?? []);
            }
            $enchantment = new CustomEnchantment($name, $rarity, $description, $maxLevel, $tags);

            self::register($name, $enchantment);
        }

        if ($skippedInvalid !== []) {
            $n = \count($skippedInvalid);
            $sample = array_slice($skippedInvalid, 0, 12);
            $suffix = $n > \count($sample) ? " (+ " . ($n - \count($sample)) . " more)" : "";
            Loader::getInstance()->getLogger()->warning(
                "Skipped {$n} enchantment key(s) missing display/description/group: " . implode(", ", $sample) . $suffix
            );
        }
    }

    protected static function getMaxLevel(array $enchantmentData): int {
        if (!isset($enchantmentData['levels']) || empty($enchantmentData['levels'])) {
            throw new \InvalidArgumentException("Enchantment '" . $enchantmentData['display'] . "' does not define any levels.");
        }
    
        $levels = $enchantmentData['levels'];
        $maxLevel = max(array_keys($levels));
        return $maxLevel;
    }

    /**
     * Same as enchant {@code applies:} tokens — resolves ALL_ARMOR, ALL_SWORD, etc. to PocketMine tag strings.
     * Used by {@see \ecstsy\MartianEnchantments\utils\EnchantApplyGate} for {@code items.settings.can-apply-to}.
     *
     * @param array<int,mixed>|string $raw
     * @return list<string>
     */
    public static function parseAppliesField(array|string $raw): array {
        $list = is_array($raw) ? $raw : [$raw];
        $out = [];
        foreach ($list as $e) {
            $s = strtoupper(trim((string) $e));
            if ($s === '') {
                continue;
            }
            foreach (self::mapAppliesToken($s) as $t) {
                $out[] = $t;
            }
        }

        return array_values(array_unique($out));
    }

    /** @return list<string> */
    private static function mapAppliesToken(string $s): array {
        return match ($s) {
            'ALL_SWORD', 'SWORD',
            'WOODEN_SWORD', 'IRON_SWORD', 'DIAMOND_SWORD', 'GOLDEN_SWORD',
            'STONE_SWORD', 'NETHERITE_SWORD' => [ItemEnchantmentTags::SWORD],
            'ALL_AXE', 'AXE' => [ItemEnchantmentTags::AXE],
            'ALL_PICKAXE', 'PICKAXE' => [ItemEnchantmentTags::PICKAXE],
            'ALL_SPADE', 'ALL_SHOVEL', 'SHOVEL' => [ItemEnchantmentTags::SHOVEL],
            'ALL_HOE', 'HOE' => [ItemEnchantmentTags::HOE],
            'ALL_ARMOR' => [
                ItemEnchantmentTags::ARMOR,
                ItemEnchantmentTags::HELMET,
                ItemEnchantmentTags::CHESTPLATE,
                ItemEnchantmentTags::LEGGINGS,
                ItemEnchantmentTags::BOOTS,
            ],
            'HELMET' => [ItemEnchantmentTags::HELMET],
            'CHESTPLATE' => [ItemEnchantmentTags::CHESTPLATE],
            'LEGGINGS' => [ItemEnchantmentTags::LEGGINGS],
            'BOOTS' => [ItemEnchantmentTags::BOOTS],
            'BOW' => [ItemEnchantmentTags::BOW],
            'CROSSBOW' => \property_exists(ItemEnchantmentTags::class, 'CROSSBOW') ? [ItemEnchantmentTags::CROSSBOW] : [],
            'TRIDENT' => [ItemEnchantmentTags::TRIDENT],
            'WEAPON' => [ItemEnchantmentTags::SWORD, ItemEnchantmentTags::AXE, ItemEnchantmentTags::BOW, ItemEnchantmentTags::TRIDENT],
            'TOOL' => [
                ItemEnchantmentTags::PICKAXE,
                ItemEnchantmentTags::AXE,
                ItemEnchantmentTags::HOE,
                ItemEnchantmentTags::SHOVEL,
            ],
            'ALL', 'ANY' => [ItemEnchantmentTags::ALL],
            default => [],
        };
    }

    protected static function parseTags(array|string $appliesTo): array {
        $applies = is_array($appliesTo) ? $appliesTo : [$appliesTo];
        $tags = [];

        foreach ($applies as $apply) {
            $apply = strtolower(trim($apply));

            if (str_ends_with($apply, 's')) {
                $apply = substr($apply, 0, -1);
            }

            switch (strtolower($apply)) {
                case 'pickaxe':
                    $tags[] = ItemEnchantmentTags::PICKAXE;
                    break;
                case 'sword':
                    $tags[] = ItemEnchantmentTags::SWORD;
                    break;
                case 'axe':
                    $tags[] = ItemEnchantmentTags::AXE;
                    break;
                case 'hoe':
                    $tags[] = ItemEnchantmentTags::HOE;
                    break;
                case 'shovel':
                    $tags[] = ItemEnchantmentTags::SHOVEL;
                    break;
                case 'armor':
                    $tags[] = ItemEnchantmentTags::ARMOR;
                    break;
                case 'helmet':
                    $tags[] = ItemEnchantmentTags::HELMET;
                    break;
                case 'chestplate':
                    $tags[] = ItemEnchantmentTags::CHESTPLATE;
                    break;
                case 'leggings':
                    $tags[] = ItemEnchantmentTags::LEGGINGS;
                    break;
                case 'boots':
                    $tags[] = ItemEnchantmentTags::BOOTS;
                    break;
                case 'bow':
                    $tags[] = ItemEnchantmentTags::BOW;
                    break;
                case 'trident':
                    $tags[] = ItemEnchantmentTags::TRIDENT;
                    break;
                case 'armor':
                    array_push($tags, ItemEnchantmentTags::HELMET, ItemEnchantmentTags::CHESTPLATE, ItemEnchantmentTags::LEGGINGS, ItemEnchantmentTags::BOOTS);
                    break;
                case 'tool':
                    array_push($tags, ItemEnchantmentTags::PICKAXE, ItemEnchantmentTags::AXE, ItemEnchantmentTags::HOE, ItemEnchantmentTags::SHOVEL);
                    break;
                case 'weapon':
                    array_push($tags, ItemEnchantmentTags::SWORD, ItemEnchantmentTags::AXE, ItemEnchantmentTags::BOW, ItemEnchantmentTags::TRIDENT);
                    break;
                case 'all':
                    return [ItemEnchantmentTags::ALL]; 
            }
        }

        return array_values(array_unique($tags));
    }
    
    /**
     * @return list<CustomEnchantment>
     */
    public static function getEnchantmentsForGroup(int $groupId): array {
        $out = [];
        foreach (self::getAll() as $e) {
            if ($e->getRarity() === $groupId) {
                $out[] = $e;
            }
        }

        return array_values($out);
    }

    public static function getEnchantmentByName(string $name): ?CustomEnchantment {
        $key = strtoupper($name);
        return self::$enchants[$key] ?? null;
    }
    
}

