<?php

declare(strict_types=1);

namespace vision\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use vision\managers\Manager;

final class SpawnCommand extends Command {
    public function __construct() {
        parent::__construct('spawn', 'Téléporte au centre de la zone protégée.', '/spawn');
        $this->setPermission(DefaultPermissionNames::GROUP_USER);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage('Cette commande doit être utilisée en jeu.');
            return;
        }

        if (Manager::COMBAT()->isInCombat($sender) || Manager::AIFIGHT()->isFighting($sender)) {
            $sender->sendMessage(Manager::BRANDING()->format('{prefix}{error}Vous ne pouvez pas vous téléporter au spawn pendant un combat.'));
            return;
        }

        $spawn = Manager::FFA()->spawnPosition();
        if ($spawn === null) {
            $sender->sendMessage(Manager::BRANDING()->format('{prefix}{error}Le spawn KitFFA est indisponible ou incomplet.'));
            return;
        }
        $sender->teleport($spawn);
        $sender->sendMessage(Manager::BRANDING()->format('{prefix}{success}Vous avez été téléporté au spawn.'));
    }
}
