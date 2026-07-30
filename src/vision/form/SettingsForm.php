<?php

namespace vision\form;

use pocketmine\player\Player;
use vision\managers\Manager;

class SettingsForm {
    public static function open(Player $sender): void  {
        $colors = ['Defaut', 'Rouge', 'Vert', 'Bleu', 'Jaune', 'Rose', 'Cyan', 'Blanc'];
        $colorKeys = ['default', 'red', 'green', 'blue', 'yellow', 'pink', 'cyan', 'white'];
        $selectedColor = array_search(Manager::SETTINGS()->getPotionParticleColorName($sender->getName()), $colorKeys, true);
        if ($selectedColor === false) {
            $selectedColor = 0;
        }

        (new CustomForm('Parametres'))
            ->toggle('guided_potions', 'Potions teleguidees', Manager::SETTINGS()->hasGuidedPotions($sender->getName()))
            ->toggle('scoreboard', 'Scoreboard', Manager::SETTINGS()->hasScoreboard($sender->getName()))
            ->toggle('combat_visibility', 'Visibilite combat', Manager::SETTINGS()->hasCombatVisibility($sender->getName()))
            ->dropdown('potion_particle_color', 'Couleur des particules de potion', $colors, $selectedColor)
            ->onSubmit(function (Player $player, array $data) use ($colorKeys): void {
                Manager::SETTINGS()->setGuidedPotions($player->getName(), (bool) ($data['guided_potions'] ?? false));
                Manager::SETTINGS()->setScoreboard($player->getName(), (bool) ($data['scoreboard'] ?? true));
                Manager::SETTINGS()->setCombatVisibility($player->getName(), (bool) ($data['combat_visibility'] ?? true));
                Manager::SETTINGS()->setPotionParticleColorName($player->getName(), $colorKeys[(int) ($data['potion_particle_color'] ?? 0)] ?? 'default');
                $player->sendMessage(Manager::BRANDING()->format('{prefix}{secondary}Parametres sauvegardes.'));
            })
            ->sendToPlayer($sender);
    }
}