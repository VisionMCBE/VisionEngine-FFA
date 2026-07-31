<?php

declare(strict_types=1);

namespace vision\managers\player;

use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;
use vision\managers\Manager;

final class CombatManager {
    private const COMBAT_SECONDS = 15;
    private const WALL_HEIGHT = 5;
    private const DETECT_DISTANCE = 4;

    /** @var array<string, int> */
    private array $combatUntil = [];

    /** @var array<string, string> */
    private array $combatOpponent = [];

    /** @var array<string, string> */
    private array $names = [];

    /** @var array<string, bool> */
    private array $insideFfa = [];

    /** @var array<string, bool> */
    private array $hideLobbyPlayers = [];

    /** @var array<string, array<string, true>> */
    private array $sentBarrier = [];

    public function register(Player $player, bool $insideFfa): void {
        $key = strtolower($player->getName());
        $this->names[$key] = $player->getName();
        $this->combatUntil[$key] = 0;
        $this->insideFfa[$key] = $insideFfa;
        $this->hideLobbyPlayers[$key] = false;
    }

    public function unregister(Player $player): void {
        $key = strtolower($player->getName());
        Manager::COOLDOWN()->clearCombat($player->getName());
        unset($this->combatUntil[$key], $this->insideFfa[$key], $this->hideLobbyPlayers[$key], $this->sentBarrier[$key], $this->combatOpponent[$key], $this->names[$key]);
        foreach ($this->combatOpponent as $playerKey => $opponentKey) {
            if ($opponentKey === $key) {
                unset($this->combatOpponent[$playerKey]);
            }
        }
        $this->refreshAllVisibility();
    }

    public function start(Player $first, Player $second): void {
        $firstKey = strtolower($first->getName());
        $secondKey = strtolower($second->getName());
        $until = time() + self::COMBAT_SECONDS;
        $this->combatUntil[$firstKey] = $until;
        $this->combatUntil[$secondKey] = $until;
        $this->combatOpponent[$firstKey] = $secondKey;
        $this->combatOpponent[$secondKey] = $firstKey;
        Manager::COOLDOWN()->add($first->getName(), 'combat', self::COMBAT_SECONDS);
        Manager::COOLDOWN()->add($second->getName(), 'combat', self::COMBAT_SECONDS);
        Manager::COOLDOWN()->setCombatPair($first->getName(), $second->getName());
        $this->refreshAllVisibility();
    }

    public function isInCombat(Player $player): bool {
        return ($this->combatUntil[strtolower($player->getName())] ?? 0) > time();
    }

    public function activeOpponent(Player $player): ?string {
        if (!$this->isInCombat($player)) {
            return null;
        }
        $opponent = $this->combatOpponent[strtolower($player->getName())] ?? null;
        if ($opponent === null) {
            return null;
        }
        $target = Server::getInstance()->getPlayerExact($this->names[$opponent] ?? $opponent);
        return $target instanceof Player && $this->isInCombat($target) ? strtolower($target->getName()) : null;
    }

    public function opponent(Player $player): ?Player {
        $opponentKey = $this->activeOpponent($player);
        if ($opponentKey === null) {
            return null;
        }
        $opponent = Server::getInstance()->getPlayerExact($this->names[$opponentKey] ?? $opponentKey);
        return $opponent instanceof Player ? $opponent : null;
    }

    public function end(Player $player): void {
        $key = strtolower($player->getName());
        $opponent = $this->combatOpponent[$key] ?? null;
        $this->combatUntil[$key] = 0;
        unset($this->combatOpponent[$key]);
        Manager::COOLDOWN()->remove($player->getName(), 'combat');
        Manager::COOLDOWN()->clearCombat($player->getName());
        $this->clearWall($player);

        if ($opponent !== null && (($this->combatOpponent[$opponent] ?? null) === $key)) {
            unset($this->combatOpponent[$opponent]);
            $this->combatUntil[$opponent] = 0;
            $opponentPlayer = Server::getInstance()->getPlayerExact($this->names[$opponent] ?? $opponent);
            if ($opponentPlayer instanceof Player) {
                Manager::COOLDOWN()->remove($opponentPlayer->getName(), 'combat');
                Manager::COOLDOWN()->clearCombat($opponentPlayer->getName());
                $this->clearWall($opponentPlayer);
            }
        }
        $this->refreshAllVisibility();
    }

    public function wasInside(Player $player): bool {
        return $this->insideFfa[strtolower($player->getName())] ?? false;
    }

    public function setInside(Player $player, bool $inside): void {
        $this->insideFfa[strtolower($player->getName())] = $inside;
    }

    public function isLobbyHidden(Player $player): bool {
        return $this->hideLobbyPlayers[strtolower($player->getName())] ?? false;
    }

    public function setLobbyHidden(Player $player, bool $hidden): void {
        $this->hideLobbyPlayers[strtolower($player->getName())] = $hidden;
    }

    public function toggleLobbyVisibility(Player $player): bool {
        $hidden = !$this->isLobbyHidden($player);
        $this->setLobbyHidden($player, $hidden);
        $this->refreshVisibility($player);
        return $hidden;
    }

