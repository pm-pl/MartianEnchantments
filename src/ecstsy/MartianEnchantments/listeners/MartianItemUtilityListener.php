<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\listeners;

use ecstsy\MartianEnchantments\armor\ArmorSetManager;
use ecstsy\MartianEnchantments\server\items\rename\MartianItemRenameSession;
use ecstsy\MartianEnchantments\utils\ItemApplyHelper;
use ecstsy\MartianEnchantments\utils\ScrollHandler;
use ecstsy\MartianEnchantments\utils\SoulGemSessionManager;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;

/**
 * Right-click consumables ({@code secret-dust}) and Item NameTag chat flow ({@see MartianItemRenameSession}).
 */
final class MartianItemUtilityListener implements Listener {

    /**
     * Stops vanilla item use/consumption for martian utilities (secret dust = fire charge, etc.).
     * Armor set gear ({@code martianArmor}) must keep vanilla behavior so Bedrock can right-click to equip armor.
     *
     * @priority HIGHEST
     */
    public function onMartianUtilityPreventVanillaItemUse(PlayerItemUseEvent $event): void {
        $item = $event->getItem();
        if (ItemApplyHelper::martianId($item) === null) {
            return;
        }
        if (ArmorSetManager::isMartianArmorItem($item)) {
            return;
        }

        $event->cancel();
    }

    public function onChat(PlayerChatEvent $event): void {
        $player = $event->getPlayer();
        if (MartianItemRenameSession::handleChat($player, $event->getMessage())) {
            $event->cancel();
        }
    }

    public function onItemUse(PlayerItemUseEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $tag = $item->getNamedTag()->getCompoundTag("MartianEnchantments");
        if ($tag === null || $tag->getTag("martianItem") === null) {
            return;
        }

        $id = strtolower($tag->getString("martianItem"));

        match ($id) {
            "itemnametag" => $this->handleNameTagUse($player, $event),
            "secret-dust" => $this->handleSecretDustUse($player, $item, $event),
            "soulgem" => $this->handleSoulGemToggle($player, $event),
            default => null,
        };
    }

    public function onQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        MartianItemRenameSession::abort($player, false);
        SoulGemSessionManager::clearOnQuit($player);
    }

    private function handleSoulGemToggle(Player $player, PlayerItemUseEvent $event): void {
        $event->cancel();
        SoulGemSessionManager::tryToggleChannel($player);
    }

    private function handleNameTagUse(Player $player, PlayerItemUseEvent $event): void {
        $event->cancel();
        MartianItemRenameSession::start($player);
    }

    private function handleSecretDustUse(Player $player, \pocketmine\item\Item $item, PlayerItemUseEvent $event): void {
        $event->cancel();
        ScrollHandler::openSecretDust($player, $item);
    }
}
