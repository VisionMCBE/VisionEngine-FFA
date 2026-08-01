<?php

declare(strict_types=1);

namespace vision\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use vision\managers\Manager;

final class SpecCommand extends Command {
    public function __construct() {
        parent::__construct('spec', 'Active ou désactive le mode spectateur.', '/spec');
        $this->setPermission(DefaultPermissionNames::GROUP_USER);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage('Cette commande doit être utilisée en jeu.');
            return;
        }

        if ($sender->getGamemode()->equals(GameMode::SPECTATOR())) {
            $spawn = Manager::FFA()->spawnPosition();
            if ($spawn === null) {
                $sender->sendMessage(Manager::BRANDING()->format('{prefix}{error}Le spawn KitFFA est indisponible ou incomplet.'));
                return;
            }

            $sender->setGamemode(GameMode::SURVIVAL());
            $sender->teleport($spawn);
            Manager::FFA()->clearCombatEffects($sender);
            Manager::COMBAT()->setInside($sender, true);
            Manager::FFA()->giveLobbyItems($sender, Manager::COMBAT()->isLobbyHidden($sender));
            Manager::COMBAT()->refreshVisibility($sender);
            $sender->sendMessage(Manager::BRANDING()->format('{prefix}{success}Vous êtes repassé en mode survie.'));
            return;
        }

        if (Manager::COMBAT()->isInCombat($sender) || Manager::AIFIGHT()->isFighting($sender)) {
            $sender->sendMessage(Manager::BRANDING()->format('{prefix}{error}Vous ne pouvez pas passer en mode spectateur pendant un combat.'));
            return;
        }

        $sender->setGamemode(GameMode::SPECTATOR());
        Manager::COMBAT()->refreshVisibility($sender);
        $sender->sendMessage(Manager::BRANDING()->format('{prefix}{success}Vous êtes maintenant en mode spectateur.'));
    }
}
