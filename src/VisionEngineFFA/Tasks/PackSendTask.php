<?php

declare(strict_types=1);

namespace VisionEngineFFA\Tasks;

use pocketmine\scheduler\Task;
use VisionEngineFFA\Main;

final class PackSendTask extends Task
{
    public function onRun(): void
    {
        $tick = Main::getInstance()->getServer()->getTick();
        foreach (Main::$packSendQueue as $entry) {
            $entry->tick($tick);
        }
    }
}
