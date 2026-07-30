<?php

declare(strict_types=1);

namespace vision\tasks;

use pocketmine\scheduler\Task;
use vision\Main;

final class LeaderboardTask extends Task
{
    public function onRun(): void
    {
        Main::getInstance()->leaderboards()->refreshAll();
    }
}
