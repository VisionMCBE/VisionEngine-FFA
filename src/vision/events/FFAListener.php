<?php

declare(strict_types=1);

namespace vision\events;

use vision\managers\Manager;

use pocketmine\block\VanillaBlocks;
use pocketmine\block\utils\DyeColor;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\GoldenApple;
use pocketmine\item\GoldenAppleEnchanted;
use pocketmine\item\PotionType;
use pocketmine\item\SplashPotion as SplashPotionItem;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\player\chat\ChatFormatter;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\particle\PotionSplashParticle;
use pocketmine\world\Position;
use pocketmine\world\sound\PotionSplashSound;
use pocketmine\world\sound\ThrowSound;
use vision\Main;
use vision\commands\XyzCommand;

use function count;
use function glob;

final class FFAListener implements Listener
{
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

    /** @var array<string, bool> */
    private array $knownPlayers = [];

    public function __construct(private readonly Main $plugin) {}

    public function onBlockBreak(BlockBreakEvent $event): void
    {
        if ($event->getPlayer()->getGamemode() !== GameMode::CREATIVE) {
            $event->cancel();
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event): void
    {
        if ($event->getPlayer()->getGamemode() !== GameMode::CREATIVE) {
            $event->cancel();
        }
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();
        $event->setJoinMessage('');
        $key = strtolower($player->getName());
        $isFirstJoin = !$player->hasPlayedBefore() && !isset($this->knownPlayers[$key]);
        $this->knownPlayers[$key] = true;
        $this->names[$key] = $player->getName();
        $this->combatUntil[$key] = 0;
        $this->hideLobbyPlayers[$key] = false;
        $this->insideFfa[$key] = Manager::FFA()->isInside($player->getPosition());
        XyzCommand::applyCoordinates($player, (bool) $this->plugin->getConfig()->getNested('coordinates.enabled', false));
        if ($this->insideFfa[$key]) {
            Manager::FFA()->giveLobbyItems($player);
        }
        $rank = Manager::RANK()->getPlayerRank($player->getName());
        $player->setNameTag($rank->getColor() . $rank->getName() . Manager::BRANDING()->format(' {secondary}') . $player->getName());
        $this->plugin->getServer()->broadcastTip(Manager::BRANDING()->format('{success}+ ') . $player->getName() . Manager::BRANDING()->format(' {success}+'));

        if ($isFirstJoin) {
            $number = count(glob($this->plugin->getServer()->getDataPath() . 'players' . DIRECTORY_SEPARATOR . '*') ?: []) + 1;
            $this->plugin->getServer()->broadcastMessage(Manager::BRANDING()->format('{prefix}{secondary}Bienvenue à {primary}') . $player->getName() . Manager::BRANDING()->format(' {secondary}sur {primary}{server_name}{secondary} ! Souhaitez-lui la bienvenue avec {primary}/bvn {dark}(#') . $number . ')');
        }
        $this->refreshAllVisibility();
    }

    public function onQuit(PlayerQuitEvent $event): void
    {
        $event->setQuitMessage('');
        Manager::SCOREBOARD()->remove($event->getPlayer());
        $key = strtolower($event->getPlayer()->getName());
        unset($this->combatUntil[$key], $this->insideFfa[$key], $this->hideLobbyPlayers[$key], $this->sentBarrier[$key], $this->combatOpponent[$key], $this->names[$key]);
        foreach ($this->combatOpponent as $playerKey => $opponentKey) {
            if ($opponentKey === $key) {
                unset($this->combatOpponent[$playerKey]);
            }
        }
        $this->plugin->getServer()->broadcastTip(Manager::BRANDING()->format('{error}- ') . $event->getPlayer()->getName() . Manager::BRANDING()->format(' {error}-'));
        $this->refreshAllVisibility();
    }

    public function onDamage(EntityDamageByEntityEvent $event): void
    {
        Manager::KNOCKBACK()->apply($event);

        $victim = $event->getEntity();
        $damager = $event->getDamager();
        if (!$victim instanceof Player || !$damager instanceof Player || $event->isCancelled()) {
            return;
        }

        if (Manager::FFA()->isInside($victim->getPosition()) || Manager::FFA()->isInside($damager->getPosition())) {
            $event->cancel();
            return;
        }

        $victimKey = strtolower($victim->getName());
        $damagerKey = strtolower($damager->getName());
        $victimOpponent = $this->activeOpponent($victim);
        $damagerOpponent = $this->activeOpponent($damager);
        if (($victimOpponent !== null && $victimOpponent !== $damagerKey) || ($damagerOpponent !== null && $damagerOpponent !== $victimKey)) {
            $event->cancel();
            $damager->sendMessage(Manager::BRANDING()->format('{prefix}{error}Ce joueur est déjà en combat avec quelqu’un d’autre.'));
            return;
        }

        $until = time() + self::COMBAT_SECONDS;
        $this->combatUntil[$victimKey] = $until;
        $this->combatUntil[$damagerKey] = $until;
        $this->combatOpponent[$victimKey] = $damagerKey;
        $this->combatOpponent[$damagerKey] = $victimKey;
        Manager::COOLDOWN()->add($victim->getName(), 'combat', self::COMBAT_SECONDS);
        Manager::COOLDOWN()->add($damager->getName(), 'combat', self::COMBAT_SECONDS);
        $this->refreshAllVisibility();
    }

    public function onChat(PlayerChatEvent $event): void
    {
        $player = $event->getPlayer();
        $rank = Manager::RANK()->getPlayerRank($player->getName());
        $brand = Manager::BRANDING();
        $format = $brand->format('{dark}[', []) . $rank->getColor() . $rank->getName() . $brand->format('{dark}] ')
            . $brand->format('{secondary}') . $player->getName()
            . $brand->format(' {dark}» {text}');

        $event->setFormatter(new class($format) implements ChatFormatter {
            public function __construct(private readonly string $prefix) {}

            public function format(string $username, string $message): string
            {
                return $this->prefix . TextFormat::clean($message);
            }
        });
    }

    public function onDeath(PlayerDeathEvent $event): void
    {
        $victim = $event->getPlayer();
        $event->setDrops([]);
        $event->setXpDropAmount(0);
        $event->setDeathMessage('');
        Manager::STATS()->addDeath($victim->getName());
        $cause = $victim->getLastDamageCause();
        if ($cause instanceof EntityDamageByEntityEvent && $cause->getDamager() instanceof Player) {
            $killer = $cause->getDamager();
            if ($killer !== $victim) {
                $message = $this->killMessage($killer, $victim);
                $this->endCombat($killer);
                $this->endCombat($victim);
                Manager::STATS()->addKill($killer->getName());
                $this->plugin->getServer()->broadcastMessage($message);
                Manager::FFA()->giveKit($killer);
                $killer->sendTip(Manager::BRANDING()->format('{success}Kit refill.'));
            }
        }
    }

    public function onConsume(PlayerItemConsumeEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem();
        if (!$item instanceof GoldenApple && !$item instanceof GoldenAppleEnchanted) {
            return;
        }
        if (Manager::COOLDOWN()->has($player->getName(), 'gapple')) {
            $event->cancel();
            $player->sendTip(Manager::BRANDING()->format('{error}Pomme disponible dans ') . Manager::COOLDOWN()->remaining($player->getName(), 'gapple') . 's.');
            return;
        }
        Manager::COOLDOWN()->add($player->getName(), 'gapple', 15);
    }

    public function onUse(PlayerItemUseEvent $event): void
    {
        $item = $event->getItem();
        $player = $event->getPlayer();
        if ($item instanceof SplashPotionItem && Manager::SETTINGS()->hasGuidedPotions($player->getName())) {
            $event->cancel();
            $beforeUse = $player->getInventory()->getItemInHand();
            if (!$beforeUse->equalsExact($item)) {
                return;
            }
            $player->getWorld()->addSound($player->getPosition(), new ThrowSound());
            $player->getWorld()->addSound($player->getPosition(), new PotionSplashSound());
            $this->addPotionParticle($player);
            $this->applyInstantPotion($player, $item->getType());
            $player->getInventory()->setItemInHand($item->pop()->isNull() ? VanillaItems::AIR() : $item);
            return;
        }

        if (!Manager::FFA()->isLobbyItem($item)) {
            return;
        }
        $event->cancel();
        $action = Manager::FFA()->lobbyAction($item);
        if ($action === 'settings') {
            (new \vision\commands\SettingsCommand($this->plugin))->open($event->getPlayer());
            return;
        }
        if ($action === 'players_visibility' && Manager::FFA()->isInside($player->getPosition())) {
            $key = strtolower($player->getName());
            $hidden = !($this->hideLobbyPlayers[$key] ?? false);
            $this->hideLobbyPlayers[$key] = $hidden;
            Manager::FFA()->updateVisibilityItem($player, $hidden);
            $this->refreshVisibility($player);
            $player->sendTip($hidden ? '§cJoueurs cachés.' : '§aJoueurs affichés.');
        }
    }

    public function onDrop(PlayerDropItemEvent $event): void
    {
        $event->cancel();
    }

    public function onInventoryTransaction(InventoryTransactionEvent $event): void
    {
        foreach ($event->getTransaction()->getActions() as $action) {
            if (Manager::FFA()->isLobbyItem($action->getSourceItem()) || Manager::FFA()->isLobbyItem($action->getTargetItem())) {
                $event->cancel();
                return;
            }
        }
    }

    public function onMove(PlayerMoveEvent $event): void
    {
        $player = $event->getPlayer();
        $key = strtolower($player->getName());
        $fromInside = Manager::FFA()->isInside($event->getFrom());
        $toInside = Manager::FFA()->isInside($event->getTo());

        if (!$fromInside && $toInside && $this->isInCombat($player)) {
            $event->cancel();
            $player->sendTip(Manager::BRANDING()->format('{error}Vous ne pouvez pas rentrer en KitFFA en combat.'));
            $this->sendBarrierToward($player, $event->getTo());
            return;
        }

        if ($toInside && !Manager::FFA()->hasLobbyItems($player)) {
            $player->getEffects()->clear();
            Manager::FFA()->giveLobbyItems($player, $this->hideLobbyPlayers[$key] ?? false);
        }

        if (!$toInside && $this->isInCombat($player)) {
            $this->sendCombatWallTowardBoth($player, $event->getTo());
        }

        if (($this->insideFfa[$key] ?? false) && !$toInside) {
            $this->hideLobbyPlayers[$key] = false;
            Manager::FFA()->giveKit($player);
            $player->sendMessage(Manager::BRANDING()->format('{prefix}{secondary}Kit FFA équipé.'));
        } elseif (!($this->insideFfa[$key] ?? false) && $toInside) {
            $this->hideLobbyPlayers[$key] = false;
            $player->getEffects()->clear();
            Manager::FFA()->giveLobbyItems($player);
        }
        $this->insideFfa[$key] = $toInside;

        if (!$this->isInCombat($player)) {
            unset($this->combatOpponent[$key]);
            $this->clearBarrier($player);
            $this->refreshVisibility($player);
        }
    }

    public function onPotionLaunch(ProjectileLaunchEvent $event): void
    {
    }

    public function onProjectileLaunch(ProjectileLaunchEvent $event): void
    {
        $projectile = $event->getEntity();
        $owner = $projectile->getOwningEntity();
        if (!$owner instanceof Player || !$projectile instanceof \pocketmine\entity\projectile\EnderPearl) {
            return;
        }
        if (Manager::COOLDOWN()->has($owner->getName(), 'pearl')) {
            $event->cancel();
            $owner->sendMessage(Manager::BRANDING()->format('{prefix}{error}Enderpearl disponible dans ') . Manager::COOLDOWN()->remaining($owner->getName(), 'pearl') . 's.');
            return;
        }
        Manager::COOLDOWN()->add($owner->getName(), 'pearl', 15);
    }

    private function isInCombat(Player $player): bool
    {
        return ($this->combatUntil[strtolower($player->getName())] ?? 0) > time();
    }

    private function endCombat(Player $player): void
    {
        $key = strtolower($player->getName());
        $opponent = $this->combatOpponent[$key] ?? null;
        $this->combatUntil[$key] = 0;
        unset($this->combatOpponent[$key]);
        Manager::COOLDOWN()->remove($player->getName(), 'combat');
        $this->clearBarrier($player);

        if ($opponent !== null && (($this->combatOpponent[$opponent] ?? null) === $key)) {
            unset($this->combatOpponent[$opponent]);
            $this->combatUntil[$opponent] = 0;
            $opponentPlayer = $this->plugin->getServer()->getPlayerExact($this->names[$opponent] ?? $opponent);
            if ($opponentPlayer instanceof Player) {
                Manager::COOLDOWN()->remove($opponentPlayer->getName(), 'combat');
                $this->clearBarrier($opponentPlayer);
            }
        }

        $this->refreshAllVisibility();
    }

    private function activeOpponent(Player $player): ?string
    {
        if (!$this->isInCombat($player)) {
            return null;
        }
        $opponent = $this->combatOpponent[strtolower($player->getName())] ?? null;
        if ($opponent === null) {
            return null;
        }
        $target = $this->plugin->getServer()->getPlayerExact($this->names[$opponent] ?? $opponent);
        return $target instanceof Player && $this->isInCombat($target) ? strtolower($target->getName()) : null;
    }

    private function refreshAllVisibility(): void
    {
        foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
            $this->refreshVisibility($player);
        }
    }

