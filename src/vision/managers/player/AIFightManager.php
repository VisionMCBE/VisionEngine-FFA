<?php

declare(strict_types=1);

namespace vision\managers\player;

use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\animation\MagicHitAnimation;
use pocketmine\entity\projectile\EnderPearl as EnderPearlProjectile;
use pocketmine\entity\projectile\SplashPotion as SplashPotionProjectile;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\PotionType;
use pocketmine\item\enchantment\MeleeWeaponEnchantment;
use pocketmine\item\SplashPotion;
use pocketmine\item\EnderPearl as EnderPearlItem;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\world\sound\ThrowSound;
use pocketmine\world\sound\EntityAttackSound;
use vision\entities\AIFightBot;
use vision\Main;
use vision\managers\Manager;

final class AIFightManager implements Listener {
    private const MAX_SESSIONS = 6;
    private const FIGHT_DURATION_TICKS = 20 * 60 * 10;
    private const BASE_COOLDOWN_SECONDS = 15 * 60;
    private const MIN_COOLDOWN_SECONDS = 5 * 60;

    private const PROFILES = [
        'easy' => ['reach' => 2.45, 'speed' => 0.34, 'attack_ticks' => 5, 'accuracy' => 62, 'heal' => 58, 'strafe' => 12, 'pearl' => 12, 'pearl_ticks' => 300],
        'medium' => ['reach' => 2.80, 'speed' => 0.44, 'attack_ticks' => 4, 'accuracy' => 78, 'heal' => 75, 'strafe' => 40, 'pearl' => 30, 'pearl_ticks' => 260],
        'hard' => ['reach' => 3.00, 'speed' => 0.50, 'attack_ticks' => 3, 'accuracy' => 90, 'heal' => 88, 'strafe' => 70, 'pearl' => 55, 'pearl_ticks' => 220],
        'hacker' => ['reach' => 3.25, 'speed' => 0.55, 'attack_ticks' => 2, 'accuracy' => 97, 'heal' => 97, 'strafe' => 92, 'pearl' => 75, 'pearl_ticks' => 180],
    ];

    /** @var array<string, array{owner: Player, bot: AIFightBot, profile: array<string, int|float>, starts_at: int, ends_at: int, cooldown_seconds: int, last_attack: int, last_heal: int, last_pearl: int, strafe: int}> */
    private array $sessions = [];
    /** @var array<string, int> */
    private array $cooldowns = [];
    private bool $loopRunning = false;

    public function isFighting(Player|string $player): bool {
        $name = $player instanceof Player ? $player->getName() : $player;
        return isset($this->sessions[strtolower($name)]);
    }

