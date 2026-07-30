<?php

declare(strict_types=1);

namespace vision;

use NayTools\NayTools;
use pocketmine\command\defaults\VanillaCommand;
use pocketmine\plugin\PluginBase;
use pocketmine\ServerProperties;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use vision\commands\KitFFACommand;
use vision\commands\LeaderboardCommand;
use vision\commands\MaintenanceCommand;
use vision\commands\RekitCommand;
use vision\commands\SettingsCommand;
use vision\commands\StatsCommand;
use vision\commands\XyzCommand;
use vision\events\FFAListener;
use vision\events\ResourcePackSendListener;
use vision\items\ItemRegistry;
use vision\managers\Manager;
use vision\managers\player\FfaManager;
use vision\managers\display\BrandingManager;
use vision\managers\player\RankManager;
use vision\managers\player\CooldownManager;
use vision\managers\player\KnockbackManager;
use vision\managers\display\LeaderboardManager;
use vision\managers\display\ScoreboardManager;
use vision\managers\data\SettingsManager;
use vision\managers\data\StatsManager;
use vision\tasks\EnvironmentTask;
use vision\tasks\LeaderboardTask;
use vision\tasks\PackSendTask;
use vision\tasks\ScoreboardTask;

use function copy;
use function file_exists;
use function is_dir;
use function mkdir;
use function rename;
use function unlink;

final class Main extends PluginBase
{
    use SingletonTrait;

    public const DEFAULT_CHUNK_SIZE = 512 * 1024;
    public const DEFAULT_PACKET_SEND_INTERVAL = 3;

    /** @var array<int, \vision\services\PackSendEntry> */
    public static array $packSendQueue = [];

    public function onEnable(): void
    {
        self::setInstance($this);

        $this->saveResource('config.yml');
        $this->saveResource('config.json');
        $this->saveResource('database.json');
        $this->saveResource('knockback.yml');
        $this->saveResource('placeholders.yml');
        $this->installResourcePack();

        Manager::setup($this);
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
            new ResourcePackSendListener(),
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
        self::$packSendQueue = [];
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

    private function installResourcePack(): void
    {
        $packName = 'VisionPackFFA.zip';
        $this->saveResource($packName, true);

        $resourcePackDir = $this->getServer()->getDataPath() . 'resource_packs';
        if (!is_dir($resourcePackDir)) {
            mkdir($resourcePackDir);
        }

        $source = $this->getDataFolder() . $packName;
        $target = $resourcePackDir . DIRECTORY_SEPARATOR . $packName;
        if (!file_exists($source)) {
            $this->getLogger()->warning('Pack resource introuvable: ' . $packName);
            return;
        }

        if (!$this->isValidZip($source)) {
            $this->getLogger()->warning('Pack resource invalide: ' . $packName);
            return;
        }

        $temporaryTarget = $target . '.tmp';
        if (file_exists($temporaryTarget)) {
            @unlink($temporaryTarget);
        }
        copy($source, $temporaryTarget);
        if (!$this->isValidZip($temporaryTarget)) {
            @unlink($temporaryTarget);
            $this->getLogger()->warning('Copie du pack invalide: ' . $packName);
            return;
        }
        rename($temporaryTarget, $target);

        $configPath = $resourcePackDir . DIRECTORY_SEPARATOR . 'resource_packs.yml';
        $config = new Config($configPath, Config::YAML, [
            'force_resources' => true,
            'resource_stack' => [],
        ]);
        $stack = $config->get('resource_stack', []);
        if (!is_array($stack)) {
            $stack = [];
        }
        if (!in_array($packName, $stack, true)) {
            $stack[] = $packName;
            $config->set('resource_stack', $stack);
            $config->save();
            $this->getLogger()->warning($packName . ' installé. Redémarre le serveur une fois pour que PocketMine le charge.');
            return;
        }

        $config->save();
    }

    private function isValidZip(string $path): bool
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }
        $valid = $zip->numFiles > 0 && $zip->getNameIndex(0) === 'manifest.json';
        $zip->close();
        return $valid;
    }

    public function ffa(): FfaManager
    {
        return Manager::FFA();
    }

    public function settings(): SettingsManager
    {
        return Manager::SETTINGS();
    }

    public function ranks(): RankManager
    {
        return Manager::RANK();
    }

    public function cooldowns(): CooldownManager
    {
        return Manager::COOLDOWN();
    }

    public function knockback(): KnockbackManager
    {
        return Manager::KNOCKBACK();
    }

    public function scoreboards(): ScoreboardManager
    {
        return Manager::SCOREBOARD();
    }

    public function branding(): BrandingManager
    {
        return Manager::BRANDING();
    }

    public function stats(): StatsManager
    {
        return Manager::STATS();
    }

    public function leaderboards(): LeaderboardManager
    {
        return Manager::LEADERBOARD();
    }
}
