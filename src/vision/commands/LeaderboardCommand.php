<?php

declare(strict_types=1);

namespace vision\commands;


use vision\managers\Manager;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use vision\Main;

final class LeaderboardCommand extends Command {
    public function __construct() {
        parent::__construct('leaderboard', 'Permet de placer ou retirer les classements FFA.', '/leaderboard <kills|deaths|league|remove>');
        $this->setPermission(DefaultPermissions::ROOT_OPERATOR);
        $this->setPermissionMessage(Manager::BRANDING()->format('{prefix}{error}Commande réservée aux OP.'));
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void  {
        if (!$sender instanceof Player) {
            $sender->sendMessage('Cette commande doit être utilisée en jeu.');
            return;
        }

        $type = strtolower((string) ($args[0] ?? ''));
        $type = $type === 'ligue' ? 'league' : $type;
        if ($type === 'kills' || $type === 'deaths' || $type === 'league') {
            Manager::LEADERBOARD()->place($type, $sender);
            $labels = ['kills' => 'des kills', 'deaths' => 'des morts', 'league' => 'des ligues'];
            $sender->sendMessage('§9Le classement ' . $labels[$type] . ' a été placé à votre position.');
            return;
        }

        if ($type === 'remove') {
            $sender->sendMessage(Manager::LEADERBOARD()->removeNearest($sender->getPosition())
                ? '§9Le classement le plus proche a été supprimé.'
                : '§cAucun classement n’a été trouvé à moins de 5 blocs.');
            return;
        }

        $sender->sendMessage('§7Utilisation : §9/leaderboard <kills|deaths|league|remove>');
    }
}
