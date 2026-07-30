<?php

declare(strict_types=1);

namespace VisionEngineFFA\Services;

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use VisionEngineFFA\Main;

final class PackSendEntry
{
    /** @var ClientboundPacket[] */
    private array $packets = [];
    private int $sendInterval = Main::DEFAULT_PACKET_SEND_INTERVAL;

    public function __construct(
        private readonly NetworkSession $session,
        private readonly int $queueKey
    ) {}

    public function addPacket(ClientboundPacket $packet): void
    {
        $this->packets[] = $packet;
    }

    public function tick(int $tick): void
    {
        if (!$this->session->isConnected()) {
            unset(Main::$packSendQueue[$this->queueKey]);
            return;
        }

        if ((($tick + $this->queueKey) % $this->sendInterval) !== 0) {
            return;
        }

        $next = array_shift($this->packets);
        if ($next instanceof ClientboundPacket) {
            $this->session->sendDataPacket($next);
            return;
        }

        unset(Main::$packSendQueue[$this->queueKey]);
    }
}