    public function start(Player $player, string $difficulty): void {
        $key = strtolower($player->getName());
        if (Manager::FFA()->isInside($player->getPosition())) {
            $player->sendMessage(Manager::BRANDING()->format('{prefix}{error}Vous devez quitter la zone KitFFA pour combattre une IA.'));
            return;
        }
        if (Manager::COMBAT()->isInCombat($player)) {
            $player->sendMessage(Manager::BRANDING()->format('{prefix}{error}Vous ne pouvez pas lancer un combat contre une IA pendant un combat.'));
            return;
        }
        if (isset($this->sessions[$key])) {
            $player->sendMessage(Manager::BRANDING()->format('{prefix}{error}Vous combattez déjà une IA.'));
            return;
        }
        $remaining = max(0, ($this->cooldowns[$key] ?? 0) - time());
        if ($remaining > 0) {
            $minutes = intdiv($remaining, 60);
            $seconds = $remaining % 60;
            $player->sendMessage(Manager::BRANDING()->format('{prefix}{error}Vous pourrez lancer un nouveau combat contre une IA dans {primary}')
                . ($minutes > 0 ? $minutes . ' min ' : '') . $seconds . ' s' . Manager::BRANDING()->format('{error}.'));
            return;
        }
        unset($this->cooldowns[$key]);
        if (count($this->sessions) >= self::MAX_SESSIONS) {
            $player->sendMessage(Manager::BRANDING()->format('{prefix}{error}Six combats contre l’IA sont déjà actifs. Réessayez dans un instant.'));
            return;
        }

        Manager::FFA()->giveKit($player);
        $direction = $player->getDirectionVector();
        $position = $player->getPosition();
        $location = new Location(
            $position->getX() + ($direction->x * 4.0),
            $position->getY(),
            $position->getZ() + ($direction->z * 4.0),
            $player->getWorld(),
            $player->getLocation()->yaw + 180.0,
            0.0
        );
        $bot = new AIFightBot($location, $player->getSkin(), $player->getName(), $difficulty);
        $bot->setMaxHealth(20);
        $bot->setHealth(20.0);
        $bot->setHasGravity(false);
        foreach ($player->getInventory()->getContents() as $slot => $item) {
            $bot->getInventory()->setItem($slot, clone $item);
        }
        foreach ($player->getArmorInventory()->getContents() as $slot => $item) {
            $bot->getArmorInventory()->setItem($slot, clone $item);
        }
        $bot->getInventory()->setHeldItemIndex(0);
        $bot->lookAt($player->getEyePos());
        $bot->spawnTo($player);

        $tick = Server::getInstance()->getTick();
        $rankId = Manager::RANK()->getPlayerRank($player->getName())->getId();
        $cooldown = max(self::MIN_COOLDOWN_SECONDS, self::BASE_COOLDOWN_SECONDS - ($rankId * 60));
        $this->sessions[$key] = [
            'owner' => $player,
            'bot' => $bot,
            'profile' => self::PROFILES[$difficulty],
            'starts_at' => $tick + 60,
            'ends_at' => $tick + 60 + self::FIGHT_DURATION_TICKS,
            'cooldown_seconds' => $cooldown,
            'last_attack' => $tick,
            'last_heal' => $tick,
            'last_pearl' => $tick,
            'strafe' => random_int(0, 1) === 0 ? -1 : 1,
        ];
        $player->setNoClientPredictions(true);
        Manager::COMBAT()->refreshAllVisibility();
        $player->sendTitle('§9§l3', '§7Préparez-vous...', 0, 20, 0);
        Main::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(static function () use ($player): void {
            if ($player->isConnected()) $player->sendTitle('§9§l2', '§7Préparez-vous...', 0, 20, 0);
        }), 20);
        Main::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(static function () use ($player): void {
            if ($player->isConnected()) $player->sendTitle('§9§l1', '§7Préparez-vous...', 0, 20, 0);
        }), 40);
        Main::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(static function () use ($player): void {
            if ($player->isConnected()) {
                $player->setNoClientPredictions(false);
            }
        }), 60);
        $this->startLoop();
    }

    private function startLoop(): void {
        if ($this->loopRunning) return;
        $this->loopRunning = true;
        Main::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(fn() => $this->tick()), 1);
    }

    private function tick(): void {
        $tick = Server::getInstance()->getTick();
        foreach ($this->sessions as $key => &$session) {
            $owner = $session['owner'];
            $bot = $session['bot'];
            if (!$owner->isConnected() || $bot->isFlaggedForDespawn() || $owner->getWorld() !== $bot->getWorld()) {
                $this->remove($key);
                continue;
            }
            if ($tick < $session['starts_at']) {
                $bot->lookAt($owner->getEyePos());
                continue;
            }
            if ($tick >= $session['ends_at']) {
                $this->finish($key, null);
                continue;
            }
            if ($tick === $session['starts_at']) {
                $bot->setHasGravity(true);
                $owner->sendTitle('§a§lGO', '§7Battez l’IA.', 0, 20, 0);
            }

            $ownerPosition = $owner->getPosition();
            $botPosition = $bot->getPosition();
            $delta = new Vector3(
                $ownerPosition->getX() - $botPosition->getX(),
                $ownerPosition->getY() - $botPosition->getY(),
                $ownerPosition->getZ() - $botPosition->getZ()
            );
            $horizontalSquared = ($delta->x * $delta->x) + ($delta->z * $delta->z);
            if ($horizontalSquared > 1600.0) {
                $this->finish($key, false);
                continue;
            }
            $distance = sqrt($horizontalSquared);
            $profile = $session['profile'];
            $bot->lookAt($owner->getEyePos());

            if ($distance > 8.0 && $tick - $session['last_pearl'] >= (int) $profile['pearl_ticks']
                && random_int(1, 100) <= (int) $profile['pearl'] && $this->usePearl($key, $owner, $bot)) {
                $session['last_pearl'] = $tick;
                $session['last_attack'] = $tick + 7;
                continue;
            }

            if ($distance > (float) $profile['reach'] * 0.85) {
                $length = max(0.001, $distance);
                $forwardX = $delta->x / $length;
                $forwardZ = $delta->z / $length;
                $strafeStrength = $distance < 6.0 ? 0.22 * $session['strafe'] : 0.0;
                $speed = (float) $profile['speed'];
                $motion = $bot->getMotion();
                $bot->setMotion(new Vector3(
                    (($forwardX + (-$forwardZ * $strafeStrength)) * $speed),
                    $motion->y,
                    (($forwardZ + ($forwardX * $strafeStrength)) * $speed)
                ));
            }

            if ($bot->getHealth() <= 16.0 && $tick - $session['last_heal'] >= 20
                && random_int(1, 100) <= (int) $profile['heal'] && $this->consumePotion($key, $owner, $bot)) {
                $session['last_heal'] = $tick;
                $session['last_attack'] = $tick + 7;
            }

            if ($distance <= (float) $profile['reach'] && abs($delta->y) <= 2.4
                && $tick - $session['last_attack'] >= (int) $profile['attack_ticks']) {
                $session['last_attack'] = $tick;
                if (random_int(1, 100) <= (int) $profile['accuracy']) {
                    $held = $bot->getInventory()->getItemInHand();
                    $damage = (float) $held->getAttackPoints();
                    $damageEvent = new EntityDamageByEntityEvent($bot, $owner, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage);
                    $damageEvent->setKnockBack(Manager::KNOCKBACK()->horizontal());
                    $damageEvent->setVerticalKnockBackLimit(Manager::KNOCKBACK()->vertical());
                    $damageEvent->setAttackCooldown((int) $profile['attack_ticks']);
                    $enchantmentDamage = 0.0;
                    $meleeEnchantments = [];
                    foreach ($held->getEnchantments() as $enchantment) {
                        $type = $enchantment->getType();
                        if ($type instanceof MeleeWeaponEnchantment && $type->isApplicableTo($owner)) {
                            $enchantmentDamage += $type->getDamageBonus($enchantment->getLevel());
                            $meleeEnchantments[] = $enchantment;
                        }
                    }
                    $damageEvent->setModifier($enchantmentDamage, EntityDamageEvent::MODIFIER_WEAPON_ENCHANTMENTS);
                    $bot->broadcastAnimation(new ArmSwingAnimation($bot), [$owner]);
                    $owner->attack($damageEvent);
                    if (!$damageEvent->isCancelled() && $damageEvent->getFinalDamage() > 0.0) {
                        $bot->getWorld()->addSound($owner->getPosition(), new EntityAttackSound(), [$owner]);
                        if ($enchantmentDamage > 0.0) {
                            $owner->broadcastAnimation(new MagicHitAnimation($owner), [$owner]);
                        }
                        foreach ($meleeEnchantments as $enchantment) {
                            $type = $enchantment->getType();
                            if ($type instanceof MeleeWeaponEnchantment) {
                                $type->onPostAttack($bot, $owner, $enchantment->getLevel());
                            }
                        }
                    }
                    if (random_int(1, 100) <= (int) $profile['strafe']) $session['strafe'] *= -1;
                }
            }
        }
        unset($session);

        if ($this->sessions === []) {
            $this->loopRunning = false;
            return;
        }
        Main::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(fn() => $this->tick()), 1);
    }

    /** @priority HIGHEST */
    public function onDamage(EntityDamageByEntityEvent $event): void {
        $victim = $event->getEntity();
        $damager = $event->getDamager();
        $bot = $victim instanceof AIFightBot ? $victim : ($damager instanceof AIFightBot ? $damager : null);
        if ($bot === null) return;
        $key = strtolower($bot->getOwnerName());
        $session = $this->sessions[$key] ?? null;
        if ($session === null) {
            $event->cancel();
            return;
        }
        $owner = $session['owner'];
        if (($victim !== $bot && $victim !== $owner) || ($damager !== $bot && $damager !== $owner)) {
            $event->cancel();
            return;
        }
        if (Server::getInstance()->getTick() < $session['starts_at']) {
            $event->cancel();
            return;
        }
        if ($victim === $bot) {
            if ($event->getFinalDamage() >= $bot->getHealth()) {
                $event->cancel();
                $this->finish($key, true);
                return;
            }
            $bot->updateNameTag(max(0.0, $bot->getHealth() - $event->getFinalDamage()));
        } elseif ($victim === $owner && $event->getFinalDamage() >= $owner->getHealth()) {
            $event->cancel();
            $this->finish($key, false);
        }
    }

    public function onEnvironmentalDamage(EntityDamageEvent $event): void {
        if ($event instanceof EntityDamageByEntityEvent) {
            return;
        }
        if ($event->getEntity() instanceof AIFightBot && $event->getCause() === EntityDamageEvent::CAUSE_FALL) {
            $event->cancel();
        }
    }

    public function onQuit(PlayerQuitEvent $event): void {
        $key = strtolower($event->getPlayer()->getName());
        if (isset($this->sessions[$key])) $this->remove($key);
    }

    private function finish(string $key, ?bool $won): void {
        $session = $this->sessions[$key] ?? null;
        if ($session === null) return;
        $player = $session['owner'];
        $this->remove($key);
        if (!$player->isConnected()) return;
        $player->setHealth($player->getMaxHealth());
        $spawn = Manager::FFA()->spawnPosition();
        if ($spawn !== null) $player->teleport($spawn);
        Manager::FFA()->clearCombatEffects($player);
        Manager::COMBAT()->setLobbyHidden($player, false);
        Manager::COMBAT()->setInside($player, true);
        Manager::FFA()->giveLobbyItems($player);
        Manager::NAMETAG()->update($player);
        Manager::COMBAT()->refreshAllVisibility();
        $title = $won === null ? '§e§lTEMPS ÉCOULÉ' : ($won ? '§a§lVICTOIRE' : '§c§lDÉFAITE');
        $player->sendTitle($title, '§7Combat amical terminé.', 10, 40, 10);
    }

    private function remove(string $key): void {
        $session = $this->sessions[$key] ?? null;
        if ($session === null) return;
        $this->cooldowns[$key] = time() + $session['cooldown_seconds'];
        if ($session['owner']->isConnected()) {
            $session['owner']->setNoClientPredictions(false);
        }
        if (!$session['bot']->isFlaggedForDespawn()) $session['bot']->flagForDespawn();
        unset($this->sessions[$key]);
        Manager::COMBAT()->refreshAllVisibility();
    }

    private function consumePotion(string $sessionKey, Player $owner, AIFightBot $bot): bool {
        foreach ($bot->getInventory()->getContents() as $slot => $item) {
            if ($item instanceof SplashPotion && $item->getType() === PotionType::STRONG_HEALING()) {
                $displaySlot = $slot <= 8 ? $slot : 2;
                $displaced = clone $bot->getInventory()->getItem($displaySlot);
                $visualPotion = clone $item;
                $visualPotion->setCount(1);
                $bot->getInventory()->setItem($displaySlot, $visualPotion);
                $bot->getInventory()->setHeldItemIndex($displaySlot);
                $bot->broadcastAnimation(new ArmSwingAnimation($bot), [$owner]);
                $bot->getWorld()->addSound($bot->getPosition(), new ThrowSound(), [$owner]);
                $location = $bot->getLocation();
                $projectile = new SplashPotionProjectile(
                    Location::fromObject($bot->getEyePos(), $bot->getWorld(), $location->yaw, $location->pitch),
                    $bot,
                    $item->getType()
                );
                $projectile->setMotion(new Vector3(
                    random_int(-5, 5) / 100,
                    -0.62,
                    random_int(-5, 5) / 100
                ));
                $projectile->spawnTo($owner);
                $item->pop();
                if ($displaySlot === $slot) {
                    $displaced = clone $item;
                } else {
                    $bot->getInventory()->setItem($slot, $item);
                }
                Main::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($sessionKey, $bot, $displaySlot, $displaced): void {
                    if (isset($this->sessions[$sessionKey]) && !$bot->isFlaggedForDespawn()) {
                        $bot->getInventory()->setItem($displaySlot, $displaced);
                        $bot->getInventory()->setHeldItemIndex(0);
                    }
                }), 7);
                return true;
            }
        }
        return false;
    }

    private function usePearl(string $sessionKey, Player $owner, AIFightBot $bot): bool {
        foreach ($bot->getInventory()->getContents() as $slot => $item) {
            if (!$item instanceof EnderPearlItem || $item->getCount() <= 0) {
                continue;
            }
            $displaySlot = $slot <= 8 ? $slot : 1;
            $displaced = clone $bot->getInventory()->getItem($displaySlot);
            $visualPearl = clone $item;
            $visualPearl->setCount(1);
            $bot->getInventory()->setItem($displaySlot, $visualPearl);
            $bot->getInventory()->setHeldItemIndex($displaySlot);
            $bot->lookAt($owner->getEyePos());
            $bot->broadcastAnimation(new ArmSwingAnimation($bot), [$owner]);

            $location = $bot->getLocation();
            $projectile = new EnderPearlProjectile(
                Location::fromObject($bot->getEyePos(), $bot->getWorld(), $location->yaw, $location->pitch),
                $bot
            );
            $direction = $owner->getEyePos()->subtractVector($bot->getEyePos());
            if ($direction->lengthSquared() <= 0.001) {
                return false;
            }
            $projectile->setMotion($direction->normalize()->multiply(1.5)->add(0.0, 0.12, 0.0));
            $projectile->spawnTo($owner);
            $bot->getWorld()->addSound($bot->getPosition(), new ThrowSound(), [$owner]);

            $item->pop();
            if ($displaySlot === $slot) {
                $displaced = clone $item;
            } else {
                $bot->getInventory()->setItem($slot, $item);
            }
            Main::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($sessionKey, $bot, $displaySlot, $displaced): void {
                if (isset($this->sessions[$sessionKey]) && !$bot->isFlaggedForDespawn()) {
                    $bot->getInventory()->setItem($displaySlot, $displaced);
                    $bot->getInventory()->setHeldItemIndex(0);
                }
            }), 7);
            return true;
        }
        return false;
    }
}
