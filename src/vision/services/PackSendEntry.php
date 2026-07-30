<?php

declare(strict_types=1);

namespace vision\services;

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;

final class PackSendEntry
{
    /** @var ClientboundPacket[] */
    private array $packets = [];

    public function __construct(
        private readonly NetworkSession $session,
        private readonly int $sendInterval
    ) {}

    public function addPacket(ClientboundPacket $packet): void
    {
        $this->packets[] = $packet;
    }

    public function tick(int $tick, int $queueKey): bool
    {
        if (!$this->session->isConnected()) {
            return false;
        }

        if ((($tick + $queueKey) % $this->sendInterval) !== 0) {
            return true;
        }

        $next = array_shift($this->packets);
        if ($next instanceof ClientboundPacket) {
            $this->session->sendDataPacket($next);
            return true;
        }

        return false;
    }
}
