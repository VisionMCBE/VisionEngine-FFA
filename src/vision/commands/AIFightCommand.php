<?php

declare(strict_types=1);

namespace vision\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use vision\managers\Manager;

final class AIFightCommand extends Command {
    public function __construct() {
        parent::__construct('aifight', 'Lance un combat amical contre une IA.', '/aifight <noob|semi-pro|pro|hacker>');
        $this->setPermission(DefaultPermissionNames::GROUP_USER);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage('Cette commande doit être utilisée en jeu.');
            return;
        }
        $difficulty = match (strtolower((string) ($args[0] ?? ''))) {
            'noob', 'easy', 'facile' => 'easy',
            'semi-pro', 'semipro', 'medium', 'moyen' => 'medium',
            'pro', 'hard', 'difficile' => 'hard',
            'hacker' => 'hacker',
            default => null,
        };
        if ($difficulty === null) {
            $sender->sendMessage('§7Utilisation : §9/aifight <noob|semi-pro|pro|hacker>');
            return;
        }
        Manager::AIFIGHT()->start($sender, $difficulty);
    }
}
