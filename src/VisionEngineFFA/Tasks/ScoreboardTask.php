<?php

declare(strict_types=1);

namespace VisionEngineFFA\Tasks;

use pocketmine\scheduler\Task;
use VisionEngineFFA\Main;

final class ScoreboardTask extends Task
{
    public function onRun(): void
    {
        foreach (Main::getInstance()->getServer()->getOnlinePlayers() as $player) {
            Main::getInstance()->scoreboards()->update($player);
        }
    }
}
