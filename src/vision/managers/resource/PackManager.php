<?php

declare(strict_types=1);

namespace vision\managers\resource;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ResourcePackChunkDataPacket;
use pocketmine\network\mcpe\protocol\ResourcePackChunkRequestPacket;
use pocketmine\network\mcpe\protocol\ResourcePackDataInfoPacket;
use pocketmine\utils\Config;
use vision\Main;
use vision\services\PackSendEntry;
use WeakMap;

final class PackManager implements Listener {
    public const CHUNK_SIZE = 512 * 1024;
    public const PACKET_SEND_INTERVAL = 3;

    /** @var WeakMap<NetworkSession, array<string, array<int, true>>> */
    private WeakMap $requestedChunks;

    /** @var array<int, PackSendEntry> */
    private array $sendQueue = [];

    public function __construct(private readonly Main $plugin)  {
        $this->requestedChunks = new WeakMap();
    }

    public function install(): void  {
        $packName = 'VisionPackFFA.zip';
        $this->plugin->saveResource($packName, true);

        $resourcePackDir = $this->plugin->getServer()->getDataPath() . 'resource_packs';
        if (!is_dir($resourcePackDir)) {
            mkdir($resourcePackDir);
        }

        $source = $this->plugin->getDataFolder() . $packName;
        $target = $resourcePackDir . DIRECTORY_SEPARATOR . $packName;
        if (!file_exists($source) || !$this->isValidZip($source)) {
            $this->plugin->getLogger()->warning('Pack resource invalide ou introuvable : ' . $packName);
            return;
        }

        $temporaryTarget = $target . '.tmp';
        if (file_exists($temporaryTarget)) {
            @unlink($temporaryTarget);
        }
        copy($source, $temporaryTarget);
        if (!$this->isValidZip($temporaryTarget)) {
            @unlink($temporaryTarget);
            $this->plugin->getLogger()->warning('Copie du pack invalide : ' . $packName);
            return;
        }
        rename($temporaryTarget, $target);

        $config = new Config($resourcePackDir . DIRECTORY_SEPARATOR . 'resource_packs.yml', Config::YAML, [
            'force_resources' => true,
            'resource_stack' => [],
        ]);
        $stack = $config->get('resource_stack', []);
        if (!is_array($stack)) {
            $stack = [];
        }
        if (!in_array($packName, $stack, true)) {
            $stack[] = $packName;
            $config->set('resource_stack', $stack);
            $this->plugin->getLogger()->warning($packName . ' installé. Redémarre le serveur une fois pour que PocketMine le charge.');
        }
        $config->save();
    }

    public function onDataPacketSend(DataPacketSendEvent $event): void  {
        $packets = $event->getPackets();
        $changed = false;
        foreach ($packets as $index => $packet) {
            if (!$packet instanceof ResourcePackDataInfoPacket) {
                continue;
            }
            $packets[$index] = ResourcePackDataInfoPacket::create(
                $packet->packId,
                self::CHUNK_SIZE,
                (int) ceil($packet->compressedPackSize / self::CHUNK_SIZE),
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

    public function onDataPacketReceive(DataPacketReceiveEvent $event): void  {
        $packet = $event->getPacket();
        if (!$packet instanceof ResourcePackChunkRequestPacket) {
            return;
        }

        $session = $event->getOrigin();
        $pack = $this->plugin->getServer()->getResourcePackManager()->getPackById($packet->packId);
        if ($pack === null) {
            return;
        }

        $offset = $packet->chunkIndex * self::CHUNK_SIZE;
        if ($packet->chunkIndex < 0 || $offset < 0 || $offset >= $pack->getPackSize()) {
            $event->cancel();
            $session->disconnectWithError('Invalid resource pack chunk request');
            return;
        }

        $requested = $this->requestedChunks[$session] ?? [];
        $packId = strtolower($pack->getPackId());
        if (isset($requested[$packId][$packet->chunkIndex])) {
            $event->cancel();
            return;
        }
        $requested[$packId][$packet->chunkIndex] = true;
        $this->requestedChunks[$session] = $requested;
        $event->cancel();

        $queueKey = spl_object_id($session);
        $entry = $this->sendQueue[$queueKey] ??= new PackSendEntry($session, self::PACKET_SEND_INTERVAL);
        $entry->addPacket(ResourcePackChunkDataPacket::create(
            $pack->getPackId(),
            $packet->chunkIndex,
            $offset,
            $pack->getPackChunk($offset, self::CHUNK_SIZE)
        ));
    }

    public function tick(int $tick): void  {
        foreach ($this->sendQueue as $key => $entry) {
            if (!$entry->tick($tick, $key)) {
                unset($this->sendQueue[$key]);
            }
        }
    }

    public function clear(): void  {
        $this->sendQueue = [];
    }

    private function isValidZip(string $path): bool  {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }
        $valid = $zip->numFiles > 0 && $zip->getNameIndex(0) === 'manifest.json';
        $zip->close();
        return $valid;
    }
}
