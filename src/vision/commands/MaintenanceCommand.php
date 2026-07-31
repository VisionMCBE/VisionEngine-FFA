<?php

declare(strict_types=1);

namespace vision\commands;


use vision\managers\Manager;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\ServerProperties;
use vision\Main;

final class MaintenanceCommand extends Command {
    public function __construct(private readonly Main $plugin)  {
        parent::__construct('maintenance', 'Active ou désactive la maintenance.', '/maintenance');
        $this->setPermission(DefaultPermissions::ROOT_OPERATOR);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void  {
        if (!$this->testPermission($sender)) {
            return;
        }
        if ($sender instanceof Player && !$sender->getServer()->isOp($sender->getName())) {
            $sender->sendMessage(Manager::BRANDING()->format('{prefix}{error}Commande réservée aux OP.'));
            return;
        }

        $server = $this->plugin->getServer();
        $whitelist = !$server->hasWhitelist();
        $server->getConfigGroup()->setConfigBool(ServerProperties::WHITELIST, $whitelist);

        if ($whitelist) {
            array_map(function (Player $player) use ($server) {
                if (!$server->isOp($player->getName())) {
                    $player->kick(Manager::BRANDING()->format('{error}Le serveur est en maintenance.'));
                }
            }, $server->getOnlinePlayers());

            $server->broadcastMessage(Manager::BRANDING()->format('{prefix}{error}Le mode maintenance vient d’être activé.'));
            return;
        }

        $message = Manager::BRANDING()->format('{prefix}{success}Le mode maintenance vient d’être désactivé.');
        if ($sender instanceof Player) {
            $sender->sendMessage($message);
        } else {
            $sender->sendMessage('Le mode maintenance vient d’être désactivé.');
        }
    }
}
