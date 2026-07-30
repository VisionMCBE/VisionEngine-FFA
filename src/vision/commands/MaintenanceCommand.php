<?php

declare(strict_types=1);

namespace vision\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\ServerProperties;
use vision\Main;

final class MaintenanceCommand extends Command
{
    public function __construct(private readonly Main $plugin)
    {
        parent::__construct('maintenance', 'Active ou désactive la maintenance.', '/maintenance');
        $this->setPermission(DefaultPermissions::ROOT_OPERATOR);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void
    {
        if (!$this->testPermission($sender)) {
            return;
        }
        if ($sender instanceof Player && !$sender->getServer()->isOp($sender->getName())) {
            $sender->sendMessage($this->plugin->branding()->format('{prefix}{error}Commande réservée aux OP.'));
            return;
        }

        $server = $this->plugin->getServer();
        $enabled = !$server->hasWhitelist();
        $server->getConfigGroup()->setConfigBool(ServerProperties::WHITELIST, $enabled);

        if ($enabled) {
            foreach ($server->getOnlinePlayers() as $player) {
                if (!$server->isOp($player->getName())) {
                    $player->kick($this->plugin->branding()->format('{error}Le serveur est en maintenance.'));
                }
            }
            $server->broadcastMessage($this->plugin->branding()->format('{prefix}{error}Maintenance activée.'));
            return;
        }

        $message = $this->plugin->branding()->format('{prefix}{success}Maintenance désactivée.');
        if ($sender instanceof Player) {
            $sender->sendMessage($message);
        } else {
            $sender->sendMessage('Maintenance désactivée.');
        }
    }
}
