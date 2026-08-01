<?php

declare(strict_types=1);

namespace vision\listeners;

use pocketmine\entity\projectile\EnderPearl;
use pocketmine\entity\projectile\SplashPotion as SplashPotionProjectile;
use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;
use pocketmine\network\mcpe\protocol\types\BoolGameRule;
use pocketmine\Server;
use vision\form\SettingsForm;
use vision\managers\Manager;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\item\SplashPotion as SplashPotionItem;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\world\Position;
use vision\Main;

use vision\services\chat\CustomChatFormatter;
use function count;
use function glob;

final class PlayerListeners implements Listener {
    /** @var array<string, int> */
    private array $blockedPearlTeleports = [];
    /** @var array<string, int> */
    private array $blockedExternalHealing = [];
    /** @var array<string, int> */
    private array $chatCooldowns = [];

    public function __construct(private readonly Main $plugin) {}

    public function handleBlockBreakEvent(BlockBreakEvent $event): void  {
        if ($event->getPlayer()->getGamemode() !== GameMode::CREATIVE) {
            $event->cancel();
        }
    }

    public function handleBlockPlaceEvent(BlockPlaceEvent $event): void  {
        if ($event->getPlayer()->getGamemode() !== GameMode::CREATIVE) {
            $event->cancel();
        }
    }

    public function handlePlayerJoinEvent(PlayerJoinEvent $event): void  {
        $player = $event->getPlayer();
        $event->setJoinMessage('');
        $isFirstJoin = !$player->hasPlayedBefore();
        $insideFfa = Manager::FFA()->isInside($player->getPosition());
        Manager::COMBAT()->register($player, $insideFfa);
        
        $packet = new GameRulesChangedPacket();
        $packet->gameRules = ['showcoordinates' => new BoolGameRule((bool) $this->plugin->getConfig()->getNested('coordinates.enabled', false), false)];
        $player->getNetworkSession()->sendDataPacket($packet);

        if ($insideFfa) {
            Manager::FFA()->giveLobbyItems($player);
        }
        
        Manager::NAMETAG()->update($player);
        Server::getInstance()->broadcastTip(Manager::BRANDING()->format('{success}+ ') . $player->getName() . Manager::BRANDING()->format(' {success}+'));

        if ($isFirstJoin) {
            $number = count(glob(Server::getInstance()->getDataPath() . 'players' . DIRECTORY_SEPARATOR . '*') ?: []) + 1;
            Server::getInstance()->broadcastMessage(Manager::BRANDING()->format('{prefix}{secondary}Bienvenue à {primary}') . $player->getName() . Manager::BRANDING()->format(' {secondary}sur {primary}{server_name}{secondary} ! Souhaitez-lui la bienvenue avec {primary}/bvn {dark}(#') . $number . ')');
        }
        Manager::COMBAT()->refreshAllVisibility();
    }

    public function handlePlayerQuitEvent(PlayerQuitEvent $event): void  {
        $key = strtolower($event->getPlayer()->getName());
        unset($this->blockedPearlTeleports[$key], $this->chatCooldowns[$key]);
        $player = $event->getPlayer();
        $event->setQuitMessage('');
        Manager::SCOREBOARD()->remove($player);
        Manager::NAMETAG()->remove($player);

        $opponent = Manager::COMBAT()->opponent($player);
        if ($opponent !== null) {
            Manager::MATCH()->resolve($opponent, $player, true);
        }

        Manager::COMBAT()->unregister($player);
        Server::getInstance()->broadcastTip(Manager::BRANDING()->format('{error}- ') . $player->getName() . Manager::BRANDING()->format(' {error}-'));
    }

    public function handleEntityDamageEvent(EntityDamageEvent $event): void {
        if ($event->getEntity() instanceof Player && $event->getCause() === EntityDamageEvent::CAUSE_FALL) {
            $event->cancel();
        }
    }

