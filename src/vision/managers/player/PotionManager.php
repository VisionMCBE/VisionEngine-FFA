<?php

declare(strict_types=1);

namespace vision\managers\player;

use pocketmine\item\PotionType;
use pocketmine\player\Player;
use pocketmine\world\particle\PotionSplashParticle;
use pocketmine\world\sound\PotionSplashSound;
use pocketmine\world\sound\ThrowSound;
use vision\managers\Manager;

final class PotionManager {
    public function applyGuided(Player $player, PotionType $type): void {
        $player->getWorld()->addSound($player->getPosition(), new ThrowSound());
        $player->getWorld()->addSound($player->getPosition(), new PotionSplashSound());
        $color = Manager::SETTINGS()->getPotionParticleColor($player->getName());
        $player->getWorld()->addParticle(
            $player->getPosition(),
            new PotionSplashParticle($color ?? PotionSplashParticle::DEFAULT_COLOR())
        );

        if ($type === PotionType::HEALING()) {
            $player->setHealth(min($player->getMaxHealth(), $player->getHealth() + 4.0));
        } elseif ($type === PotionType::STRONG_HEALING()) {
            $player->setHealth(min($player->getMaxHealth(), $player->getHealth() + 8.0));
        }
        Manager::NAMETAG()->update($player);
    }
}
