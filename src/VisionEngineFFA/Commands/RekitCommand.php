<?php

declare(strict_types=1);

namespace VisionEngineFFA\Commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use VisionEngineFFA\Main;

final class RekitCommand extends Command
{
    public function __construct(private readonly Main $plugin)
    {
        parent::__construct('rekit', 'Reprend le kit FFA.', '/rekit', ['refill']);
        $this->setPermission(DefaultPermissionNames::GROUP_USER);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void
    {
        if (!$this->testPermission($sender)) {
            return;
        }
        if (!$sender instanceof Player) {
            $sender->sendMessage('Commande en jeu uniquement.');
            return;
        }
        if ($this->plugin->ffa()->isInside($sender->getPosition())) {
            $sender->sendMessage($this->plugin->branding()->format('{prefix}{error}Sors de la zone KitFFA pour prendre ton kit.'));
            return;
        }

        $this->plugin->ffa()->giveKit($sender);
        $sender->sendMessage($this->plugin->branding()->format('{prefix}{secondary}Kit FFA récupéré.'));
    }
}
