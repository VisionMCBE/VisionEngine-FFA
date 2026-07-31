<?php

declare(strict_types=1);

namespace vision\managers\display;


use vision\managers\Manager;

use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\player\Player;
use pocketmine\Server;
final class ScoreboardManager {
    private const MAX_LINES = 15;

    /** @var array<string, string> */
    private array $titles = [];

    /** @var array<string, array<int, string>> */
    private array $lines = [];

    /** @var array<string, int> */
    private array $lastLines = [];

    public function update(Player $player): void  {
        if (!Manager::SETTINGS()->hasScoreboard($player->getName())) {
            $this->remove($player);
            return;
        }

        $combat = Manager::COOLDOWN()->remaining($player->getName(), 'combat');
        $pearl = Manager::COOLDOWN()->remaining($player->getName(), 'pearl');
        $brand = Manager::BRANDING();
        $league = Manager::ELO()->league($player->getName());
        $players = count(Server::getInstance()->getOnlinePlayers());

        $this->title($player, $combat > 0 ? $brand->combatTitle() : $brand->scoreboardTitle());
        $this->line($player, 1, '§9§l' . $player->getName() . '§r');
        $this->line($player, 2, ' ');
        $this->line($player, 3, '§fJoueurs: §9' . $players);
        $this->line($player, 4, '§fLigue: ' . $league['color'] . $league['name']);

        if ($combat <= 0) {
            $this->line($player, 5, '  ');
            $this->line($player, 6, $brand->format('§9{server_ip}'));
            $this->clearAfter($player, 6);
            return;
        }

        $opponent = Manager::COOLDOWN()->combatOpponent($player->getName()) ?? 'Inconnu';
        $this->line($player, 5, '  ');
        $this->line($player, 6, '§9§lCombat§r');
        $this->line($player, 7, '§fAdversaire: §9' . $opponent);
        $this->line($player, 8, '§fTemps: §9' . $combat . 's');
        $this->line($player, 9, '§fEnderpearl: §9' . ($pearl > 0 ? $pearl . 's' : 'Prête'));
        $this->line($player, 10, '   ');
        $this->line($player, 11, $brand->format('§9{server_ip}'));
        $this->clearAfter($player, 11);
    }

    private function title(Player $player, string $title): void  {
        $key = strtolower($player->getName());
        if (($this->titles[$key] ?? null) === $title) {
            return;
        }
        $this->titles[$key] = $title;

        $packet = new SetDisplayObjectivePacket();
        $packet->displaySlot = 'sidebar';
        $packet->objectiveName = 'objective';
        $packet->displayName = $title;
        $packet->criteriaName = 'dummy';
        $packet->sortOrder = 0;
        $player->getNetworkSession()->sendDataPacket($packet);
    }

    private function line(Player $player, int $line, string $content): void  {
        $key = strtolower($player->getName());
        if (($this->lines[$key][$line] ?? null) === $content) {
            return;
        }
        $this->lines[$key][$line] = $content;

        $entry = new ScorePacketEntry();
        $entry->objectiveName = 'objective';
        $entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
        $entry->customName = $content;
        $entry->score = $line;
        $entry->scoreboardId = $line;
        $packet = new SetScorePacket();
        $packet->type = SetScorePacket::TYPE_CHANGE;
        $packet->entries[] = $entry;
        $player->getNetworkSession()->sendDataPacket($packet);
    }

    private function clearAfter(Player $player, int $lastLine): void  {
        $key = strtolower($player->getName());
        $previousLastLine = $this->lastLines[$key] ?? 0;
        $this->lastLines[$key] = $lastLine;
        if ($previousLastLine <= $lastLine) {
            return;
        }

        $entries = [];
        for ($line = $lastLine + 1; $line <= min($previousLastLine, self::MAX_LINES); ++$line) {
            $entry = new ScorePacketEntry();
            $entry->objectiveName = 'objective';
            $entry->score = $line;
            $entry->scoreboardId = $line;
            $entries[] = $entry;
            unset($this->lines[$key][$line]);
        }
        if ($entries === []) {
            return;
        }
        $packet = new SetScorePacket();
        $packet->type = SetScorePacket::TYPE_REMOVE;
        $packet->entries = $entries;
        $player->getNetworkSession()->sendDataPacket($packet);
    }

    public function remove(Player $player): void  {
        $key = strtolower($player->getName());
        if (!isset($this->titles[$key])) {
            return;
        }

        $packet = new RemoveObjectivePacket();
        $packet->objectiveName = 'objective';
        $player->getNetworkSession()->sendDataPacket($packet);
        unset($this->titles[$key], $this->lines[$key], $this->lastLines[$key]);
    }
}
