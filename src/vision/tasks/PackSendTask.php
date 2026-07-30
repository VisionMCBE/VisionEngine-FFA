<?php

declare(strict_types=1);

namespace vision\tasks;

use pocketmine\scheduler\Task;
use vision\Main;

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
