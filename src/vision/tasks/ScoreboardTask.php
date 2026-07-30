<?php

declare(strict_types=1);

namespace vision\tasks;


use vision\managers\Manager;

use pocketmine\scheduler\Task;
use vision\Main;

final class ScoreboardTask extends Task {
    public function onRun(): void
    {
        foreach (Main::getInstance()->getServer()->getOnlinePlayers() as $player) {
            Manager::SCOREBOARD()->update($player);
        }
    }
}
