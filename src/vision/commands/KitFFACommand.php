<?php

declare(strict_types=1);

namespace vision\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use vision\Main;

final class KitFFACommand extends Command
{
    public function __construct(private readonly Main $plugin)
    {
        parent::__construct('kitffa', 'Configure la zone KitFFA.', '/kitffa <pos1|pos2|info|givekit>');
        $this->setPermission(DefaultPermissions::ROOT_OPERATOR);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage('Commande en jeu uniquement.');
            return;
        }
        if (!$sender->getServer()->isOp($sender->getName())) {
            $sender->sendMessage($this->plugin->branding()->format('{prefix}{error}Commande réservée aux OP.'));
            return;
        }

        $sub = strtolower((string) ($args[0] ?? ''));
        if ($sub === 'pos1' || $sub === 'pos2') {
            $this->plugin->ffa()->setPos($sub === 'pos1' ? 1 : 2, $sender->getPosition());
            $sender->sendMessage($this->plugin->branding()->format('{prefix}{secondary}Position ') . strtoupper($sub) . $this->plugin->branding()->format(' enregistrée.'));
            return;
        }

        if ($sub === 'givekit') {
            $this->plugin->ffa()->giveKit($sender);
            $sender->sendMessage($this->plugin->branding()->format('{prefix}{secondary}Kit FFA donné.'));
            return;
        }

        if ($sub === 'info') {
            $bounds = $this->plugin->ffa()->bounds();
            if ($bounds === null) {
                $sender->sendMessage($this->plugin->branding()->format('{prefix}{error}Zone KitFFA incomplète.'));
                return;
            }
            $sender->sendMessage($this->plugin->branding()->format('{prefix}{secondary}Zone KitFFA: {primary}') . $bounds['world'] . $this->plugin->branding()->format(' {secondary}X ') . $bounds['minX'] . ' -> ' . $bounds['maxX'] . $this->plugin->branding()->format(' {secondary}Z ') . $bounds['minZ'] . ' -> ' . $bounds['maxZ']);
            return;
        }

        $sender->sendMessage($this->plugin->branding()->format('{prefix}{secondary}Utilisation: {primary}/kitffa pos1{secondary}, {primary}/kitffa pos2{secondary}, {primary}/kitffa info{secondary}, {primary}/kitffa givekit'));
    }
}