    /** @priority HIGHEST */
    public function handleEntityDamageByEntityEvent(EntityDamageByEntityEvent $event): void  {
        $victim = $event->getEntity();
        $damager = $event->getDamager();
        if (!$victim instanceof Player || !$damager instanceof Player) {
            return;
        }

        if ($this->isProtectedByKitFfa($victim) || $this->isProtectedByKitFfa($damager)) {
            $event->cancel();
            return;
        }

        if ($event->isCancelled()) {
            return;
        }
        Manager::KNOCKBACK()->apply($event);

        if (Manager::AIFIGHT()->isFighting($victim) || Manager::AIFIGHT()->isFighting($damager)) {
            $event->cancel();
            return;
        }

        $victimKey = strtolower($victim->getName());
        $damagerKey = strtolower($damager->getName());
        $victimOpponent = Manager::COMBAT()->activeOpponent($victim);
        $damagerOpponent = Manager::COMBAT()->activeOpponent($damager);
        if (($victimOpponent !== null && $victimOpponent !== $damagerKey) || ($damagerOpponent !== null && $damagerOpponent !== $victimKey)) {
            $event->cancel();
            $damager->sendMessage(Manager::BRANDING()->format('{prefix}{error}Ce joueur est déjà en combat avec quelqu’un d’autre.'));
            return;
        }

        Manager::COMBAT()->start($victim, $damager);
        $remainingHealth = $victim->getHealth() - $event->getFinalDamage();
        if ($remainingHealth > 0.0) {
            Manager::NAMETAG()->update($victim, $remainingHealth);
            return;
        }

        $event->cancel();
        $this->removeEnderPearls($victim);
        $victim->setHealth($victim->getMaxHealth());
        Manager::MATCH()->resolve($damager, $victim);
        $spawn = Manager::FFA()->spawnPosition();
        if ($spawn !== null) {
            $victim->teleport($spawn);
        }
        Manager::FFA()->clearCombatEffects($victim);
        Manager::COMBAT()->setLobbyHidden($victim, false);
        Manager::COMBAT()->setInside($victim, true);
        Manager::FFA()->giveLobbyItems($victim);
        Manager::COMBAT()->refreshAllVisibility();
        Manager::NAMETAG()->update($victim);
    }

    public function handlePlayerRespawnEvent(PlayerRespawnEvent $event): void {
        Manager::NAMETAG()->update($event->getPlayer(), $event->getPlayer()->getMaxHealth());
    }

    public function handlePlayerChatEvent(PlayerChatEvent $event): void  {
        $player = $event->getPlayer();
        $key = strtolower($player->getName());
        $tick = Server::getInstance()->getTick();
        $remaining = ($this->chatCooldowns[$key] ?? 0) - $tick;
        if ($remaining > 0) {
            $event->cancel();
            $player->sendMessage(Manager::BRANDING()->format('{prefix}{error}Veuillez patienter {primary}')
                . (int) ceil($remaining / 20) . Manager::BRANDING()->format(' {error}seconde(s) avant de renvoyer un message.'));
            return;
        }
        $this->chatCooldowns[$key] = $tick + 60;

        $rank = Manager::RANK()->getPlayerRank($player->getName());
        $brand = Manager::BRANDING();
        $format = $brand->format('{dark}[') . $rank->getColor() . $rank->getName() . $brand->format('{dark}] ')
            . $brand->format('{secondary}') . $player->getName()
            . $brand->format(' {dark}» {text}');

        $event->setFormatter(new CustomChatFormatter($format));
    }

    public function handlePlayerDeathEvent(PlayerDeathEvent $event): void  {
        $victim = $event->getPlayer();
        $this->removeEnderPearls($victim);
        $event->setDrops([]);
        $event->setXpDropAmount(0);
        $event->setDeathMessage('');
        $cause = $victim->getLastDamageCause();
        if ($cause instanceof EntityDamageByEntityEvent && $cause->getDamager() instanceof Player) {
            $killer = $cause->getDamager();
            if ($killer !== $victim) {
                Manager::MATCH()->resolve($killer, $victim);
                return;
            }
        }
        Manager::STATS()->addDeath($victim->getName());
    }

