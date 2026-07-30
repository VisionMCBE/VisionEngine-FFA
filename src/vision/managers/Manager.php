<?php

declare(strict_types=1);

namespace vision\managers;

use RuntimeException;
use vision\Main;
use vision\managers\data\DatabaseManager;
use vision\managers\data\SettingsManager;
use vision\managers\data\StatsManager;
use vision\managers\display\BrandingManager;
use vision\managers\display\LeaderboardManager;
use vision\managers\display\ScoreboardManager;
use vision\managers\player\CooldownManager;
use vision\managers\player\FfaManager;
use vision\managers\player\KnockbackManager;
use vision\managers\player\RankManager;
use vision\managers\resource\PackManager;
use vision\managers\security\AntiCheatManager;

/**
 * @method static BrandingManager BRANDING()
 * @method static RankManager RANK()
 * @method static CooldownManager COOLDOWN()
 * @method static KnockbackManager KNOCKBACK()
 * @method static ScoreboardManager SCOREBOARD()
 * @method static SettingsManager SETTINGS()
 * @method static StatsManager STATS()
 * @method static LeaderboardManager LEADERBOARD()
 * @method static FfaManager FFA()
 * @method static AntiCheatManager ANTICHEAT()
 * @method static PackManager PACK()
 */
final class Manager {
    /** @var array<string, object> */
    private static array $registrants = [];

    public static function setup(Main $plugin): void
    {
        DatabaseManager::init($plugin);

        self::$registrants['BRANDING'] = new BrandingManager($plugin);
        self::$registrants['RANK'] = new RankManager(DatabaseManager::get());
        self::RANK()->initSchema();
        self::$registrants['COOLDOWN'] = new CooldownManager();
        self::$registrants['KNOCKBACK'] = new KnockbackManager($plugin);
        self::$registrants['SCOREBOARD'] = new ScoreboardManager();
        self::$registrants['SETTINGS'] = new SettingsManager($plugin);
        self::$registrants['STATS'] = new StatsManager($plugin);
        self::$registrants['LEADERBOARD'] = new LeaderboardManager($plugin);
        self::$registrants['FFA'] = new FfaManager($plugin);
        self::$registrants['ANTICHEAT'] = new AntiCheatManager();
        self::$registrants['PACK'] = new PackManager($plugin);

        foreach (array_keys(self::$registrants) as $name) {
            $plugin->getLogger()->notice('[MANAGER] Chargement du Manager ' . $name);
        }
    }

    public static function shutdown(): void
    {
        self::PACK()->clear();
        DatabaseManager::close();
        self::$registrants = [];
    }

    public static function __callStatic(string $name, array $arguments): object
    {
        $name = strtoupper($name);
        return self::$registrants[$name] ?? throw new RuntimeException('Manager non chargé : ' . $name);
    }
}
