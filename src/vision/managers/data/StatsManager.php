<?php

declare(strict_types=1);

namespace vision\managers\data;

use pocketmine\item\Item;
use pocketmine\item\PotionType;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use vision\Main;

final class StatsManager {
    private Config $config;

    public function __construct(Main $plugin)  {
        $this->config = new Config($plugin->getDataFolder() . 'stats.json', Config::JSON, []);
    }

    /** @return array{kills: int, deaths: int, streak: int, best_streak: int} */
    public function get(string $player): array  {
        $key = strtolower($player);
        return [
            'kills' => (int) $this->config->getNested($key . '.kills', 0),
            'deaths' => (int) $this->config->getNested($key . '.deaths', 0),
            'streak' => (int) $this->config->getNested($key . '.streak', 0),
            'best_streak' => (int) $this->config->getNested($key . '.best_streak', 0),
        ];
    }

    public function addKill(string $player): void  {
        $stats = $this->get($player);
        ++$stats['kills'];
        ++$stats['streak'];
        $stats['best_streak'] = max($stats['best_streak'], $stats['streak']);
        $this->set($player, $stats);
    }

    public function addDeath(string $player): void  {
        $stats = $this->get($player);
        ++$stats['deaths'];
        $stats['streak'] = 0;
        $this->set($player, $stats);
    }

    public function kd(string $player): float  {
        $stats = $this->get($player);
        return $stats['kills'] / max(1, $stats['deaths']);
    }

    public function killMessage(Player $killer, Player $victim): string  {
        $potion = VanillaItems::SPLASH_POTION()->setType(PotionType::STRONG_HEALING());
        return '§9[Vision] §f' . $killer->getName() . ' §8[' . $this->countItems($killer, $potion) . '] §7a désintégré §f'
            . $victim->getName() . ' §8[' . $this->countItems($victim, $potion) . ']§7.';
    }

    /** @return list<array{name: string, value: int}> */
    public function top(string $stat, int $limit = 10): array  {
        if ($stat !== 'kills' && $stat !== 'deaths') {
            return [];
        }

        $rows = [];
        foreach ($this->config->getAll() as $key => $data) {
            if (!is_array($data)) {
                continue;
            }
            $rows[] = [
                'name' => (string) ($data['name'] ?? $key),
                'value' => (int) ($data[$stat] ?? 0),
            ];
        }
        usort($rows, static fn(array $a, array $b): int => $b['value'] <=> $a['value']);
        return array_slice($rows, 0, $limit);
    }

    /** @param array{kills: int, deaths: int, streak: int, best_streak: int} $stats */
    private function set(string $player, array $stats): void  {
        $key = strtolower($player);
        foreach ($stats as $name => $value) {
            $this->config->setNested($key . '.' . $name, $value);
        }
        $this->config->setNested($key . '.name', $player);
        $this->config->save();
    }

    private function countItems(Player $player, Item $needle): int  {
        $count = 0;
        foreach ($player->getInventory()->getContents() as $item) {
            if ($item->equals($needle, false, false)) {
                $count += $item->getCount();
            }
        }
        return $count;
    }
}
