<?php

declare(strict_types=1);

namespace ecstsy\MartianEnchantments\utils;

use ecstsy\MartianEnchantments\libs\ecstsy\MartianUtilities\utils\PlayerUtils;
use ecstsy\MartianEnchantments\Loader;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;

/**
 * Enchanting-table particles sweep from ahead of the player toward the torso (“absorption”).
 */
final class SoulGemParticleEffect {

    private const CHANNEL_FRAMES = 14;

    public static function channelOpenBurst(Player $player, string $particleIdentifier): void {
        if (!$player->isOnline()) {
            return;
        }

        $scheduler = Loader::getInstance()->getScheduler();
        $feet = $player->getPosition();
        $dirRaw = $player->getDirectionVector();
        $dirLen = hypot($dirRaw->x, hypot($dirRaw->y, $dirRaw->z));
        $dir = $dirLen < 1e-9
            ? new Vector3(0, 0, 1)
            : new Vector3($dirRaw->x / $dirLen, $dirRaw->y / $dirLen, $dirRaw->z / $dirLen);

        $eyeVec = new Vector3($feet->x, $feet->y + $player->getEyeHeight(), $feet->z);

        $start = new Vector3(
            $eyeVec->getX() + $dir->getX() * 2.35,
            $eyeVec->getY() + max(-0.2, min(0.35, $dir->getY() * 1.05)),
            $eyeVec->getZ() + $dir->getZ() * 2.35,
        );

        $core = new Vector3($feet->getX(), $feet->getY() + 0.92, $feet->getZ());

        $hx = $dir->getX();
        $hz = $dir->getZ();
        $pmag = hypot($hx, $hz);
        $right = $pmag < 1e-6 ? new Vector3(1.0, 0.0, 0.0) : new Vector3(-($hz / $pmag), 0.0, ($hx / $pmag));

        for ($n = 0; $n < self::CHANNEL_FRAMES; $n++) {
            $delay = $n * 2;

            $scheduler->scheduleDelayedTask(new ClosureTask(static function () use ($player, $start, $core, $right, $n, $particleIdentifier): void {
                if (!$player->isOnline()) {
                    return;
                }

                $den = max(1, self::CHANNEL_FRAMES - 1);
                $prog = min(1.0, max(0.0, $n / $den));
                $ease = 1 - (1 - $prog) * (1 - $prog);

                $bx = $start->getX() + ($core->getX() - $start->getX()) * $ease;
                $by = $start->getY() + ($core->getY() - $start->getY()) * $ease;
                $bz = $start->getZ() + ($core->getZ() - $start->getZ()) * $ease;

                $spiral = (1 - $ease) * 0.72;
                $angle = ($n / $den) * 6.28;
                $offX = $right->getX() * cos($angle) * $spiral;
                $offZ = $right->getZ() * cos($angle) * $spiral;
                $offY = sin($angle) * 0.14 * $spiral;

                for ($s = 0; $s < 5; $s++) {
                    $jx = (mt_rand(-12, 12) / 1000.0) + ($s * 0.038 - 0.076);
                    $jy = mt_rand(-10, 10) / 1000.0;
                    $jz = (mt_rand(-12, 12) / 1000.0);

                    PlayerUtils::spawnParticleEffectFor(
                        $player,
                        new Vector3($bx + $offX + $jx, $by + $offY + $jy, $bz + $offZ + $jz),
                        $particleIdentifier
                    );
                }
            }), $delay + 3);
        }

        $finaleDelay = self::CHANNEL_FRAMES * 2 + 6;
        $scheduler->scheduleDelayedTask(new ClosureTask(static function () use ($player, $core, $particleIdentifier): void {
            if (!$player->isOnline()) {
                return;
            }

            for ($burst = 0; $burst < 10; $burst++) {
                $jx = mt_rand(-8, 8) / 100.0;
                $jy = mt_rand(-14, 8) / 100.0;
                $jz = mt_rand(-8, 8) / 100.0;
                PlayerUtils::spawnParticleEffectFor(
                    $player,
                    new Vector3($core->getX() + $jx, $core->getY() + $jy, $core->getZ() + $jz),
                    $particleIdentifier
                );
            }
        }), $finaleDelay);
    }
}
