<?php

declare(strict_types=1);

namespace vision\events;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ResourcePackChunkDataPacket;
use pocketmine\network\mcpe\protocol\ResourcePackChunkRequestPacket;
use pocketmine\network\mcpe\protocol\ResourcePackDataInfoPacket;
use vision\Main;
use vision\services\PackSendEntry;
use WeakMap;

final class ResourcePackSendListener implements Listener
{
    /** @var WeakMap<NetworkSession, array<string, array<int, true>>> */
    private WeakMap $requestedChunks;

    public function __construct()
    {
        $this->requestedChunks = new WeakMap();
    }

    public function onDataPacketSend(DataPacketSendEvent $event): void
    {
        $packets = $event->getPackets();
        $changed = false;

        foreach ($packets as $index => $packet) {
            if (!$packet instanceof ResourcePackDataInfoPacket) {
                continue;
            }

            $chunkCount = (int) ceil($packet->compressedPackSize / Main::DEFAULT_CHUNK_SIZE);
            $packets[$index] = ResourcePackDataInfoPacket::create(
                $packet->packId,
                Main::DEFAULT_CHUNK_SIZE,
                $chunkCount,
                $packet->compressedPackSize,
                $packet->sha256,
                $packet->isPremium,
                $packet->packType
            );
            $changed = true;
        }

        if ($changed) {
            $event->setPackets($packets);
        }
    }

    public function onDataPacketReceive(DataPacketReceiveEvent $event): void
    {
        $packet = $event->getPacket();
        if (!$packet instanceof ResourcePackChunkRequestPacket) {
            return;
        }

        $session = $event->getOrigin();
        $pack = Main::getInstance()->getServer()->getResourcePackManager()->getPackById($packet->packId);
        if ($pack === null) {
            return;
        }

        $chunkIndex = $packet->chunkIndex;
        $offset = $chunkIndex * Main::DEFAULT_CHUNK_SIZE;
        if ($chunkIndex < 0 || $offset < 0 || $offset >= $pack->getPackSize()) {
            $event->cancel();
            $session->disconnectWithError('Invalid resource pack chunk request');
            return;
        }

        $requested = $this->requestedChunks[$session] ?? [];
        $packId = strtolower($pack->getPackId());
        if (isset($requested[$packId][$chunkIndex])) {
            $event->cancel();
            return;
        }

        $requested[$packId][$chunkIndex] = true;
        $this->requestedChunks[$session] = $requested;
        $event->cancel();

        $queueKey = spl_object_id($session);
        $entry = Main::$packSendQueue[$queueKey] ??= new PackSendEntry($session, $queueKey);
        $entry->addPacket(ResourcePackChunkDataPacket::create(
            $pack->getPackId(),
            $chunkIndex,
            $offset,
            $pack->getPackChunk($offset, Main::DEFAULT_CHUNK_SIZE)
        ));
    }
}
