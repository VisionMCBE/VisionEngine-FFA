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
        parent::__construct('settings', 'Ouvre vos paramètres personnels.', '/settings');
        $this->setPermission(DefaultPermissionNames::GROUP_USER);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void  {
        if (!$sender instanceof Player) {
            $sender->sendMessage('Cette commande doit être utilisée en jeu.');
            return;
        }

        SettingsForm::open($sender);
    }
}
