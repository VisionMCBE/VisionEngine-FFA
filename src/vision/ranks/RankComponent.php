<?php

declare(strict_types=1);

namespace vision\ranks;

final class RankComponent
{
    public function __construct(
        private readonly string $name,
        private readonly int $id,
        private readonly string $color
    ) {}

    public function getName(): string { return $this->name; }
    public function getId(): int { return $this->id; }
    public function getColor(): string { return $this->color; }
}
