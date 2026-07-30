<?php

declare(strict_types=1);

namespace vision\managers\data;

use pocketmine\color\Color;
use pocketmine\utils\Config;
use vision\Main;

final class SettingsManager {
    private Config $config;
    private Main $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
        $this->config = new Config($plugin->getDataFolder() . 'settings.json', Config::JSON, []);
    }

    public function hasGuidedPotions(string $player): bool
    {
        return (bool) $this->config->getNested(strtolower($player) . '.potions_teleguidees', false);
    }

    public function setGuidedPotions(string $player, bool $enabled): void
    {
        $this->config->setNested(strtolower($player) . '.potions_teleguidees', $enabled);
        $this->config->save();
    }

    public function hasScoreboard(string $player): bool
    {
        return (bool) $this->config->getNested(strtolower($player) . '.scoreboard', (bool) $this->plugin->getConfig()->getNested('scoreboard.enabled_by_default', true));
    }

    public function setScoreboard(string $player, bool $enabled): void
    {
        $this->config->setNested(strtolower($player) . '.scoreboard', $enabled);
        $this->config->save();
    }

    public function hasCombatVisibility(string $player): bool
    {
        return (bool) $this->config->getNested(strtolower($player) . '.combat_visibility', true);
    }

    public function setCombatVisibility(string $player, bool $enabled): void
    {
        $this->config->setNested(strtolower($player) . '.combat_visibility', $enabled);
        $this->config->save();
    }

    public function getPotionParticleColorName(string $player): string
    {
        return (string) $this->config->getNested(strtolower($player) . '.potion_particle_color', 'default');
    }

    public function setPotionParticleColorName(string $player, string $color): void
    {
        $this->config->setNested(strtolower($player) . '.potion_particle_color', $color);
        $this->config->save();
    }

    public function getPotionParticleColor(string $player): ?Color
    {
        return match ($this->getPotionParticleColorName($player)) {
            'red' => new Color(255, 0, 0),
            'green' => new Color(0, 255, 0),
            'blue' => new Color(0, 0, 255),
            'yellow' => new Color(255, 255, 0),
            'pink' => new Color(255, 0, 255),
            'cyan' => new Color(0, 255, 255),
            'white' => new Color(255, 255, 255),
            default => null,
        };
    }
}