    private function refreshVisibility(Player $viewer): void
    {
        $viewerKey = strtolower($viewer->getName());
        $hideLobbyPlayers = ($this->insideFfa[$viewerKey] ?? false) && ($this->hideLobbyPlayers[$viewerKey] ?? false);
        $enabled = Manager::SETTINGS()->hasCombatVisibility($viewer->getName());
        $opponentKey = $enabled ? $this->activeOpponent($viewer) : null;

        foreach ($this->plugin->getServer()->getOnlinePlayers() as $target) {
            if ($target === $viewer) {
                continue;
            }
            if (!$hideLobbyPlayers && ($opponentKey === null || strtolower($target->getName()) === $opponentKey)) {
                $viewer->showPlayer($target);
            } else {
                $viewer->hidePlayer($target);
            }
        }
    }

    private function nearestEnemy(Player $player, float $range): ?Player
    {
        $nearest = null;
        $best = $range * $range;
        foreach ($player->getWorld()->getPlayers() as $candidate) {
            if ($candidate === $player || !$candidate->isAlive()) {
                continue;
            }
            $distance = $candidate->getPosition()->distanceSquared($player->getPosition());
            if ($distance < $best) {
                $best = $distance;
                $nearest = $candidate;
            }
        }
        return $nearest;
    }

