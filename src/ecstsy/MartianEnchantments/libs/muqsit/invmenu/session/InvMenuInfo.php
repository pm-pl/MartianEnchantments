<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\libs\muqsit\invmenu\session;

use ecstsy\MartianEnchantments\libs\muqsit\invmenu\InvMenu;
use ecstsy\MartianEnchantments\libs\muqsit\invmenu\type\graphic\InvMenuGraphic;

final class InvMenuInfo{

	public function __construct(
		readonly public InvMenu $menu,
		readonly public InvMenuGraphic $graphic,
		readonly public ?string $graphic_name
	){}
}