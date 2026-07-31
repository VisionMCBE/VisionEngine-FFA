<?php

declare(strict_types=1);

namespace vision\entities;

use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\entity\Skin;
use pocketmine\player\Player;

final class AIFightBot extends Human implements NeverSavedWithChunkEntity {
    public function __construct(
        Location $location,
        Skin $skin,
        private readonly string $ownerName,
        private readonly string $difficulty
    ) {
        parent::__construct($location, $skin);
        $this->setCanSaveWithChunk(false);
        $this->setNameTagVisible(true);
        $this->setNameTagAlwaysVisible(true);
        $this->updateNameTag();
    }

    public function getOwnerName(): string {
        return $this->ownerName;
    }

    public function getDifficulty(): string {
        return $this->difficulty;
    }

    public function spawnTo(Player $player): void {
        if (strcasecmp($player->getName(), $this->ownerName) === 0) {
            parent::spawnTo($player);
        }
    }

    public function updateNameTag(?float $health = null): void {
        $percentage = (int) round((($health ?? $this->getHealth()) / max(1.0, $this->getMaxHealth())) * 100);
        $color = $percentage > 60 ? '§a' : ($percentage > 30 ? '§e' : '§c');
        $this->setNameTag('§9IA §8- §f' . self::difficultyLabel($this->difficulty)
            . "\n" . $color . max(0, $percentage) . '§f%');
    }

    public static function difficultyLabel(string $difficulty): string {
        return match ($difficulty) {
            'easy' => 'Noob',
            'medium' => 'Semi-Pro',
            'hard' => 'Pro',
            default => 'Hacker',
        };
    }
}
