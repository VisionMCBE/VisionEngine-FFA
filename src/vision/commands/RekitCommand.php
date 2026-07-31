<?php

declare(strict_types=1);

namespace vision\commands;


use vision\managers\Manager;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use vision\Main;

final class RekitCommand extends Command {
    public function __construct()  {
        parent::__construct('rekit', 'Rééquipe entièrement votre kit FFA.', '/rekit', ['refill']);
        $this->setPermission(DefaultPermissionNames::GROUP_USER);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void  {
        if (!$sender instanceof Player) {
            $sender->sendMessage('Cette commande doit être utilisée en jeu.');
            return;
        }
        if (Manager::COMBAT()->isInCombat($sender) || Manager::AIFIGHT()->isFighting($sender)) {
            $sender->sendMessage(Manager::BRANDING()->format('{prefix}{error}Vous ne pouvez pas utiliser cette commande pendant un combat.'));
            return;
        }

        if (Manager::FFA()->isInside($sender->getPosition())) {
            $sender->sendMessage(Manager::BRANDING()->format('{prefix}{error}Vous devez quitter la zone protégée avant de pouvoir récupérer votre kit.'));
            return;
        }

        Manager::FFA()->giveKit($sender);
        $sender->sendMessage(Manager::BRANDING()->format('{prefix}{success}Votre kit FFA a été entièrement rééquipé.'));
    }
}
