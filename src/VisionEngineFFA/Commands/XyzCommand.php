<?php

declare(strict_types=1);

namespace VisionEngineFFA\Commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissions;
use VisionEngineFFA\Main;

final class XyzCommand extends Command
{
    public function __construct(private readonly Main $plugin)
    {
        parent::__construct('xyz', 'Active ou désactive les coordonnées scoreboard.', '/xyz <on|off>');
        $this->setPermission(DefaultPermissions::ROOT_OPERATOR);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void
    {
        if (!$this->testPermission($sender)) {
            return;
        }

        $mode = strtolower((string) ($args[0] ?? ''));
        if ($mode !== 'on' && $mode !== 'off') {
            $sender->sendMessage($this->plugin->branding()->format('{prefix}{secondary}Utilisation: {primary}/xyz on{secondary}, {primary}/xyz off'));
            return;
        }

        $enabled = $mode === 'on';
        $this->plugin->getConfig()->setNested('scoreboard.show_xyz', $enabled);
        $this->plugin->getConfig()->save();

        $sender->sendMessage($this->plugin->branding()->format('{prefix}{secondary}Coordonnées scoreboard: ') . ($enabled ? $this->plugin->branding()->format('{success}activées') : $this->plugin->branding()->format('{error}désactivées')));
    }
}
