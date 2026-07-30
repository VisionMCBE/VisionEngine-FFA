<?php

declare(strict_types=1);

namespace VisionEngineFFA\Tasks;

use pocketmine\scheduler\Task;
use VisionEngineFFA\Main;

final class LeaderboardTask extends Task
{
    public function onRun(): void
    {
        Main::getInstance()->leaderboards()->refreshAll();
    }
}
