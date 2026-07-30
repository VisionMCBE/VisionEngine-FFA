<?php

declare(strict_types=1);

namespace vision;

use NayTools\NayTools;
use pocketmine\command\defaults\VanillaCommand;
use pocketmine\plugin\PluginBase;
use pocketmine\ServerProperties;
use pocketmine\utils\SingletonTrait;
use vision\commands\KitFFACommand;
use vision\commands\LeaderboardCommand;
use vision\commands\MaintenanceCommand;
use vision\commands\RekitCommand;
use vision\commands\SettingsCommand;
use vision\commands\StatsCommand;
use vision\commands\XyzCommand;
use vision\events\FFAListener;
use vision\items\ItemRegistry;
use vision\managers\Manager;
use vision\tasks\EnvironmentTask;
use vision\tasks\LeaderboardTask;
use vision\tasks\PackSendTask;
use vision\tasks\ScoreboardTask;

final class Main extends PluginBase
{
    use SingletonTrait;

    public function onEnable(): void
    {
        self::setInstance($this);

        $this->saveResource('config.yml');
        $this->saveResource('config.json');
        $this->saveResource('database.json');
        $this->saveResource('knockback.yml');
        $this->saveResource('placeholders.yml');
        Manager::setup($this);
        Manager::PACK()->install();
        $this->getServer()->getConfigGroup()->setConfigString(ServerProperties::MOTD, Manager::BRANDING()->motd());

        ItemRegistry::registerAll();

        $map = $this->getServer()->getCommandMap();
        $this->removeNonOpVanillaCommands();
        foreach ([
            new KitFFACommand($this),
            new LeaderboardCommand($this),
            new MaintenanceCommand($this),
            new RekitCommand($this),
            new SettingsCommand($this),
            new StatsCommand($this),
            new XyzCommand($this),
        ] as $command) {
            $map->register('vision', $command);
        }

        foreach ([
            new FFAListener($this),
            Manager::ANTICHEAT(),
            Manager::PACK(),
        ] as $listener) {
            $this->getServer()->getPluginManager()->registerEvents($listener, $this);
        }

        foreach ([
            [new ScoreboardTask(), 20],
            [new EnvironmentTask(), 20 * 10],
            [new LeaderboardTask(), 20 * 10],
            [new PackSendTask(), 1],
        ] as [$task, $period]) {
            $this->getScheduler()->scheduleRepeatingTask($task, $period);
        }
    }

    public function onDisable(): void
    {
        Manager::shutdown();
    }

    private function removeNonOpVanillaCommands(): void
    {
        $allowed = [
            'ban' => true,
            'ban-ip' => true,
            'banlist' => true,
            'clear' => true,
            'deop' => true,
            'difficulty' => true,
            'effect' => true,
            'enchant' => true,
            'gamemode' => true,
            'give' => true,
            'kick' => true,
            'kill' => true,
            'op' => true,
            'pardon' => true,
            'pardon-ip' => true,
            'say' => true,
            'status' => true,
            'stop' => true,
            'tp' => true,
            'time' => true,
            'timings' => true,
            'title' => true,
            'whitelist' => true,
        ];

        $map = $this->getServer()->getCommandMap();
        foreach ($map->getCommands() as $command) {
            if (!$command instanceof VanillaCommand || isset($allowed[$command->getName()])) {
                continue;
            }
            $map->unregister($command);
        }
    }


}
