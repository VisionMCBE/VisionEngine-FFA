<?php

declare(strict_types=1);

namespace vision\managers\security;


use vision\managers\Manager;

use pocketmine\block\Ladder;
use pocketmine\block\Liquid;
use pocketmine\block\utils\Fallable;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Entity;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\math\AxisAlignedBB;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;
use vision\ranks\RankType;

final class AntiCheatManager implements Listener {
    private const KICK_THRESHOLD = 6;
    private const DECAY_SECONDS = 30;
    private const ALERT_COOLDOWN = 5;
    private const REACH_MAX = 4.45;
    private const REACH_BUFFER_LIMIT = 3;
    private const CPS_MAX = 18;
    private const NUKER_BLOCKS_PER_SECOND = 8;
    private const NUKER_SPREAD_LIMIT = 4.5;
    private const NUKER_REACH_LIMIT = 7.0;
    private const PHASE_BUFFER_LIMIT = 4;
    private const GRAVITY = 0.08;
    private const VERT_DRAG = 0.98;
    private const FLY_GRACE = 10;
    private const FLY_TOLERANCE = 0.05;
    private const FLY_LIMIT = 12;

    /** @var array<string, int> */
    private array $reachViolations = [];
    /** @var array<string, array{prevHoriz: float, speedBuf: float, air: int, tpExempt: int, kbExempt: int, prevDy: float, flyViol: float, phaseBuf: float}> */
    private array $state = [];
    /** @var array<string, list<float>> */
    private array $clicks = [];
    /** @var array<string, list<array{t: float, x: int, y: int, z: int}>> */
    private array $breaks = [];

    /** @var array<string, array<string, array{vl: float, last: int, lastAlert: int}>> */
    private array $violations = [];

    private static ?self $instance = null;

    public function __construct()  {
        self::$instance = $this;
    }

    public static function getInstance(): ?self  {
        return self::$instance;
    }

    public function grantMovementExemption(Player $player, int $ticks): void  {
        $key = strtolower($player->getName());
        $this->state[$key] ??= $this->emptyState();
        $this->state[$key]['kbExempt'] = max($this->state[$key]['kbExempt'], Server::getInstance()->getTick() + $ticks);
    }

    public function onMove(PlayerMoveEvent $event): void  {
        $player = $event->getPlayer();
        $key = strtolower($player->getName());
        if (!$this->shouldCheck($player)) {
            unset($this->state[$key]);
            return;
        }

        $this->state[$key] ??= $this->emptyState();
        $st = &$this->state[$key];
        $tick = Server::getInstance()->getTick();
        $from = $event->getFrom();
        $to = $event->getTo();
        $dx = $to->x - $from->x;
        $dy = $to->y - $from->y;
        $dz = $to->z - $from->z;
        $horiz = sqrt($dx * $dx + $dz * $dz);

        $this->checkSpeed($player, $st, $tick, $horiz);
        $this->checkFly($player, $st, $tick, $dy);
        $this->checkPhase($player, $st, $tick, $dx, $dy, $dz);
        $st['prevHoriz'] = $horiz;
    }

    public function onDamage(EntityDamageByEntityEvent $event): void  {
        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if (!$damager instanceof Player || !$victim instanceof Entity || !$this->shouldCheck($damager)) {
            return;
        }

        $this->trackCps($damager);
        $this->checkReach($damager, $victim);
        $this->grantMovementExemption($damager, 8);
        if ($victim instanceof Player) {
            $this->grantMovementExemption($victim, 12);
        }
    }

    public function onBreak(BlockBreakEvent $event): void  {
        $player = $event->getPlayer();
        if (!$this->shouldCheck($player)) {
            return;
        }

        $key = strtolower($player->getName());
        $pos = $event->getBlock()->getPosition();
        $now = microtime(true);
        $this->breaks[$key][] = ['t' => $now, 'x' => $pos->getFloorX(), 'y' => $pos->getFloorY(), 'z' => $pos->getFloorZ()];
        $this->breaks[$key] = array_values(array_filter($this->breaks[$key], static fn(array $entry): bool => $now - $entry['t'] <= 1.0));

        if (count($this->breaks[$key]) < self::NUKER_BLOCKS_PER_SECOND) {
            return;
        }

        $first = $this->breaks[$key][0];
        $spread = 0.0;
        foreach ($this->breaks[$key] as $entry) {
            $spread = max($spread, sqrt(($entry['x'] - $first['x']) ** 2 + ($entry['y'] - $first['y']) ** 2 + ($entry['z'] - $first['z']) ** 2));
        }

        $reach = $player->getPosition()->distance($pos);
        if ($spread >= self::NUKER_SPREAD_LIMIT || $reach >= self::NUKER_REACH_LIMIT) {
            $this->flag($player, 'Nuker', count($this->breaks[$key]) . ' blocs/s, portée ' . round($reach, 2));
        }
    }

