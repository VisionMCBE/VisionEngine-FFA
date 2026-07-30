<?php

declare(strict_types=1);

namespace vision\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissionNames;
use vision\Main;

final class StatsCommand extends Command
{
    public function __construct(private readonly Main $plugin)
    {
        parent::__construct('stats', 'Affiche les stats FFA.', '/stats [joueur]');
        $this->setPermission(DefaultPermissionNames::GROUP_USER);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void
    {
        if (!$this->testPermission($sender)) {
            return;
        }

        $target = (string) ($args[0] ?? $sender->getName());
        $stats = $this->plugin->stats()->get($target);
        $kd = number_format($this->plugin->stats()->kd($target), 2);
        $brand = $this->plugin->branding();

        $sender->sendMessage($brand->format('{prefix}{primary}Stats FFA de {text}') . $target);
        $sender->sendMessage($brand->format('{secondary}Kills : {primary}') . $stats['kills']);
        $sender->sendMessage($brand->format('{secondary}Morts : {primary}') . $stats['deaths']);
        $sender->sendMessage($brand->format('{secondary}K/D : {primary}') . $kd);
        $sender->sendMessage($brand->format('{secondary}Streak actuel : {primary}') . $stats['streak']);
        $sender->sendMessage($brand->format('{secondary}Meilleur streak : {primary}') . $stats['best_streak']);
    }
}
