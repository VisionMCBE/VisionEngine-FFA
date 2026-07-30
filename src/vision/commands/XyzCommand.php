<?php

declare(strict_types=1);

namespace vision\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;
use pocketmine\network\mcpe\protocol\types\BoolGameRule;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use vision\Main;

final class XyzCommand extends Command
{
    public function __construct(private readonly Main $plugin)
    {
        parent::__construct('xyz', 'Active ou désactive les coordonnées.', '/xyz <on|off>');
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
        $this->plugin->getConfig()->setNested('coordinates.enabled', $enabled);
        $this->plugin->getConfig()->save();

        foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
            self::applyCoordinates($player, $enabled);
        }
        $sender->sendMessage($this->plugin->branding()->format('{prefix}{secondary}Coordonnées : ') . ($enabled ? $this->plugin->branding()->format('{success}activées') : $this->plugin->branding()->format('{error}désactivées')));
    }

    public static function applyCoordinates(Player $player, bool $show): void
    {
        $packet = new GameRulesChangedPacket();
        $packet->gameRules = ['showcoordinates' => new BoolGameRule($show, false)];
        $player->getNetworkSession()->sendDataPacket($packet);
    }
}
