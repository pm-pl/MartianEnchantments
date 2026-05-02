<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\utils\screens\enchants;

use ecstsy\MartianEnchantments\commands\EnchantmentInfoPresenter;
use ecstsy\MartianEnchantments\enchantments\CustomEnchantment;
use ecstsy\MartianEnchantments\enchantments\CustomEnchantments;
use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\PlayerUtils;
use ecstsy\MartianEnchantments\libs\jojoe77777\FormAPI\SimpleForm;
use ecstsy\MartianEnchantments\utils\screens\BaseScreen;
use pocketmine\item\enchantment\ItemEnchantmentTags;
use pocketmine\item\enchantment\ItemEnchantmentTagRegistry;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class EnchantmentBuckets {

    public const CAT_ARMOR = 'c_armor';
    public const CAT_SWORD = 'c_sword';
    public const CAT_AXE = 'c_axe';
    public const CAT_RANGED = 'c_ranged';
    public const CAT_TOOLS = 'c_tools';
    public const CAT_OTHER = 'c_other';
    public const CAT_ALL = 'c_universal';

    public const PREFIX_ENCH = 'e:';

    /**
     * @return array<string, list<CustomEnchantment>>
     */
    public static function bucketAll(): array {
        $buckets = [
            self::CAT_ARMOR => [],
            self::CAT_SWORD => [],
            self::CAT_AXE => [],
            self::CAT_RANGED => [],
            self::CAT_TOOLS => [],
            self::CAT_OTHER => [],
            self::CAT_ALL => [],
        ];

        $reg = ItemEnchantmentTagRegistry::getInstance();
        foreach (CustomEnchantments::getAll() as $e) {
            $cat = self::categorize($e, $reg);
            $buckets[$cat][] = $e;
        }

        foreach ($buckets as $k => &$list) {
            usort($list, static fn (CustomEnchantment $a, CustomEnchantment $b): int => strcmp($a->getName(), $b->getName()));
        }
        unset($list);

        return $buckets;
    }

    public static function categorize(CustomEnchantment $e, ItemEnchantmentTagRegistry $reg): string {
        $tags = $e->getTags();
        if (in_array(ItemEnchantmentTags::ALL, $tags, true)) {
            return self::CAT_ALL;
        }

        $armor = [ItemEnchantmentTags::ARMOR, ItemEnchantmentTags::HELMET, ItemEnchantmentTags::CHESTPLATE, ItemEnchantmentTags::LEGGINGS, ItemEnchantmentTags::BOOTS];
        if ($reg->isTagArrayIntersection($tags, $armor)) {
            return self::CAT_ARMOR;
        }
        if ($reg->isTagArrayIntersection($tags, [ItemEnchantmentTags::SWORD])) {
            return self::CAT_SWORD;
        }
        if ($reg->isTagArrayIntersection($tags, [ItemEnchantmentTags::AXE])) {
            return self::CAT_AXE;
        }
        $ranged = [ItemEnchantmentTags::BOW, ItemEnchantmentTags::TRIDENT];
        /** @phpstan-ignore-next-line */
        if (\property_exists(ItemEnchantmentTags::class, 'CROSSBOW')) {
            $ranged[] = ItemEnchantmentTags::CROSSBOW;
        }
        if ($reg->isTagArrayIntersection($tags, $ranged)) {
            return self::CAT_RANGED;
        }
        if ($reg->isTagArrayIntersection($tags, [ItemEnchantmentTags::PICKAXE, ItemEnchantmentTags::SHOVEL, ItemEnchantmentTags::HOE])) {
            return self::CAT_TOOLS;
        }

        return self::CAT_OTHER;
    }
}

final class EnchantmentListScreen {

    public static function open(Player $player): void {
        (new EnchantmentMainCategoriesScreen())->open($player);
    }
}

final class EnchantmentMainCategoriesScreen extends BaseScreen {