    private function applyInstantPotion(Player $player, PotionType $type): void
    {
        if ($type === PotionType::HEALING()) {
            $player->setHealth(min($player->getMaxHealth(), $player->getHealth() + 4.0));
        } elseif ($type === PotionType::STRONG_HEALING()) {
            $player->setHealth(min($player->getMaxHealth(), $player->getHealth() + 8.0));
        }
    }

    private function addPotionParticle(Player $player): void
    {
        $color = Manager::SETTINGS()->getPotionParticleColor($player->getName());
        $particle = $color === null
            ? new PotionSplashParticle(PotionSplashParticle::DEFAULT_COLOR())
            : new PotionSplashParticle($color);
        $player->getWorld()->addParticle($player->getPosition(), $particle);
    }

    private function killMessage(Player $killer, Player $victim): string
    {
        $potion = VanillaItems::SPLASH_POTION()->setType(PotionType::STRONG_HEALING());
        return '§9[Vision] §f' . $killer->getName() . ' §8[' . $this->countItems($killer, $potion) . '] §7a désintégré §f'
            . $victim->getName() . ' §8[' . $this->countItems($victim, $potion) . ']§7.';
    }

    private function countItems(Player $player, \pocketmine\item\Item $needle): int
    {
        $count = 0;
        foreach ($player->getInventory()->getContents() as $item) {
            if ($item->equals($needle, false, false)) {
                $count += $item->getCount();
            }
        }
        return $count;
    }

