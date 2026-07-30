<?php

declare(strict_types=1);

namespace VisionEngineFFA;

use NayTools\NayTools;
use pocketmine\command\defaults\VanillaCommand;
use pocketmine\plugin\PluginBase;
use pocketmine\ServerProperties;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use VisionEngineFFA\AntiCheat\AntiCheatListener;
use VisionEngineFFA\Commands\KitFfaCommand;
use VisionEngineFFA\Commands\LeaderboardCommand;
use VisionEngineFFA\Commands\MaintenanceCommand;
use VisionEngineFFA\Commands\RekitCommand;
use VisionEngineFFA\Commands\SettingsCommand;
use VisionEngineFFA\Commands\StatsCommand;
use VisionEngineFFA\Commands\XyzCommand;
use VisionEngineFFA\Events\FfaListener;
use VisionEngineFFA\Events\ResourcePackSendListener;
use VisionEngineFFA\Items\ItemRegistry;
use VisionEngineFFA\Managers\DatabaseManager;
use VisionEngineFFA\Managers\FfaManager;
use VisionEngineFFA\Managers\BrandingManager;
use VisionEngineFFA\Managers\RankManager;
use VisionEngineFFA\Managers\CooldownManager;
use VisionEngineFFA\Managers\KnockbackManager;
use VisionEngineFFA\Managers\LeaderboardManager;
use VisionEngineFFA\Managers\ScoreboardManager;
use VisionEngineFFA\Managers\SettingsManager;
use VisionEngineFFA\Managers\StatsManager;
use VisionEngineFFA\Tasks\EnvironmentTask;
use VisionEngineFFA\Tasks\LeaderboardTask;
use VisionEngineFFA\Tasks\PackSendTask;
use VisionEngineFFA\Tasks\ScoreboardTask;

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

    /** @var array<int, \VisionEngineFFA\Services\PackSendEntry> */
    public static array $packSendQueue = [];

    private FfaManager $ffaManager;
    private SettingsManager $settingsManager;
    private RankManager $rankManager;
    private CooldownManager $cooldownManager;
    private KnockbackManager $knockbackManager;
    private ScoreboardManager $scoreboardManager;
    private BrandingManager $brandingManager;
    private StatsManager $statsManager;
    private LeaderboardManager $leaderboardManager;

    public function onEnable(): void
    {
        self::setInstance($this);

        $this->saveResource('config.yml');
        $this->saveResource('config.json');
        $this->saveResource('database.json');
        $this->saveResource('knockback.yml');
        $this->saveResource('placeholders.yml');
        $this->installResourcePack();

        DatabaseManager::init($this);
        $this->brandingManager = new BrandingManager($this);
        $this->applyMotd();
        $this->rankManager = new RankManager(DatabaseManager::get());
        $this->rankManager->initSchema();
        $this->cooldownManager = new CooldownManager();
        $this->knockbackManager = new KnockbackManager($this);
        $this->scoreboardManager = new ScoreboardManager();
        $this->settingsManager = new SettingsManager($this);
        $this->statsManager = new StatsManager($this);
        $this->leaderboardManager = new LeaderboardManager($this);
        $this->ffaManager = new FfaManager($this);

        ItemRegistry::registerAll();

        $map = $this->getServer()->getCommandMap();
        $this->removeNonOpVanillaCommands();
        $map->register('VisionEngineFFA', new KitFfaCommand($this));
        $map->register('VisionEngineFFA', new LeaderboardCommand($this));
        $map->register('VisionEngineFFA', new MaintenanceCommand($this));
        $map->register('VisionEngineFFA', new RekitCommand($this));
        $map->register('VisionEngineFFA', new SettingsCommand($this));
        $map->register('VisionEngineFFA', new StatsCommand($this));
        $map->register('VisionEngineFFA', new XyzCommand($this));

        $this->getServer()->getPluginManager()->registerEvents(new FfaListener($this), $this);
        $this->getServer()->getPluginManager()->registerEvents(new AntiCheatListener($this), $this);
        $this->getServer()->getPluginManager()->registerEvents(new ResourcePackSendListener(), $this);
        $this->getScheduler()->scheduleRepeatingTask(new ScoreboardTask(), 20);
        $this->getScheduler()->scheduleRepeatingTask(new EnvironmentTask(), 20 * 10);
        $this->getScheduler()->scheduleRepeatingTask(new LeaderboardTask(), 20 * 10);
        $this->getScheduler()->scheduleRepeatingTask(new PackSendTask(), 1);
        $this->getLogger()->info('VisionEngineFFA charge.');
    }

    public function onDisable(): void
    {
        self::$packSendQueue = [];
        DatabaseManager::close();
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

    private function applyMotd(): void
    {
        $this->getServer()->getConfigGroup()->setConfigString(ServerProperties::MOTD, $this->brandingManager->motd());
    }

    public function ffa(): FfaManager
    {
        return $this->ffaManager;
    }

    public function settings(): SettingsManager
    {
        return $this->settingsManager;
    }

    public function ranks(): RankManager
    {
        return $this->rankManager;
    }

    public function cooldowns(): CooldownManager
    {
        return $this->cooldownManager;
    }

    public function knockback(): KnockbackManager
    {
        return $this->knockbackManager;
    }

    public function scoreboards(): ScoreboardManager
    {
        return $this->scoreboardManager;
    }

    public function branding(): BrandingManager
    {
        return $this->brandingManager;
    }

    public function stats(): StatsManager
    {
        return $this->statsManager;
    }

    public function leaderboards(): LeaderboardManager
    {
        return $this->leaderboardManager;
    }
}