    public function refreshAllVisibility(): void {
        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            $this->refreshVisibility($player);
        }
    }

    public function refreshVisibility(Player $viewer): void {
        $viewerKey = strtolower($viewer->getName());
        $viewerInAiFight = Manager::AIFIGHT()->isFighting($viewer);
        $hideLobbyPlayers = ($this->insideFfa[$viewerKey] ?? false) && ($this->hideLobbyPlayers[$viewerKey] ?? false);
        $opponentKey = Manager::SETTINGS()->hasCombatVisibility($viewer->getName()) ? $this->activeOpponent($viewer) : null;

        foreach (Server::getInstance()->getOnlinePlayers() as $target) {
            if ($target === $viewer) {
                continue;
            }
            if (!$viewerInAiFight && !Manager::AIFIGHT()->isFighting($target)
                && !$hideLobbyPlayers && ($opponentKey === null || strtolower($target->getName()) === $opponentKey)) {
                $viewer->showPlayer($target);
            } else {
                $viewer->hidePlayer($target);
            }
        }
    }

    public function sendWallTowardBoth(Player $player, Position $position): void {
        $this->sendWallToward($player, $position);
        $opponentKey = $this->activeOpponent($player);
        if ($opponentKey === null) {
            return;
        }
        $opponent = Server::getInstance()->getPlayerExact($this->names[$opponentKey] ?? $opponentKey);
        if ($opponent instanceof Player) {
            $this->sendWallToward($opponent, $position);
        }
    }

    public function clearWall(Player $player): void {
        $key = strtolower($player->getName());
        $blocks = $this->sentBarrier[$key] ?? [];
        if ($blocks === []) {
            return;
        }
        $translator = TypeConverter::getInstance()->getBlockTranslator();
        foreach ($blocks as $posKey => $_) {
            [$x, $y, $z] = array_map('intval', explode(',', $posKey));
            $real = $player->getWorld()->getBlockAt($x, $y, $z);
            $player->getNetworkSession()->sendDataPacket(UpdateBlockPacket::create(
                new BlockPosition($x, $y, $z),
                $translator->internalIdToNetworkId($real->getStateId()),
                UpdateBlockPacket::FLAG_NETWORK,
                UpdateBlockPacket::DATA_LAYER_NORMAL
            ));
        }
        unset($this->sentBarrier[$key]);
    }

    private function sendWallToward(Player $player, Position $position): void {
        $bounds = Manager::FFA()->bounds();
        if ($bounds === null || $position->getWorld()->getFolderName() !== $bounds['world']) {
            return;
        }
        $x = $position->getFloorX();
        $z = $position->getFloorZ();
        $d = self::DETECT_DISTANCE;
        if ($x + $d >= $bounds['minX'] && $x <= $bounds['minX'] && $z >= $bounds['minZ'] && $z <= $bounds['maxZ']) {
            $this->sendWall($player, 'east', $bounds, $position);
        } elseif ($x - $d <= $bounds['maxX'] && $x >= $bounds['maxX'] && $z >= $bounds['minZ'] && $z <= $bounds['maxZ']) {
            $this->sendWall($player, 'west', $bounds, $position);
        } elseif ($z + $d >= $bounds['minZ'] && $z <= $bounds['minZ'] && $x >= $bounds['minX'] && $x <= $bounds['maxX']) {
            $this->sendWall($player, 'south', $bounds, $position);
        } elseif ($z - $d <= $bounds['maxZ'] && $z >= $bounds['maxZ'] && $x >= $bounds['minX'] && $x <= $bounds['maxX']) {
            $this->sendWall($player, 'north', $bounds, $position);
        }
    }

    private function sendWall(Player $player, string $side, array $bounds, Position $position): void {
        $key = strtolower($player->getName());
        $translator = TypeConverter::getInstance()->getBlockTranslator();
        $glassId = $translator->internalIdToNetworkId(VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::RED)->getStateId());
        $baseY = $position->getFloorY() - 1;
        $positions = [];
        if ($side === 'east' || $side === 'west') {
            $wallX = $side === 'east' ? $bounds['minX'] - 1 : $bounds['maxX'] + 1;
            for ($z = max($bounds['minZ'], $position->getFloorZ() - 8); $z <= min($bounds['maxZ'], $position->getFloorZ() + 8); ++$z) {
                for ($y = $baseY; $y < $baseY + self::WALL_HEIGHT; ++$y) {
                    $positions[] = [$wallX, $y, $z];
                }
            }
        } else {
            $wallZ = $side === 'south' ? $bounds['minZ'] - 1 : $bounds['maxZ'] + 1;
            for ($x = max($bounds['minX'], $position->getFloorX() - 8); $x <= min($bounds['maxX'], $position->getFloorX() + 8); ++$x) {
                for ($y = $baseY; $y < $baseY + self::WALL_HEIGHT; ++$y) {
                    $positions[] = [$x, $y, $wallZ];
                }
            }
        }
        foreach ($positions as [$x, $y, $z]) {
            $posKey = $x . ',' . $y . ',' . $z;
            if (isset($this->sentBarrier[$key][$posKey])) {
                continue;
            }
            $player->getNetworkSession()->sendDataPacket(UpdateBlockPacket::create(
                new BlockPosition($x, $y, $z),
                $glassId,
                UpdateBlockPacket::FLAG_NETWORK,
                UpdateBlockPacket::DATA_LAYER_NORMAL
            ));
            $this->sentBarrier[$key][$posKey] = true;
        }
    }
}