    private function sendBarrierToward(Player $player, Position $pos): void
    {
        $this->sendCombatWallTowardBoth($player, $pos);
    }

    private function sendCombatWallTowardBoth(Player $player, Position $pos): void
    {
        $this->sendCombatWallToward($player, $pos);
        $opponentKey = $this->activeOpponent($player);
        if ($opponentKey === null) {
            return;
        }
        $opponent = $this->plugin->getServer()->getPlayerExact($this->names[$opponentKey] ?? $opponentKey);
        if ($opponent instanceof Player) {
            $this->sendCombatWallToward($opponent, $pos);
        }
    }

    private function sendCombatWallToward(Player $player, Position $pos): void
    {
        $bounds = Manager::FFA()->bounds();
        if ($bounds === null || $pos->getWorld()->getFolderName() !== $bounds['world']) {
            return;
        }

        $x = $pos->getFloorX();
        $z = $pos->getFloorZ();
        $d = self::DETECT_DISTANCE;
        if ($x + $d >= $bounds['minX'] && $x <= $bounds['minX'] && $z >= $bounds['minZ'] && $z <= $bounds['maxZ']) {
            $this->sendWall($player, 'east', $bounds, $pos);
        } elseif ($x - $d <= $bounds['maxX'] && $x >= $bounds['maxX'] && $z >= $bounds['minZ'] && $z <= $bounds['maxZ']) {
            $this->sendWall($player, 'west', $bounds, $pos);
        } elseif ($z + $d >= $bounds['minZ'] && $z <= $bounds['minZ'] && $x >= $bounds['minX'] && $x <= $bounds['maxX']) {
            $this->sendWall($player, 'south', $bounds, $pos);
        } elseif ($z - $d <= $bounds['maxZ'] && $z >= $bounds['maxZ'] && $x >= $bounds['minX'] && $x <= $bounds['maxX']) {
            $this->sendWall($player, 'north', $bounds, $pos);
        }
    }

