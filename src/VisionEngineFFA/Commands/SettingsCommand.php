<?php

declare(strict_types=1);

namespace VisionEngineFFA\Commands;

use NayTools\form\CustomForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use VisionEngineFFA\Main;

final class SettingsCommand extends Command
{
    public function __construct(private readonly Main $plugin)
    {
        parent::__construct('settings', 'Parametres joueur.', '/settings');
        $this->setPermission(DefaultPermissionNames::GROUP_USER);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage('Commande en jeu uniquement.');
            return;
        }

        $this->open($sender);
    }

    public function open(Player $sender): void
    {
        $colors = ['Defaut', 'Rouge', 'Vert', 'Bleu', 'Jaune', 'Rose', 'Cyan', 'Blanc'];
        $colorKeys = ['default', 'red', 'green', 'blue', 'yellow', 'pink', 'cyan', 'white'];
        $selectedColor = array_search($this->plugin->settings()->getPotionParticleColorName($sender->getName()), $colorKeys, true);
        if ($selectedColor === false) {
            $selectedColor = 0;
        }

        (new CustomForm('Parametres'))
            ->toggle('guided_potions', 'Potions teleguidees', $this->plugin->settings()->hasGuidedPotions($sender->getName()))
            ->toggle('scoreboard', 'Scoreboard', $this->plugin->settings()->hasScoreboard($sender->getName()))
            ->toggle('combat_visibility', 'Visibilite combat', $this->plugin->settings()->hasCombatVisibility($sender->getName()))
            ->dropdown('potion_particle_color', 'Couleur des particules de potion', $colors, $selectedColor)
            ->onSubmit(function (Player $player, array $data) use ($colorKeys): void {
                $this->plugin->settings()->setGuidedPotions($player->getName(), (bool) ($data['guided_potions'] ?? false));
                $this->plugin->settings()->setScoreboard($player->getName(), (bool) ($data['scoreboard'] ?? true));
                $this->plugin->settings()->setCombatVisibility($player->getName(), (bool) ($data['combat_visibility'] ?? true));
                $this->plugin->settings()->setPotionParticleColorName($player->getName(), $colorKeys[(int) ($data['potion_particle_color'] ?? 0)] ?? 'default');
                $player->sendMessage($this->plugin->branding()->format('{prefix}{secondary}Parametres sauvegardes.'));
            })
            ->sendToPlayer($sender);
    }
}
