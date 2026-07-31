<?php

declare(strict_types=1);

namespace vision\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use vision\managers\Manager;
use vision\ranks\RankType;

final class RankCommand extends Command {
    public function __construct() {
        parent::__construct('rank', 'Attribue un rang à un joueur.', '/rank <joueur> <rang> [durée]');
        $this->setPermission(DefaultPermissions::ROOT_OPERATOR);
        $this->setPermissionMessage(Manager::BRANDING()->format('{prefix}{error}Commande réservée aux OP.'));
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if (!$this->testPermission($sender)) {
            return;
        }
        if (!isset($args[0], $args[1])) {
            $sender->sendMessage('§cUtilisation : §9/rank <joueur> <rang> [durée]');
            $sender->sendMessage('§7Exemples : §9/rank Steve Hero 30m §8| §9/rank Steve Paysan');
            return;
        }

        $rank = RankType::fromString((string) $args[1]);
        if ($rank === null) {
            $ranks = array_map(static fn(RankType $type): string => RankType::enumToString($type), RankType::cases());
            $sender->sendMessage('§cRang invalide. Rangs disponibles : §7' . implode(', ', $ranks) . '.');
            return;
        }

        $rawDuration = isset($args[2]) ? implode('', array_slice($args, 2)) : '';
        $permanent = $rawDuration === '' || in_array(strtolower(trim($rawDuration)), ['permanent', 'perm', 'forever'], true);
        $duration = $permanent ? null : $this->parseDuration($rawDuration);
        if (!$permanent && $duration === null) {
            $sender->sendMessage('§cDurée invalide. Exemples : §930m§c, §92h§c, §97d§c ou §91mois§c.');
            return;
        }

        $target = $sender->getServer()->getPlayerByPrefix((string) $args[0]);
        $targetName = $target?->getName() ?? (string) $args[0];
        $expiresAt = $duration === null ? null : time() + $duration;
        Manager::RANK()->setPlayerRank($targetName, $rank, $expiresAt);
        $component = Manager::RANK()->rank($rank);

        if ($target instanceof Player) {
            $target->setNameTag($component->getColor() . $component->getName()
                . Manager::BRANDING()->format(' {secondary}') . $target->getName());
        }

        $durationText = $duration === null ? ' de manière permanente' : ' pendant ' . $this->formatDuration($duration);
        $sender->getServer()->broadcastMessage(
            Manager::BRANDING()->format('{prefix}{primary}') . $targetName
            . Manager::BRANDING()->format(' {secondary}a reçu le rang ')
            . $component->getColor() . $component->getName()
            . Manager::BRANDING()->format('{secondary}') . $durationText . '.'
        );
    }

    private function parseDuration(string $duration): ?int {
        $duration = strtolower(trim(str_replace([' ', 'é', 'è'], ['', 'e', 'e'], $duration)));
        $units = [
            '/^(\d+)(m|min|minute|minutes)$/' => 60,
            '/^(\d+)(h|heure|heures)$/' => 3600,
            '/^(\d+)(j|d|jour|jours)$/' => 86400,
            '/^(\d+)(w|week|weeks|semaine|semaines)$/' => 604800,
            '/^(\d+)(mo|mois|month|months)$/' => 2592000,
        ];
        foreach ($units as $pattern => $seconds) {
            if (preg_match($pattern, $duration, $matches) === 1) {
                return max(1, (int) $matches[1]) * $seconds;
            }
        }
        return null;
    }

    private function formatDuration(int $seconds): string {
        foreach ([2592000 => 'mois', 604800 => 'semaine', 86400 => 'jour', 3600 => 'heure'] as $unit => $label) {
            if ($seconds >= $unit) {
                $amount = intdiv($seconds, $unit);
                return $amount . ' ' . $label . ($amount > 1 && $label !== 'mois' ? 's' : '');
            }
        }
        $minutes = max(1, intdiv($seconds, 60));
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '');
    }
}
