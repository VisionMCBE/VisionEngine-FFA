<?php

declare(strict_types=1);

namespace vision\tasks;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\scheduler\Task;
use pocketmine\world\World;
use vision\Main;

final class EnvironmentTask extends Task {
    public function onRun(): void
    {
        $server = Main::getInstance()->getServer();

        foreach ($server->getWorldManager()->getWorlds() as $world) {
            $world->setTime(World::TIME_DAY);
            $world->stopTime();
        }

        foreach ($server->getOnlinePlayers() as $player) {
            $player->getHungerManager()->setFood(20);
            $player->getHungerManager()->setSaturation(20.0);

            $effect = $player->getEffects()->get(VanillaEffects::NIGHT_VISION());
            if ($effect !== null && $effect->getDuration() > 20 * 60) {
                continue;
            }
            $player->getEffects()->add(new EffectInstance(VanillaEffects::NIGHT_VISION(), 20 * 60 * 10, 0, false));
        }
    }
}
