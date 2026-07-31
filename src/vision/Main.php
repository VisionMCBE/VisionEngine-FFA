<?php

declare(strict_types=1);

namespace vision;

use pocketmine\command\defaults\VanillaCommand;
use pocketmine\plugin\PluginBase;
use pocketmine\ServerProperties;
use pocketmine\utils\SingletonTrait;
use vision\commands\KitFFACommand;
use vision\commands\LeaderboardCommand;
use vision\commands\MaintenanceCommand;
use vision\commands\RekitCommand;
use vision\commands\RejoinCommand;
use vision\commands\SettingsCommand;
use vision\commands\SpawnCommand;
use vision\commands\StatsCommand;
use vision\commands\XyzCommand;
use vision\items\ItemRegistry;
use vision\listeners\PlayerListeners;
use vision\managers\Manager;
use vision\tasks\EnvironmentTask;
use vision\tasks\LeaderboardTask;
use vision\tasks\PackSendTask;
use vision\tasks\ScoreboardTask;

final class Main extends PluginBase {
    use SingletonTrait;

    public function onEnable(): void {
        self::setInstance($this);

        foreach (['config.yml', 'config.json', 'database.json', 'knockback.yml', "placeholders.yml"] as $file) {
            $this->saveResource($file);
        }

        Manager::setup($this);
        Manager::PACK()->install();
        $this->getServer()->getConfigGroup()->setConfigString(ServerProperties::MOTD, Manager::BRANDING()->motd());

        ItemRegistry::registerAll();

        $map = $this->getServer()->getCommandMap();
        $this->removeNonOpVanillaCommands();

        $map->registerAll($this->getName(), [
            new KitFFACommand(),
            new LeaderboardCommand(),
            new MaintenanceCommand($this),
            new RekitCommand(),
            new RejoinCommand(),
            new SettingsCommand(),
            new SpawnCommand(),
            new StatsCommand(),
            new XyzCommand($this),
        ]);


        foreach ([
            new PlayerListeners($this),
            Manager::ANTICHEAT(),
            Manager::PACK(),
            ] as $listener) {
            $this->getServer()->getPluginManager()->registerEvents($listener, $this);
        }

        foreach ([
            [new ScoreboardTask(), 20], [new EnvironmentTask(), 20 * 10],
            [new LeaderboardTask(), 20 * 10], [new PackSendTask(), 1],
        ] as [$task, $period]) {
            $this->getScheduler()->scheduleRepeatingTask($task, $period);
        }
    }

    public function onDisable(): void {
        Manager::shutdown();
    }

    private function removeNonOpVanillaCommands(): void {
        $allowed = [
            'ban', 'ban-ip', 'banlist', 'clear',
            'deop', 'difficulty', 'effect',
            'enchant', 'gamemode', 'give', 'kick',
            'kill', 'op', 'pardon', 'pardon-ip',
            'say', 'status', 'stop', 'tp',
            'time', 'timings', 'title', 'whitelist',
        ];

        $map = $this->getServer()->getCommandMap();
        foreach ($map->getCommands() as $command) {
            if (!$command instanceof VanillaCommand || in_array($command->getName(), $allowed, true)) {
                continue;
            }
            $map->unregister($command);
        }
    }
}
