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
        parent::__construct('rekit', 'Reprend le kit FFA.', '/rekit', ['refill']);
        $this->setPermission(DefaultPermissionNames::GROUP_USER);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void  {
        if (!$sender instanceof Player) {
            $sender->sendMessage('Commande en jeu uniquement.');
            return;
        }

        if (Manager::FFA()->isInside($sender->getPosition())) {
            $sender->sendMessage(Manager::BRANDING()->format('{prefix}{error}Sors de la zone KitFFA pour prendre ton kit.'));
            return;
        }

        Manager::FFA()->giveKit($sender);
        $sender->sendMessage(Manager::BRANDING()->format('{prefix}{secondary}Kit FFA récupéré.'));
    }
}
