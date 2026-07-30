<?php

declare(strict_types=1);

namespace vision\tasks;


use vision\managers\Manager;

use pocketmine\scheduler\Task;
final class LeaderboardTask extends Task {
    public function onRun(): void
    {
        Manager::LEADERBOARD()->refreshAll();
    }
}
