<?php

declare(strict_types=1);

namespace vision\managers\display;


use vision\managers\Manager;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\world\particle\FloatingTextParticle;
use pocketmine\world\Position;
use vision\Main;

final class LeaderboardManager {
    private Config $config;

    /** @var array<string, FloatingTextParticle> */
    private array $particles = [];

    public function __construct(private readonly Main $plugin)  {
        $this->config = new Config($plugin->getDataFolder() . 'leaderboards.json', Config::JSON, []);
    }

    public function place(string $type, Player $player): void  {
        $world = $player->getWorld();
        $key = strtolower($world->getFolderName()) . '|' . $type;
        $position = $player->getPosition();
        $this->remove($key);
        $this->config->set($key, [
            'type' => $type,
            'world' => $world->getFolderName(),
            'x' => $position->getX(),
            'y' => $position->getY(),
            'z' => $position->getZ(),
        ]);
        $this->config->save();
        $this->refreshOne($key, $this->config->get($key));
    }

    public function removeNearest(Position $position): bool  {
        $nearest = null;
        $distance = 25.0;
        foreach ($this->config->getAll() as $key => $data) {
            if (!is_array($data) || strtolower((string) ($data['world'] ?? '')) !== strtolower($position->getWorld()->getFolderName())) {
                continue;
            }
            $dx = (float) ($data['x'] ?? 0) - $position->getX();
            $dy = (float) ($data['y'] ?? 0) - $position->getY();
            $dz = (float) ($data['z'] ?? 0) - $position->getZ();
            $current = ($dx * $dx) + ($dy * $dy) + ($dz * $dz);
            if ($current < $distance) {
                $distance = $current;
                $nearest = (string) $key;
            }
        }
        if ($nearest === null) {
            return false;
        }
        $this->remove($nearest);
        $this->config->remove($nearest);
        $this->config->save();
        return true;
    }

    public function refreshAll(): void  {
        foreach ($this->config->getAll() as $key => $data) {
            if (is_array($data)) {
                $this->refreshOne((string) $key, $data);
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function refreshOne(string $key, array $data): void  {
        $world = $this->plugin->getServer()->getWorldManager()->getWorldByName((string) ($data['world'] ?? ''));
        $type = (string) ($data['type'] ?? '');
        if ($world === null || ($type !== 'kills' && $type !== 'deaths')) {
            return;
        }

        $title = $type === 'kills' ? '§l§9TOP KILLS' : '§l§9TOP MORTS';
        $lines = [];
        foreach (Manager::STATS()->top($type) as $index => $row) {
            $rank = $index + 1;
            $color = $rank === 1 ? '§6' : ($rank === 2 ? '§7' : ($rank === 3 ? '§c' : '§f'));
            $lines[] = $color . $rank . '. §f' . $row['name'] . ' §8- §9' . $row['value'];
        }
        if ($lines === []) {
            $lines[] = '§7Aucun joueur classé.';
        }

        $particle = $this->particles[$key] ??= new FloatingTextParticle(implode("\n", $lines), $title);
        $particle->setInvisible(false);
        $particle->setTitle($title);
        $particle->setText(implode("\n", $lines));
        $world->addParticle(new Position(
            (float) ($data['x'] ?? 0),
            (float) ($data['y'] ?? 0),
            (float) ($data['z'] ?? 0),
            $world
        ), $particle);
    }

    private function remove(string $key): void  {
        $particle = $this->particles[$key] ?? null;
        $data = $this->config->get($key);
        if ($particle !== null && is_array($data)) {
            $world = $this->plugin->getServer()->getWorldManager()->getWorldByName((string) ($data['world'] ?? ''));
            if ($world !== null) {
                $particle->setInvisible();
                $world->addParticle(new Position((float) $data['x'], (float) $data['y'], (float) $data['z'], $world), $particle);
            }
        }
        unset($this->particles[$key]);
    }
}
