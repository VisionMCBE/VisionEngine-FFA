<?php

declare(strict_types=1);

namespace vision\commands;


use vision\managers\Manager;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use vision\Main;

final class LeaderboardCommand extends Command
{
    public function __construct(private readonly Main $plugin)
    {
        parent::__construct('leaderboard', 'Place les classements FFA.', '/leaderboard <kills|deaths|remove>');
        $this->setPermission(DefaultPermissions::ROOT_OPERATOR);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage('Commande en jeu uniquement.');
            return;
        }
        if (!$sender->getServer()->isOp($sender->getName())) {
            $sender->sendMessage('§cCommande réservée aux OP.');
            return;
        }

        $type = strtolower((string) ($args[0] ?? ''));
        if ($type === 'kills' || $type === 'deaths') {
            Manager::LEADERBOARD()->place($type, $sender);
            $sender->sendMessage('§9Classement ' . ($type === 'kills' ? 'des kills' : 'des morts') . ' placé.');
            return;
        }
        if ($type === 'remove') {
            $sender->sendMessage(Manager::LEADERBOARD()->removeNearest($sender->getPosition())
                ? '§9Classement le plus proche supprimé.'
                : '§cAucun classement à moins de 5 blocs.');
            return;
        }
        $sender->sendMessage('§7Utilisation : §9/leaderboard <kills|deaths|remove>');
    }
}
