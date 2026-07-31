<?php

declare(strict_types=1);

namespace vision\listeners;

use pocketmine\entity\projectile\EnderPearl;
use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;
use pocketmine\network\mcpe\protocol\types\BoolGameRule;
use pocketmine\Server;
use vision\form\SettingsForm;
use vision\managers\Manager;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
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
use vision\Main;

use vision\services\chat\CustomChatFormatter;
use function count;
use function glob;

final class PlayerListeners implements Listener {
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
        $rank = Manager::RANK()->getPlayerRank($player->getName());
        $brand = Manager::BRANDING();
        $format = $brand->format('{dark}[') . $rank->getColor() . $rank->getName() . $brand->format('{dark}] ')
            . $brand->format('{secondary}') . $player->getName()
            . $brand->format(' {dark}» {text}');

        $event->setFormatter(new CustomChatFormatter($format));
    }

    public function handlePlayerDeathEvent(PlayerDeathEvent $event): void  {
        $victim = $event->getPlayer();
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
        if ($item instanceof SplashPotionItem && Manager::SETTINGS()->hasGuidedPotions($player->getName())) {
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

}