    protected function build(Player $player): SimpleForm {
        $b = EnchantmentBuckets::bucketAll();

        $form = new SimpleForm(function (Player $p, ?string $data): void {
            if ($data === null) {
                return;
            }
            if (str_starts_with($data, 'c_')) {
                (new EnchantmentCategoryEntriesScreen($data))->open($p);
            }
        });

        $form->setTitle(C::colorize('&8&lMartian&dEnchants'));
        $form->setContent(C::colorize('&r&8Pick a category. Each button shows how many enchants are in it.'));

        foreach ([
            [EnchantmentBuckets::CAT_ARMOR, '&l&cArmor', '&r&8Helmets, chestplates…'],
            [EnchantmentBuckets::CAT_SWORD, '&l&eSwords', '&r&8Melee swords'],
            [EnchantmentBuckets::CAT_AXE, '&l&6Axes', '&r&8War / tool axes'],
            [EnchantmentBuckets::CAT_RANGED, '&l&bRanged', '&r&8Bow, crossbow, trident'],
            [EnchantmentBuckets::CAT_TOOLS, '&l&aTools', '&r&8Pick, shovel, hoe'],
            [EnchantmentBuckets::CAT_ALL, '&l&dUniversal', '&r&8Applies to ALL items'],
            [EnchantmentBuckets::CAT_OTHER, '&l&8Other / mixed', '&r&8Everything else'],
        ] as $row) {
            [$id, $title, $subtitle] = $row;
            $n = count($b[$id]);
            $form->addButton(
                C::colorize($title) . "\n" . C::colorize('&r&8' . $n . ' enchantments…') . "\n" . C::colorize($subtitle),
                -1,
                '',
                $id
            );
        }

        return $form;
    }
}

final class EnchantmentCategoryEntriesScreen extends BaseScreen {

    public function __construct(private readonly string $categoryId) {
    }

    protected function build(Player $player): SimpleForm {
        $buckets = EnchantmentBuckets::bucketAll();
        $list = $buckets[$this->categoryId] ?? [];
        $categoryId = $this->categoryId;

        $form = new SimpleForm(function (Player $p, ?string $data) use ($categoryId): void {
            if ($data === null) {
                return;
            }
            if ($data === 'back') {
                EnchantmentListScreen::open($p);

                return;
            }
            if (!str_starts_with($data, EnchantmentBuckets::PREFIX_ENCH)) {
                return;
            }
            $name = substr($data, strlen(EnchantmentBuckets::PREFIX_ENCH));
            $e = CustomEnchantments::getEnchantmentByName($name);
            if ($e !== null) {
                (new EnchantmentDetailBrowseScreen($e, $categoryId))->open($p);
            }
        });

        $form->setTitle(C::colorize('&r&8Category'));
        $form->setContent(C::colorize('&r&8Select an enchantment to read its details.'));
        $form->addButton(C::colorize('&8« &r&8Back to categories'), -1, '', 'back');

        foreach ($list as $e) {
            $form->addButton(
                C::colorize('&r&l&f' . ucfirst($e->getName())),
                -1,
                '',
                EnchantmentBuckets::PREFIX_ENCH . strtolower($e->getName())
            );
        }

        if ($list === []) {
            $form->addButton(C::colorize('&r&8(nothing here)'), -1, '', 'back');
        }

        return $form;
    }

    protected function afterOpen(Player $player): void {
        PlayerUtils::playSound($player, 'random.click');
    }
}

final class EnchantmentDetailBrowseScreen extends BaseScreen {

    public function __construct(
        private readonly CustomEnchantment $enchantment,
        private readonly string $returnCategory,
    ) {
    }

    protected function build(Player $player): SimpleForm {
        $content = EnchantmentInfoPresenter::asFormContent($this->enchantment);
        $returnCategory = $this->returnCategory;

        $form = new SimpleForm(function (Player $p, ?string $data) use ($returnCategory): void {
            if ($data === null) {
                EnchantmentListScreen::open($p);

                return;
            }
            if ($data === 'back_cat') {
                (new EnchantmentCategoryEntriesScreen($returnCategory))->open($p);

                return;
            }
            EnchantmentListScreen::open($p);
        });

        $form->setTitle(C::colorize('&r&d' . ucfirst($this->enchantment->getName())));
        $form->setContent($content);
        $form->addButton(C::colorize('&8« &r&8Back'), -1, '', 'back_cat');

        return $form;
    }

    protected function afterOpen(Player $player): void {
        PlayerUtils::playSound($player, 'random.orb');
    }
}