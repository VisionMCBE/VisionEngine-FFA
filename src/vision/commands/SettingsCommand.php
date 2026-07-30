<?php

declare(strict_types=1);

namespace vision\commands;


use vision\form\SettingsForm;
use vision\managers\Manager;

use NayTools\form\CustomForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;

final class SettingsCommand extends Command {
    public function __construct()  {
        parent::__construct('settings', 'Parametres joueur.', '/settings');
        $this->setPermission(DefaultPermissionNames::GROUP_USER);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void  {
        if (!$sender instanceof Player) {
            $sender->sendMessage('Commande en jeu uniquement.');
            return;
        }

        SettingsForm::open($sender);
    }
}
