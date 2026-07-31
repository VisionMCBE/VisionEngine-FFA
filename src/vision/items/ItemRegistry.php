<?php

declare(strict_types=1);

namespace vision\items;


use vision\managers\Manager;

use NayTools\NayTools;
use NayTools\behavior\item\BuiltItem;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\SplashPotion as SplashPotionEntity;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\inventory\CreativeCategory;
use pocketmine\item\Item;
use pocketmine\item\ItemUseResult;
use pocketmine\item\Potion;
use pocketmine\item\SplashPotion;
use pocketmine\item\ToolTier;
use pocketmine\player\Player;
use pocketmine\world\sound\ThrowSound;
final class ItemRegistry {
    /** @var array<string, float> */
    private static array $potionLauncherUses = [];

    private const POTION_LAUNCHER_DELAY = 0.6;

    public static function registerAll(): void  {
        self::registerSwords();
        self::registerArmors();
        self::registerPotionLauncher();
    }

    private static function registerPotionLauncher(): void  {
        $ability = static fn(Player $player, mixed ...$unused): ItemUseResult => self::launchPotion($player);

        NayTools::item('visionengine:potion_launcher', 'item.visionengine:potion_launcher.name')
            ->texture('potion_launcher')
            ->maxStackSize(1)
            ->handEquipped()
            ->cooldown(12)
            ->creativeCategory(CreativeCategory::EQUIPMENT)
            ->factory(static function (): Item {
                $item = new BuiltItem('item.visionengine:potion_launcher.name');
                $item->setCustomName('§r§9Potion Launcher');
                return $item;
            })
            ->onUse($ability)
            ->onInteract($ability)
            ->register();
    }

    private static function launchPotion(Player $player): ItemUseResult  {
        $key = strtolower($player->getName());
        $now = microtime(true);
        if (($now - (self::$potionLauncherUses[$key] ?? 0.0)) < self::POTION_LAUNCHER_DELAY) {
            return ItemUseResult::FAIL;
        }
        self::$potionLauncherUses[$key] = $now;

        $inventory = $player->getInventory();
        foreach ($inventory->getContents() as $slot => $potion) {
            if (!$potion instanceof Potion && !$potion instanceof SplashPotion) {
                continue;
            }

            if (Manager::SETTINGS()->hasGuidedPotions($player->getName())) {
                Manager::POTION()->applyGuided($player, $potion->getType());
                if ($player->hasFiniteResources()) {
                    $potion->pop();
                    $inventory->setItem($slot, $potion);
                }
                return ItemUseResult::SUCCESS;
            }

            $location = $player->getLocation();
            $projectile = new SplashPotionEntity(
                Location::fromObject($player->getEyePos(), $player->getWorld(), $location->yaw, $location->pitch),
                $player,
                $potion->getType()
            );
            $projectile->setMotion($player->getDirectionVector()->multiply(0.5));
            $launchEvent = new ProjectileLaunchEvent($projectile);
            $launchEvent->call();
            if ($launchEvent->isCancelled()) {
                $projectile->flagForDespawn();
                return ItemUseResult::FAIL;
            }

            $projectile->spawnToAll();
            $player->getWorld()->addSound($location, new ThrowSound());
            if ($player->hasFiniteResources()) {
                $potion->pop();
                $inventory->setItem($slot, $potion);
            }
            return ItemUseResult::SUCCESS;
        }

        $player->sendMessage('§1[§9Vision§1] §cVous n’avez aucune potion dans votre inventaire.');
        return ItemUseResult::FAIL;
    }

    private static function registerSwords(): void  {
        $group = Manager::BRANDING()->itemText('creative_groups.swords', 'Épées {server_name}');
        foreach ([
            ['visionne_sword', 5500, 25],
        ] as [$id, $durability, $attack]) {
            NayTools::item('visionengine:' . $id, 'item.visionengine:' . $id . '.name')
                ->texture($id)
                ->tool('sword', ToolTier::DIAMOND, durability: $durability, attackPoints: $attack)
                ->creativeCategory(CreativeCategory::EQUIPMENT)
                ->creativeGroup($group)
                ->register();
        }
    }

    private static function registerArmors(): void  {
        $group = Manager::BRANDING()->itemText('creative_groups.armors', 'Armures {server_name}');
        $sets = [
            'visionne' => [
                ['helmet', 4, 3700],
                ['chestplate', 9, 4000],
                ['leggings', 7, 3900],
                ['boots', 3, 3600],
            ],
        ];

        foreach ($sets as $set => $pieces) {
            foreach ($pieces as [$slot, $defense, $durability]) {
                $id = $set . '_' . $slot;
                NayTools::item('visionengine:' . $id, 'item.visionengine:' . $id . '.name')
                    ->texture($id)
                    ->armor($slot, $defense, $durability, $set === 'visionne' ? 2 : 1)
                    ->creativeCategory(CreativeCategory::EQUIPMENT)
                    ->creativeGroup($group)
                    ->register();
            }
        }
    }
}
