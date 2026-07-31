<?php

declare(strict_types=1);

namespace vision\managers\player;

use pocketmine\player\Player;
use pocketmine\Server;
use vision\managers\Manager;

final class MatchManager {
    public function resolve(Player $winner, Player $loser, bool $disconnect = false): void {
        $loserStreak = Manager::STATS()->get($loser->getName())['streak'];
        $message = Manager::STATS()->killMessage($winner, $loser);
        Manager::STATS()->addDeath($loser->getName());
        Manager::STATS()->addKill($winner->getName());
        $eloChange = Manager::ELO()->recordKill($winner->getName(), $loser->getName(), $loserStreak);
        $winnerLeague = Manager::ELO()->league($winner->getName());
        $loserLeague = Manager::ELO()->league($loser->getName());

        $winner->sendPopup(($disconnect ? '§aVictoire par abandon : +' : '§aVictoire : +')
            . $eloChange['winner_gain'] . ' ELO §8- ' . $winnerLeague['color'] . $winnerLeague['name']
            . ($eloChange['anti_farm'] ? ' §c(Anti-farm)' : ''));
        if (!$disconnect) {
            $loser->sendPopup('§cDéfaite : -' . $eloChange['loser_loss'] . ' ELO §8- '
                . $loserLeague['color'] . $loserLeague['name']);
        }
        if ($winnerLeague['name'] !== $eloChange['winner_old_league']) {
            $winner->sendTitle('§9§lPROMOTION', $winnerLeague['color'] . $winnerLeague['name'], 10, 50, 15);
        }
        if (!$disconnect && $loserLeague['name'] !== $eloChange['loser_old_league']) {
            $loser->sendTitle('§c§lRÉTROGRADATION', $loserLeague['color'] . $loserLeague['name'], 10, 50, 15);
        }
        if ($eloChange['streak_bonus'] > 0) {
            $winner->sendMessage(Manager::BRANDING()->format('{prefix}{warning}Prime de série récupérée : {primary}+')
                . $eloChange['streak_bonus'] . Manager::BRANDING()->format(' {warning}ELO pour une série de {primary}')
                . $loserStreak . Manager::BRANDING()->format('{warning}.'));
        }

        Server::getInstance()->broadcastMessage($disconnect
            ? Manager::BRANDING()->format('{prefix}{primary}') . $loser->getName()
                . Manager::BRANDING()->format(' {secondary}a perdu son combat en se déconnectant face à {primary}')
                . $winner->getName() . Manager::BRANDING()->format('{secondary}.')
            : $message);

        Manager::COMBAT()->end($winner);
        Manager::FFA()->giveKit($winner);
        $winner->sendMessage(Manager::BRANDING()->format('{prefix}{success}Votre kit a été entièrement réapprovisionné.'));
        Manager::NAMETAG()->update($winner);
        if (!$disconnect) {
            Manager::NAMETAG()->update($loser, 0.0);
        }
    }
}
