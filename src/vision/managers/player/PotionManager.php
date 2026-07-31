<?php

declare(strict_types=1);

namespace vision\managers\player;

use pocketmine\entity\effect\InstantEffect;
use pocketmine\entity\Living;
use pocketmine\item\PotionType;
use pocketmine\player\Player;
use pocketmine\world\particle\PotionSplashParticle;
use pocketmine\world\sound\PotionSplashSound;
use pocketmine\world\sound\ThrowSound;
use vision\entities\AIFightBot;
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

        $impact = $player->getPosition();
        $area = $player->getBoundingBox()->expandedCopy(4.125, 2.125, 4.125);
        foreach ($player->getWorld()->getCollidingEntities($area) as $entity) {
            if (!$entity instanceof Living || !$entity->isAlive()) {
                continue;
            }
            if ($entity instanceof Player && $entity !== $player) {
                $opponent = Manager::COMBAT()->activeOpponent($entity);
                $protected = Manager::COMBAT()->isInCombat($entity) || Manager::AIFIGHT()->isFighting($entity);
                if ($protected && $opponent !== strtolower($player->getName())) {
                    continue;
                }
            }
            $distanceSquared = $entity->getEyePos()->distanceSquared($impact);
            if ($distanceSquared > 16.0) {
                continue;
            }
            $distanceMultiplier = $entity === $player ? 1.0 : 1.0 - (sqrt($distanceSquared) / 4.0);
            foreach ($type->getEffects() as $effect) {
                if ($effect->getType() instanceof InstantEffect) {
                    $effect->getType()->applyEffect($entity, $effect, $distanceMultiplier, $player);
                    continue;
                }
                $duration = (int) round($effect->getDuration() * 0.75 * $distanceMultiplier);
                if ($duration >= 20) {
                    $effect->setDuration($duration);
                    $entity->getEffects()->add($effect);
                }
            }
            if ($entity instanceof Player) {
                Manager::NAMETAG()->update($entity);
            } elseif ($entity instanceof AIFightBot) {
                $entity->updateNameTag();
            }
        }
    }
}