    private function sendWall(Player $player, string $side, array $bounds, Position $pos): void
    {
        $key = strtolower($player->getName());
        $blockTranslator = TypeConverter::getInstance()->getBlockTranslator();
        $glassId = $blockTranslator->internalIdToNetworkId(VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::RED)->getStateId());
        $baseY = $pos->getFloorY() - 1;
        $positions = [];

        if ($side === 'east' || $side === 'west') {
            $wallX = $side === 'east' ? $bounds['minX'] - 1 : $bounds['maxX'] + 1;
            for ($z = max($bounds['minZ'], $pos->getFloorZ() - 8); $z <= min($bounds['maxZ'], $pos->getFloorZ() + 8); ++$z) {
                for ($y = $baseY; $y < $baseY + self::WALL_HEIGHT; ++$y) {
                    $positions[] = [$wallX, $y, $z];
                }
            }
        } else {
            $wallZ = $side === 'south' ? $bounds['minZ'] - 1 : $bounds['maxZ'] + 1;
            for ($x = max($bounds['minX'], $pos->getFloorX() - 8); $x <= min($bounds['maxX'], $pos->getFloorX() + 8); ++$x) {
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
            $packet = UpdateBlockPacket::create(new BlockPosition($x, $y, $z), $glassId, UpdateBlockPacket::FLAG_NETWORK, UpdateBlockPacket::DATA_LAYER_NORMAL);
            $player->getNetworkSession()->sendDataPacket($packet);
            $this->sentBarrier[$key][$posKey] = true;
        }
    }

    private function clearBarrier(Player $player): void
    {
        $key = strtolower($player->getName());
        $blocks = $this->sentBarrier[$key] ?? [];
        if ($blocks === []) {
            return;
        }
        $translator = TypeConverter::getInstance()->getBlockTranslator();
        foreach ($blocks as $posKey => $_) {
            [$x, $y, $z] = array_map('intval', explode(',', $posKey));
            $real = $player->getWorld()->getBlockAt($x, $y, $z);
            $packet = UpdateBlockPacket::create(new BlockPosition($x, $y, $z), $translator->internalIdToNetworkId($real->getStateId()), UpdateBlockPacket::FLAG_NETWORK, UpdateBlockPacket::DATA_LAYER_NORMAL);
            $player->getNetworkSession()->sendDataPacket($packet);
        }
        unset($this->sentBarrier[$key]);
    }
}
