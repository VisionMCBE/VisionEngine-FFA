<?php

declare(strict_types=1);

namespace vision\commands;

use vision\managers\Manager;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use vision\Main;

final class KitFFACommand extends Command {
    public function __construct() {
        parent::__construct('kitffa', 'Configure la zone KitFFA.', '/kitffa <pos1|pos2|info|givekit>');
        $this->setPermission(DefaultPermissions::ROOT_OPERATOR);
        $this->setPermissionMessage(Manager::BRANDING()->format('{prefix}{error}Commande réservée aux OP.'));
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage('Cette commande doit être utilisée en jeu.');
            return;
        }

        $sub = strtolower((string) ($args[0] ?? ''));
        if ($sub === 'pos1' || $sub === 'pos2') {
            Manager::FFA()->setPos($sub === 'pos1' ? 1 : 2, $sender->getPosition());
            $sender->sendMessage(Manager::BRANDING()->format('{prefix}{secondary}Position ') . strtoupper($sub) . Manager::BRANDING()->format(' enregistrée.'));
            return;
        }

        if ($sub === 'givekit') {
            Manager::FFA()->giveKit($sender);
            $sender->sendMessage(Manager::BRANDING()->format('{prefix}{success}Le kit FFA vous a bien été donné.'));
            return;
        }

        if ($sub === 'info') {
            $bounds = Manager::FFA()->bounds();
            if ($bounds === null) {
                $sender->sendMessage(Manager::BRANDING()->format('{prefix}{error}La zone KitFFA n’est pas encore entièrement configurée.'));
                return;
            }
            $sender->sendMessage(Manager::BRANDING()->format('{prefix}{secondary}Zone KitFFA: {primary}') . $bounds['world'] . Manager::BRANDING()->format(' {secondary}X ') . $bounds['minX'] . ' -> ' . $bounds['maxX'] . Manager::BRANDING()->format(' {secondary}Z ') . $bounds['minZ'] . ' -> ' . $bounds['maxZ']);
            return;
        }

        $sender->sendMessage(Manager::BRANDING()->format('{prefix}{secondary}Utilisation: {primary}/kitffa pos1{secondary}, {primary}/kitffa pos2{secondary}, {primary}/kitffa info{secondary}, {primary}/kitffa givekit'));
    }
}
