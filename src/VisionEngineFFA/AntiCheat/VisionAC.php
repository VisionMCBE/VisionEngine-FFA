<?php

declare(strict_types=1);

namespace VisionEngineFFA\AntiCheat;

use pocketmine\player\Player;
use pocketmine\Server;
use VisionEngineFFA\Main;
use VisionEngineFFA\Ranks\RankType;

final class VisionAC
{
    private const KICK_THRESHOLD = 6;
    private const DECAY_SECONDS = 30;
    private const ALERT_COOLDOWN = 5;

    private static ?self $instance = null;

    /** @var array<string, array<string, array{vl: float, last: int, lastAlert: int}>> */
    private array $violations = [];

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function flag(Player $player, string $check, string $details): void
    {
        if (!$player->isConnected() || $player->getServer()->isOp($player->getName())) {
            return;
        }

        $key = strtolower($player->getName());
        $now = time();
        $entry = $this->violations[$key][$check] ?? ['vl' => 0.0, 'last' => 0, 'lastAlert' => 0];

        if ($now - $entry['last'] > self::DECAY_SECONDS) {
            $entry['vl'] = 0.0;
        }

        $entry['vl'] += 1.0;
        $entry['last'] = $now;
        $kick = $entry['vl'] >= self::KICK_THRESHOLD;

        if ($kick || ($now - $entry['lastAlert']) >= self::ALERT_COOLDOWN) {
            $entry['lastAlert'] = $now;
            $this->notifyStaff($player, $check, $details, (int) $entry['vl'], $kick);
        }

        if ($kick) {
            $entry['vl'] = 0.0;
            Server::getInstance()->broadcastMessage(Main::getInstance()->branding()->format('{ac_prefix}{error}{player} a été kick par l\'anticheat ({text}{check}{error}).', [
                '{player}' => $player->getName(),
                '{check}' => $check,
            ]));
            $player->kick(Main::getInstance()->branding()->format('{error}{server_name}AC: comportement invalide ({check}).', ['{check}' => $check]));
        }

        $this->violations[$key][$check] = $entry;
    }

    public function clear(Player $player): void
    {
        unset($this->violations[strtolower($player->getName())]);
    }

    private function notifyStaff(Player $player, string $check, string $details, int $vl, bool $kick): void
    {
        $plugin = Main::getInstance();
        $moderatorId = $plugin->ranks()->rank(RankType::MODERATEUR)->getId();
        $message = $plugin->branding()->anticheatPrefix()
            . ($kick ? $plugin->branding()->format('{error}Kick {secondary}') : $plugin->branding()->format('{warning}Alerte {secondary}'))
            . $player->getName()
            . $plugin->branding()->format(' {dark}| {text}') . $check
            . $plugin->branding()->format(' {dark}| {primary}') . $vl . '/' . self::KICK_THRESHOLD
            . $plugin->branding()->format(' {dark}| {secondary}') . $details;

        foreach (Server::getInstance()->getOnlinePlayers() as $online) {
            if ($online->getServer()->isOp($online->getName()) || $plugin->ranks()->getPlayerRank($online->getName())->getId() >= $moderatorId) {
                $online->sendMessage($message);
            }
        }
    }
}
