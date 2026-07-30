<?php

declare(strict_types=1);

namespace vision\managers\player;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use vision\Main;

final class KnockbackManager {
    private const DEFAULT_ENABLED = true;
    private const DEFAULT_HORIZONTAL = 0.38;
    private const DEFAULT_VERTICAL = 0.4;
    private const DEFAULT_ATTACK_COOLDOWN = 8;

    private Config $config;

    public function __construct(Main $plugin)
    {
        $json = $plugin->getDataFolder() . 'knockback.json';
        $file = is_file($json) ? $json : $plugin->getDataFolder() . 'knockback.yml';
        $type = str_ends_with($file, '.json') ? Config::JSON : Config::YAML;
        $this->config = new Config($file, $type, [
            'enabled' => self::DEFAULT_ENABLED,
            'horizontal' => self::DEFAULT_HORIZONTAL,
            'vertical' => self::DEFAULT_VERTICAL,
            'attack_cooldown' => self::DEFAULT_ATTACK_COOLDOWN,
        ]);
    }

    public function apply(EntityDamageByEntityEvent $event): void
    {
        if (!$this->enabled()) {
            return;
        }
        if (!$event->getEntity() instanceof Player || !$event->getDamager() instanceof Player) {
            return;
        }

        $event->setKnockBack($this->horizontal());
        $event->setVerticalKnockBackLimit($this->vertical());
        $event->setAttackCooldown($this->attackCooldown());
    }

    public function enabled(): bool
    {
        return (bool) $this->config->get('enabled', self::DEFAULT_ENABLED);
    }

    public function horizontal(): float
    {
        return max(0.0, (float) $this->config->get('horizontal', self::DEFAULT_HORIZONTAL));
    }

    public function vertical(): float
    {
        return max(0.0, (float) $this->config->get('vertical', self::DEFAULT_VERTICAL));
    }

    public function attackCooldown(): int
    {
        return max(0, (int) $this->config->get('attack_cooldown', self::DEFAULT_ATTACK_COOLDOWN));
    }
}
