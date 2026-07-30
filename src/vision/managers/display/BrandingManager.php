<?php

declare(strict_types=1);

namespace vision\managers\display;

use vision\Main;

final class BrandingManager {
    public function __construct(private readonly Main $plugin) {}

    public function serverName(): string
    {
        return (string) $this->plugin->getConfig()->getNested('server.name', 'Vision');
    }

    public function modeName(): string
    {
        return (string) $this->plugin->getConfig()->getNested('server.mode_name', 'FFA');
    }

    public function serverIp(): string
    {
        return (string) $this->plugin->getConfig()->getNested('server.ip', '13.140.129.97:19132');
    }

    public function motd(): string
    {
        return $this->format((string) $this->plugin->getConfig()->getNested('server.motd', '{primary}{server_name} {mode_name}'));
    }

    public function color(string $key, string $default): string
    {
        return (string) $this->plugin->getConfig()->getNested('colors.' . $key, $default);
    }

    public function format(string $text, array $extra = []): string
    {
        $values = [
            '{server_name}' => $this->serverName(),
            '{mode_name}' => $this->modeName(),
            '{server_ip}' => $this->serverIp(),
            '{bracket}' => $this->color('bracket', '§1'),
            '{primary}' => $this->color('primary', '§9'),
            '{secondary}' => $this->color('secondary', '§7'),
            '{text}' => $this->color('text', '§f'),
            '{success}' => $this->color('success', '§a'),
            '{error}' => $this->color('error', '§c'),
            '{warning}' => $this->color('warning', '§e'),
            '{dark}' => $this->color('dark', '§8'),
        ];
        $values['{prefix}'] = strtr((string) $this->plugin->getConfig()->getNested('messages.prefix', '{bracket}[{primary}{server_name}{bracket}] '), $values);
        $values['{ac_prefix}'] = strtr((string) $this->plugin->getConfig()->getNested('messages.anticheat_prefix', '{bracket}[{primary}{server_name}AC{bracket}] '), $values);

        return strtr($text, $extra + $values);
    }

    public function prefix(): string
    {
        return $this->format('{prefix}');
    }

    public function anticheatPrefix(): string
    {
        return $this->format('{ac_prefix}');
    }

    public function scoreboardTitle(): string
    {
        return $this->format((string) $this->plugin->getConfig()->getNested('scoreboard.title', '{primary}{server_name} {mode_name}'));
    }

    public function combatTitle(): string
    {
        return $this->format((string) $this->plugin->getConfig()->getNested('scoreboard.combat_title', '{error}Combat'));
    }

    public function separator(): string
    {
        return $this->format((string) $this->plugin->getConfig()->getNested('scoreboard.separator', '{secondary}-----------------'));
    }

    public function itemText(string $path, string $default): string
    {
        return $this->format((string) $this->plugin->getConfig()->getNested('items.' . $path, $default));
    }
}
