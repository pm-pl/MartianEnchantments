<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\utils\screens;

use ecstsy\MartianEnchantments\libs\jojoe77777\FormAPI\SimpleForm;
use ecstsy\MartianEnchantments\libs\muqsit\invmenu\InvMenu;
use pocketmine\player\Player;

/**
 * Form / InvMenu facade used by gameplay screens ({@see CollectScreen}-style workflow).
 */
abstract class BaseScreen {

    /**
     * @return SimpleForm|InvMenu
     */
    abstract protected function build(Player $player): SimpleForm|InvMenu;

    /** Hook after UI is sent ({@see open}). */
    protected function afterOpen(Player $player): void {
    }

    final public function open(Player $player): void {
        $ui = $this->build($player);
        if ($ui instanceof InvMenu) {
            $ui->send($player);
        } else {
            $player->sendForm($ui);
        }
        $this->afterOpen($player);
    }
}
