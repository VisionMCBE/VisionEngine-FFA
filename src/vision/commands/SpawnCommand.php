<?php

declare(strict_types=1);

namespace vision\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\world\Position;
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

        if (Manager::COMBAT()->isInCombat($sender)) {
            $sender->sendMessage(Manager::BRANDING()->format('{prefix}{error}Vous ne pouvez pas vous téléporter au spawn pendant un combat.'));
            return;
        }

        $bounds = Manager::FFA()->bounds();
        if ($bounds === null) {
            $sender->sendMessage(Manager::BRANDING()->format('{prefix}{error}La zone KitFFA n’est pas encore entièrement configurée.'));
            return;
        }

        $worldManager = $sender->getServer()->getWorldManager();
        $worldManager->loadWorld($bounds['world']);
        $world = $worldManager->getWorldByName($bounds['world']);
        if ($world === null) {
            $sender->sendMessage(Manager::BRANDING()->format('{prefix}{error}Le monde de la zone KitFFA est indisponible.'));
            return;
        }

        $sender->teleport(new Position(
            (($bounds['minX'] + $bounds['maxX']) / 2) + 0.5,
            (($bounds['minY'] + $bounds['maxY']) / 2) + 1,
            (($bounds['minZ'] + $bounds['maxZ']) / 2) + 0.5,
            $world
        ));
        $sender->sendMessage(Manager::BRANDING()->format('{prefix}{success}Vous avez été téléporté au spawn.'));
    }
}