    public function onTeleport(EntityTeleportEvent $event): void  {
        $entity = $event->getEntity();
        if (!$entity instanceof Player) {
            return;
        }
        $key = strtolower($entity->getName());
        $this->state[$key] ??= $this->emptyState();
        $this->state[$key]['tpExempt'] = Server::getInstance()->getTick() + 20;
    }

    public function onQuit(PlayerQuitEvent $event): void  {
        $key = strtolower($event->getPlayer()->getName());
        unset($this->state[$key], $this->clicks[$key], $this->breaks[$key], $this->reachViolations[$key]);
        $this->clear($event->getPlayer());
    }

    private function shouldCheck(Player $player): bool  {
        return $player->isConnected()
            && !$player->getGamemode()->equals(GameMode::CREATIVE())
            && !$player->getGamemode()->equals(GameMode::SPECTATOR());
    }

    private function checkSpeed(Player $player, array &$st, int $tick, float $horiz): void  {
        if ($tick < $st['tpExempt'] || $tick < $st['kbExempt'] || $this->inLiquid($player) || $this->onIce($player)) {
            $st['speedBuf'] = 0.0;
            return;
        }

        $base = max(0.38, $player->getMovementSpeed() * 3.8);
        if (!$player->isOnGround()) {
            $base += 0.12;
        }

        if ($horiz > $base) {
            $st['speedBuf'] += $horiz - $base;
            if ($st['speedBuf'] > 0.8) {
                $st['speedBuf'] = 0.0;
                $this->flag($player, 'Speed', 'vitesse ' . round($horiz, 3) . ' > ' . round($base, 3));
            }
        } else {
            $st['speedBuf'] = max(0.0, $st['speedBuf'] - 0.08);
        }
    }

    private function checkFly(Player $player, array &$st, int $tick, float $dy): void  {
        $effects = $player->getEffects();
        if ($player->isOnGround() || $this->inLiquid($player) || $this->onClimbable($player)
            || $effects->has(VanillaEffects::LEVITATION()) || $effects->has(VanillaEffects::SLOW_FALLING())
            || $tick < $st['tpExempt'] || $tick < $st['kbExempt']) {
            $st['air'] = 0;
            $st['flyViol'] = 0.0;
            $st['prevDy'] = $dy;
            return;
        }

        ++$st['air'];
        $expected = ($st['prevDy'] - self::GRAVITY) * self::VERT_DRAG;
        $jumpBoost = $effects->get(VanillaEffects::JUMP_BOOST());
        $verticalTolerance = self::FLY_TOLERANCE + min(2.0, ($jumpBoost?->getEffectLevel() ?? 0) / 10);
        if ($st['air'] > self::FLY_GRACE && $dy > $expected + $verticalTolerance) {
            $st['flyViol'] += 1.0;
            if ($st['flyViol'] >= self::FLY_LIMIT) {
                $st['flyViol'] = 0.0;
                $this->flag($player, 'Fly', 'dy ' . round($dy, 3) . ', attendu ' . round($expected, 3));
            }
        } else {
            $st['flyViol'] = max(0.0, $st['flyViol'] - 0.5);
        }
        $st['prevDy'] = $dy;
    }

    private function checkPhase(Player $player, array &$st, int $tick, float $dx, float $dy, float $dz): void  {
        if ($tick < $st['tpExempt'] || $tick < $st['kbExempt'] || abs($dx) + abs($dy) + abs($dz) < 0.0001) {
            $st['phaseBuf'] = 0.0;
            return;
        }

        $box = $player->getBoundingBox()->offsetCopy($dx, $dy, $dz);
        if ($this->touchesFallableBlock($player, $box)) {
            $st['phaseBuf'] = 0.0;
            return;
        }

        $collisions = $player->getWorld()->getCollisionBoxes($player, $box, false);
        if ($collisions === []) {
            $st['phaseBuf'] = max(0.0, $st['phaseBuf'] - 1.0);
            return;
        }

        $st['phaseBuf'] += 1.0;
        if ($st['phaseBuf'] >= self::PHASE_BUFFER_LIMIT) {
            $st['phaseBuf'] = 0.0;
            $this->flag($player, 'Phase', count($collisions) . ' collision(s) solide(s)');
        }
    }

    private function trackCps(Player $player): void  {
        $key = strtolower($player->getName());
        $now = microtime(true);
        $this->clicks[$key][] = $now;
        $this->clicks[$key] = array_values(array_filter($this->clicks[$key], static fn(float $time): bool => $now - $time <= 1.0));

        if (count($this->clicks[$key]) > self::CPS_MAX) {
            $this->flag($player, 'CPS', count($this->clicks[$key]) . ' cps');
        }
    }