    public function handlePlayerItemUseEvent(PlayerItemUseEvent $event): void  {
        $item = $event->getItem();
        $player = $event->getPlayer();
        if ($item instanceof SplashPotionItem && Manager::SETTINGS()->hasGuidedPotions($player->getName())
            && !Manager::AIFIGHT()->isFighting($player)) {
            $event->cancel();
            $beforeUse = $player->getInventory()->getItemInHand();
            if (!$beforeUse->equalsExact($item)) {
                return;
            }
            Manager::POTION()->applyGuided($player, $item->getType());
            $player->getInventory()->setItemInHand($item->pop()->isNull() ? VanillaItems::AIR() : $item);
            return;
        }

        if (!Manager::FFA()->isLobbyItem($item)) {
            return;
        }
        $event->cancel();
        $action = Manager::FFA()->lobbyAction($item);

        if ($action === 'settings') {
            SettingsForm::open($event->getPlayer());
            return;
        }

        if ($action === 'league') {
            $player->sendMessage(Manager::ELO()->information($player->getName()));
            return;
        }

        if ($action === 'players_visibility' && Manager::FFA()->isInside($player->getPosition())) {
            $hidden = Manager::COMBAT()->toggleLobbyVisibility($player);
            Manager::FFA()->updateVisibilityItem($player, $hidden);
        }
    }

    public function handlePlayerDropItemEvent(PlayerDropItemEvent $event): void  {
        $event->cancel();
    }

    public function handleInventoryTransactionEvent(InventoryTransactionEvent $event): void  {
        foreach ($event->getTransaction()->getActions() as $action) {
            if (Manager::FFA()->isLobbyItem($action->getSourceItem()) || Manager::FFA()->isLobbyItem($action->getTargetItem())) {
                $event->cancel();
                return;
            }
        }
    }

    public function handlePlayerMoveEvent(PlayerMoveEvent $event): void  {
        $player = $event->getPlayer();
        $fromInside = Manager::FFA()->isInside($event->getFrom());
        $toInside = Manager::FFA()->isInside($event->getTo());

        if (!$fromInside && $toInside && Manager::AIFIGHT()->isFighting($player)) {
            $event->cancel();
            $player->sendTip(Manager::BRANDING()->format('{error}Terminez votre combat contre l\'IA avant de retourner dans la zone KitFFA.'));
            return;
        }

        if (!$fromInside && $toInside && Manager::COMBAT()->isInCombat($player)) {
            $event->cancel();
            $player->sendTip(Manager::BRANDING()->format('{error}Vous ne pouvez pas entrer dans la zone protégée pendant un combat.'));
            Manager::COMBAT()->sendWallTowardBoth($player, $event->getTo());
            return;
        }

        if ($toInside && !Manager::FFA()->hasLobbyItems($player)) {
            Manager::FFA()->clearCombatEffects($player);
            Manager::FFA()->giveLobbyItems($player, Manager::COMBAT()->isLobbyHidden($player));
        }

        if (!$toInside && Manager::COMBAT()->isInCombat($player)) {
            Manager::COMBAT()->sendWallTowardBoth($player, $event->getTo());
        }

        if (Manager::COMBAT()->wasInside($player) && !$toInside) {
            Manager::COMBAT()->setLobbyHidden($player, false);
            Manager::FFA()->giveKit($player);
            $player->sendMessage(Manager::BRANDING()->format('{prefix}{success}Votre kit FFA vous a été équipé.'));
        } elseif (!Manager::COMBAT()->wasInside($player) && $toInside) {
            Manager::COMBAT()->setLobbyHidden($player, false);
            Manager::FFA()->clearCombatEffects($player);
            Manager::FFA()->giveLobbyItems($player);
        }
        Manager::COMBAT()->setInside($player, $toInside);

        if (!Manager::COMBAT()->isInCombat($player)) {
            Manager::COMBAT()->clearWall($player);
            Manager::COMBAT()->refreshVisibility($player);
        }
    }

