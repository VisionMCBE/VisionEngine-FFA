<?php

declare(strict_types=1);

namespace vision\tasks;

use pocketmine\scheduler\Task;
use vision\Main;
use vision\managers\Manager;

final class PackSendTask extends Task {
    public function onRun(): void  {
        $tick = Main::getInstance()->getServer()->getTick();
        Manager::PACK()->tick($tick);
    }
}