    private function checkReach(Player $damager, Entity $victim): void  {
        $key = strtolower($damager->getName());
        $distance = $damager->getEyePos()->distance($victim->getPosition()->add(0, $victim->getSize()->getHeight() / 2, 0));
        if ($distance <= self::REACH_MAX) {
            $this->reachViolations[$key] = 0;
            return;
        }

        $this->reachViolations[$key] = ($this->reachViolations[$key] ?? 0) + 1;
        if ($this->reachViolations[$key] >= self::REACH_BUFFER_LIMIT) {
            $this->reachViolations[$key] = 0;
            $this->flag($damager, 'Reach', 'portée ' . round($distance, 2));
        }
    }

    private function touchesFallableBlock(Player $player, AxisAlignedBB $box): bool  {
        $world = $player->getWorld();
        $minX = (int) floor($box->minX + 0.00001);
        $maxX = (int) floor($box->maxX - 0.00001);
        $minY = (int) floor($box->minY + 0.00001);
        $maxY = (int) floor($box->maxY - 0.00001);
        $minZ = (int) floor($box->minZ + 0.00001);
        $maxZ = (int) floor($box->maxZ - 0.00001);

        for ($x = $minX; $x <= $maxX; ++$x) {
            for ($y = $minY; $y <= $maxY; ++$y) {
                for ($z = $minZ; $z <= $maxZ; ++$z) {
                    if ($world->getBlockAt($x, $y, $z) instanceof Fallable) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function inLiquid(Player $player): bool  {
        return $player->getWorld()->getBlock($player->getPosition()) instanceof Liquid
            || $player->getWorld()->getBlock($player->getPosition()->add(0, 1, 0)) instanceof Liquid;
    }

    private function onClimbable(Player $player): bool  {
        return $player->getWorld()->getBlock($player->getPosition()) instanceof Ladder;
    }

    private function onIce(Player $player): bool  {
        $pos = $player->getPosition();
        return str_contains(strtolower($player->getWorld()->getBlockAt($pos->getFloorX(), $pos->getFloorY() - 1, $pos->getFloorZ())->getName()), 'ice');
    }

    /** @return array{prevHoriz: float, speedBuf: float, air: int, tpExempt: int, kbExempt: int, prevDy: float, flyViol: float, phaseBuf: float} */
    private function emptyState(): array  {
        return ['prevHoriz' => 0.0, 'speedBuf' => 0.0, 'air' => 0, 'tpExempt' => 0, 'kbExempt' => 0, 'prevDy' => 0.0, 'flyViol' => 0.0, 'phaseBuf' => 0.0];
    }

    private function flag(Player $player, string $check, string $details): void  {
        if (!$player->isConnected()) {
            return;
        }

        $key = strtolower($player->getName());
        $now = time();
        $entry = $this->violations[$key][$check] ?? ['vl' => 0.0, 'last' => 0, 'lastAlert' => 0];
        if ($now - $entry['last'] > self::DECAY_SECONDS) {
            $entry['vl'] = 0.0;
        }

        $entry['vl'] += 1.0;
        $entry['last'] = $now;
        $kick = $entry['vl'] >= self::KICK_THRESHOLD;
        if ($kick || ($now - $entry['lastAlert']) >= self::ALERT_COOLDOWN) {
            $entry['lastAlert'] = $now;
            $this->notifyStaff($player, $check, $details, (int) $entry['vl'], $kick);
        }

        if ($kick) {
            $entry['vl'] = 0.0;
            Server::getInstance()->broadcastMessage(Manager::BRANDING()->format('{ac_prefix}{error}{player} a été kick par l\'anticheat ({text}{check}{error}).', [
                '{player}' => $player->getName(),
                '{check}' => $check,
            ]));
            $player->kick(Manager::BRANDING()->format('{error}{server_name}AC: comportement invalide ({check}).', ['{check}' => $check]));
        }

        $this->violations[$key][$check] = $entry;
    }

    private function clear(Player $player): void  {
        unset($this->violations[strtolower($player->getName())]);
    }

    private function notifyStaff(Player $player, string $check, string $details, int $vl, bool $kick): void  {
        $moderatorId = Manager::RANK()->rank(RankType::MODERATEUR)->getId();
        $message = Manager::BRANDING()->anticheatPrefix()
            . ($kick ? Manager::BRANDING()->format('{error}Kick {secondary}') : Manager::BRANDING()->format('{warning}Alerte {secondary}'))
            . $player->getName()
            . Manager::BRANDING()->format(' {dark}| {text}') . $check
            . Manager::BRANDING()->format(' {dark}| {primary}') . $vl . '/' . self::KICK_THRESHOLD
            . Manager::BRANDING()->format(' {dark}| {secondary}') . $details;

        foreach (Server::getInstance()->getOnlinePlayers() as $online) {
            if ($online->getServer()->isOp($online->getName()) || Manager::RANK()->getPlayerRank($online->getName())->getId() >= $moderatorId) {
                $online->sendMessage($message);
            }
        }
    }
}
