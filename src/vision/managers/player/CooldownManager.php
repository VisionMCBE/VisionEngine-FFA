<?php

declare(strict_types=1);

namespace vision\managers\player;

final class CooldownManager {
    /** @var array<string, array<string, int>> */
    private array $cooldowns = [];

    /** @var array<string, string> */
    private array $combatOpponents = [];

    public function add(string $player, string $type, int $seconds): void  {
        $this->cooldowns[strtolower($player)][$type] = time() + $seconds;
    }

    public function remaining(string $player, string $type): int  {
        return max(0, ($this->cooldowns[strtolower($player)][$type] ?? 0) - time());
    }

    public function has(string $player, string $type): bool  {
        return $this->remaining($player, $type) > 0;
    }

    public function remove(string $player, string $type): void  {
        unset($this->cooldowns[strtolower($player)][$type]);
    }

    public function clear(string $player): void  {
        unset($this->cooldowns[strtolower($player)]);
        unset($this->combatOpponents[strtolower($player)]);
    }

    public function setCombatPair(string $first, string $second): void  {
        $this->combatOpponents[strtolower($first)] = $second;
        $this->combatOpponents[strtolower($second)] = $first;
    }

    public function combatOpponent(string $player): ?string  {
        if (!$this->has($player, 'combat')) {
            return null;
        }
        return $this->combatOpponents[strtolower($player)] ?? null;
    }

    public function clearCombat(string $player): void  {
        $key = strtolower($player);
        $opponent = $this->combatOpponents[$key] ?? null;
        unset($this->combatOpponents[$key], $this->cooldowns[$key]['combat']);
        if ($opponent !== null) {
            $opponentKey = strtolower($opponent);
            unset($this->combatOpponents[$opponentKey], $this->cooldowns[$opponentKey]['combat']);
        }
    }
}