    public function handleProjectileLaunchEvent(ProjectileLaunchEvent $event): void  {
        $projectile = $event->getEntity();
        $owner = $projectile->getOwningEntity();
        if (!$owner instanceof Player || !$projectile instanceof EnderPearl) {
            return;
        }
        if (Manager::COOLDOWN()->has($owner->getName(), 'pearl')) {
            $event->cancel();
            $owner->sendMessage(Manager::BRANDING()->format('{prefix}{error}Vous pourrez réutiliser une perle de l’End dans {primary}') . Manager::COOLDOWN()->remaining($owner->getName(), 'pearl') . Manager::BRANDING()->format(' {error}secondes.'));
            return;
        }
        Manager::COOLDOWN()->add($owner->getName(), 'pearl', 15);
    }

    public function handleProjectileHitEvent(ProjectileHitEvent $event): void {
        $projectile = $event->getEntity();
        $owner = $projectile->getOwningEntity();
        if ($projectile instanceof SplashPotionProjectile && $owner instanceof Player
            && ($projectile->getPotionType() === \pocketmine\item\PotionType::HEALING()
                || $projectile->getPotionType() === \pocketmine\item\PotionType::STRONG_HEALING())) {
            $tick = Server::getInstance()->getTick();
            $ownerKey = strtolower($owner->getName());
            $area = $projectile->getBoundingBox()->expandedCopy(4.125, 2.125, 4.125);
            foreach ($projectile->getWorld()->getCollidingEntities($area, $projectile) as $entity) {
                if (!$entity instanceof Player || $entity === $owner) {
                    continue;
                }
                $targetKey = strtolower($entity->getName());
                $opponent = Manager::COMBAT()->activeOpponent($entity);
                $protected = Manager::COMBAT()->isInCombat($entity) || Manager::AIFIGHT()->isFighting($entity);
                if ($protected && $opponent !== $ownerKey) {
                    $this->blockedExternalHealing[$targetKey] = $tick;
                }
            }
        }
        if (!$projectile instanceof EnderPearl || !$owner instanceof Player
            || Manager::FFA()->isInside($owner->getPosition())) {
            return;
        }
        $hit = $event->getRayTraceResult()->getHitVector();
        $destination = new Position($hit->getX(), $hit->getY(), $hit->getZ(), $projectile->getWorld());
        if (!Manager::FFA()->isInside($destination)) {
            return;
        }
        $this->blockedPearlTeleports[strtolower($owner->getName())] = Server::getInstance()->getTick() + 1;
        $owner->sendMessage(Manager::BRANDING()->format('{prefix}{error}Vous ne pouvez pas utiliser une perle de l’End pour entrer dans la zone KitFFA.'));
    }

    public function handleEntityRegainHealthEvent(EntityRegainHealthEvent $event): void {
        $player = $event->getEntity();
        if (!$player instanceof Player) {
            return;
        }
        $key = strtolower($player->getName());
        if (($this->blockedExternalHealing[$key] ?? -1) !== Server::getInstance()->getTick()) {
            return;
        }
        unset($this->blockedExternalHealing[$key]);
        $event->cancel();
    }

    public function handleEntityTeleportEvent(EntityTeleportEvent $event): void {
        $player = $event->getEntity();
        if (!$player instanceof Player) {
            return;
        }
        $key = strtolower($player->getName());
        if (($this->blockedPearlTeleports[$key] ?? -1) < Server::getInstance()->getTick()
            || !Manager::FFA()->isInside($event->getTo())) {
            return;
        }
        unset($this->blockedPearlTeleports[$key]);
        $event->cancel();
    }

    private function isProtectedByKitFfa(Player $player): bool {
        return Manager::FFA()->isInside($player->getPosition()) || Manager::COMBAT()->wasInside($player);
    }

    private function removeEnderPearls(Player $player): void {
        unset($this->blockedPearlTeleports[strtolower($player->getName())]);
        foreach ($player->getWorld()->getEntities() as $entity) {
            if ($entity instanceof EnderPearl && $entity->getOwningEntity() === $player) {
                $entity->flagForDespawn();
            }
        }
    }

}
