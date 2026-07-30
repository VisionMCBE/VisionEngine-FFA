<?php

declare(strict_types=1);

namespace VisionEngineFFA\Managers;

final class CooldownManager
{
    /** @var array<string, array<string, int>> */
    private array $cooldowns = [];

    public function add(string $player, string $type, int $seconds): void
    {
        $this->cooldowns[strtolower($player)][$type] = time() + $seconds;
    }

    public function remaining(string $player, string $type): int
    {
        return max(0, ($this->cooldowns[strtolower($player)][$type] ?? 0) - time());
    }

    public function has(string $player, string $type): bool
    {
        return $this->remaining($player, $type) > 0;
    }

    public function remove(string $player, string $type): void
    {
        unset($this->cooldowns[strtolower($player)][$type]);
    }

    public function clear(string $player): void
    {
        unset($this->cooldowns[strtolower($player)]);
    }
}
