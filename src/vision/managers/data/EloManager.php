<?php

declare(strict_types=1);

namespace vision\managers\data;

use pocketmine\utils\Config;
use vision\Main;

final class EloManager {
    private const ANTI_FARM_WINDOW = 300;

    /** @var list<array{name: string, elo: int, color: string}> */
    private const LEAGUES = [
        ['name' => 'Bronze I', 'elo' => 0, 'color' => '§6'],
        ['name' => 'Bronze II', 'elo' => 75, 'color' => '§6'],
        ['name' => 'Bronze III', 'elo' => 150, 'color' => '§6'],
        ['name' => 'Argent I', 'elo' => 250, 'color' => '§7'],
        ['name' => 'Argent II', 'elo' => 350, 'color' => '§7'],
        ['name' => 'Argent III', 'elo' => 450, 'color' => '§7'],
        ['name' => 'Or I', 'elo' => 600, 'color' => '§e'],
        ['name' => 'Or II', 'elo' => 750, 'color' => '§e'],
        ['name' => 'Or III', 'elo' => 900, 'color' => '§e'],
        ['name' => 'Platine I', 'elo' => 1100, 'color' => '§b'],
        ['name' => 'Platine II', 'elo' => 1300, 'color' => '§b'],
        ['name' => 'Platine III', 'elo' => 1500, 'color' => '§b'],
        ['name' => 'Diamant I', 'elo' => 1750, 'color' => '§3'],
        ['name' => 'Diamant II', 'elo' => 2000, 'color' => '§3'],
        ['name' => 'Diamant III', 'elo' => 2250, 'color' => '§3'],
        ['name' => 'Saphir I', 'elo' => 2550, 'color' => '§9'],
        ['name' => 'Saphir II', 'elo' => 2850, 'color' => '§9'],
        ['name' => 'Saphir III', 'elo' => 3150, 'color' => '§9'],
        ['name' => 'Visionne I', 'elo' => 3500, 'color' => '§d'],
        ['name' => 'Visionne II', 'elo' => 3850, 'color' => '§d'],
        ['name' => 'Visionne III', 'elo' => 4200, 'color' => '§d'],
    ];

    private Config $config;

    /** @var array<string, array{last_kill: int, kills: int}> */
    private array $recentKills = [];

    public function __construct(Main $plugin)  {
        $this->config = new Config($plugin->getDataFolder() . 'elo.json', Config::JSON, []);
    }

    public function get(string $player): int  {
        return max(0, (int) $this->config->getNested(strtolower($player) . '.elo', 0));
    }

    /** @return array{winner_gain: int, loser_loss: int, winner_old_league: string, loser_old_league: string, anti_farm: bool} */
    public function recordKill(string $winner, string $loser): array  {
        $winnerElo = $this->get($winner);
        $loserElo = $this->get($loser);
        $expected = 1.0 / (1.0 + pow(10.0, ($loserElo - $winnerElo) / 400.0));
        $change = max(18, min(36, (int) round(36.0 * (1.0 - $expected))));
        $multiplier = $this->antiFarmMultiplier($winner, $loser);
        $change = max(1, (int) round($change * $multiplier));
        $loss = min($loserElo, $change);
        $winnerLeague = $this->league($winnerElo)['name'];
        $loserLeague = $this->league($loserElo)['name'];

        $this->set($winner, $winnerElo + $change);
        $this->set($loser, $loserElo - $loss);

        return [
            'winner_gain' => $change,
            'loser_loss' => $loss,
            'winner_old_league' => $winnerLeague,
            'loser_old_league' => $loserLeague,
            'anti_farm' => $multiplier < 1.0,
        ];
    }

    private function antiFarmMultiplier(string $winner, string $loser): float  {
        $key = strtolower($winner) . ':' . strtolower($loser);
        $now = time();
        $history = $this->recentKills[$key] ?? null;
        $kills = $history !== null && ($now - $history['last_kill']) <= self::ANTI_FARM_WINDOW
            ? $history['kills']
            : 0;

        $this->recentKills[$key] = ['last_kill' => $now, 'kills' => $kills + 1];
        return match ($kills) {
            0 => 1.0,
            1 => 0.5,
            default => 0.25,
        };
    }

    /** @return array{name: string, elo: int, color: string} */
    public function league(int|string $playerOrElo): array  {
        $elo = is_int($playerOrElo) ? $playerOrElo : $this->get($playerOrElo);
        $league = self::LEAGUES[0];
        foreach (self::LEAGUES as $candidate) {
            if ($elo < $candidate['elo']) {
                break;
            }
            $league = $candidate;
        }
        return $league;
    }

    /** @return array{name: string, elo: int, color: string}|null */
    public function nextLeague(string $player): ?array  {
        $elo = $this->get($player);
        foreach (self::LEAGUES as $league) {
            if ($league['elo'] > $elo) {
                return $league;
            }
        }
        return null;
    }

    public function information(string $player): string  {
        $elo = $this->get($player);
        $league = $this->league($elo);
        $next = $this->nextLeague($player);
        $message = "§9§lVOTRE LIGUE§r\n§fLigue actuelle : " . $league['color'] . $league['name']
            . "\n§fELO : §9" . $elo;
        if ($next === null) {
            return $message . "\n§dVous avez atteint la ligue maximale.";
        }
        return $message . "\n§fProchaine ligue : " . $next['color'] . $next['name']
            . "\n§fELO requis : §9" . $next['elo']
            . " §8(§9" . ($next['elo'] - $elo) . " restant§8)";
    }

    /** @return list<array{name: string, value: int, league: string, color: string}> */
    public function top(int $limit = 10): array  {
        $rows = [];
        foreach ($this->config->getAll() as $key => $data) {
            if (!is_array($data)) {
                continue;
            }
            $elo = max(0, (int) ($data['elo'] ?? 0));
            $league = $this->league($elo);
            $rows[] = [
                'name' => (string) ($data['name'] ?? $key),
                'value' => $elo,
                'league' => $league['name'],
                'color' => $league['color'],
            ];
        }
        usort($rows, static fn(array $a, array $b): int => $b['value'] <=> $a['value']);
        return array_slice($rows, 0, $limit);
    }

    private function set(string $player, int $elo): void  {
        $key = strtolower($player);
        $this->config->setNested($key . '.name', $player);
        $this->config->setNested($key . '.elo', max(0, $elo));
        $this->config->save();
    }
}
