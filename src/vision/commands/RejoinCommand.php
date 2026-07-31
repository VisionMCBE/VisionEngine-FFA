<?php

declare(strict_types=1);

namespace vision\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use vision\managers\Manager;

final class RejoinCommand extends Command {
    public function __construct() {
        parent::__construct('rejoin', 'Reconnecte au serveur.', '/rejoin');
        $this->setPermission(DefaultPermissionNames::GROUP_USER);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage('Cette commande doit être utilisée en jeu.');
            return;
        }
        if (Manager::COMBAT()->isInCombat($sender) || Manager::AIFIGHT()->isFighting($sender)) {
            $sender->sendMessage(Manager::BRANDING()->format('{prefix}{error}Vous ne pouvez pas utiliser cette commande pendant un combat.'));
            return;
        }

        $server = Manager::BRANDING()->serverIp();
        $separator = strrpos($server, ':');
        $address = $separator === false ? $server : substr($server, 0, $separator);
        $port = $separator === false ? 19132 : (int) substr($server, $separator + 1);
        $sender->transfer($address, $port > 0 ? $port : 19132, 'Reconnexion au serveur...');
    }
}
