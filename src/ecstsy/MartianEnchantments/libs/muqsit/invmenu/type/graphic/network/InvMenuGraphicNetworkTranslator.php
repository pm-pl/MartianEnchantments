<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\libs\muqsit\invmenu\type\graphic\network;

use ecstsy\MartianEnchantments\libs\muqsit\invmenu\session\InvMenuInfo;
use ecstsy\MartianEnchantments\libs\muqsit\invmenu\session\PlayerSession;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;

interface InvMenuGraphicNetworkTranslator{

	public function translate(PlayerSession $session, InvMenuInfo $current, ContainerOpenPacket $packet) : void;
}