<?php

declare(strict_types=1);

namespace vision\managers\display;

use pocketmine\player\Player;
use vision\managers\Manager;

final class NameTagManager {
    /** @var array<string, string> */
    private array $tags = [];

    public function update(Player $player, ?float $health = null): void {
        $league = Manager::ELO()->league($player->getName());
        $rank = Manager::RANK()->getPlayerRank($player->getName());
        $maximum = max(1.0, $player->getMaxHealth());
        $health ??= $player->getHealth();
        $percentage = (int) round((max(0.0, min($maximum, $health)) / $maximum) * 100);
        $tag = '§8[' . $league['color'] . $league['name'] . '§8] '
            . $rank->getColor() . $player->getName()
            . "\n§f" . $percentage . '§c%';
        $key = strtolower($player->getName());

        if (($this->tags[$key] ?? null) !== $tag || $player->getNameTag() !== $tag) {
            $this->tags[$key] = $tag;
            $player->setNameTag($tag);
        }
        $player->setNameTagVisible(true);
        $player->setNameTagAlwaysVisible(true);
    }

    public function remove(Player $player): void {
        unset($this->tags[strtolower($player->getName())]);
    }
}
