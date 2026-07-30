<?php

declare(strict_types=1);

namespace VisionEngineFFA\Managers;

use PDO;
use VisionEngineFFA\Ranks\RankComponent;
use VisionEngineFFA\Ranks\RankType;

final class RankManager
{
    public function __construct(private readonly PDO $database) {}

    public function initSchema(): void
    {
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS player_ranks ('
            . 'player_name VARCHAR(255) PRIMARY KEY, '
            . 'rank_name VARCHAR(64) NOT NULL, '
            . 'expires_at BIGINT NULL, '
            . 'updated_at BIGINT NOT NULL'
            . ')'
        );
    }

    public function getPlayerRank(string $player): RankComponent
    {
        $statement = $this->database->prepare('SELECT rank_name, expires_at FROM player_ranks WHERE player_name = :player');
        $statement->execute(['player' => strtolower($player)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return $this->rank(RankType::PAYSAN);
        }

        $expiresAt = (int) ($row['expires_at'] ?? 0);
        if ($expiresAt > 0 && $expiresAt <= time()) {
            return $this->rank(RankType::PAYSAN);
        }

        return $this->rank(RankType::fromString((string) $row['rank_name']) ?? RankType::PAYSAN);
    }

    public function rank(RankType $type): RankComponent
    {
        $i = 0;
        foreach (RankType::cases() as $case) {
            if ($case === $type) {
                return new RankComponent(RankType::enumToString($case), $i, $case->value);
            }
            ++$i;
        }
        return new RankComponent('Paysan', 0, '§7');
    }
}
