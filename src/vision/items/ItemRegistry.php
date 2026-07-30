<?php

declare(strict_types=1);

namespace vision\items;


use vision\managers\Manager;

use NayTools\NayTools;
use pocketmine\inventory\CreativeCategory;
use pocketmine\item\ToolTier;
final class ItemRegistry {
    public static function registerAll(): void  {
        self::registerSwords();
        self::registerArmors();
    }

    private static function registerSwords(): void  {
        $group = Manager::BRANDING()->itemText('creative_groups.swords', 'Épées {server_name}');
        foreach ([
            ['saphir_sword', 3600, 14],
            ['platine_sword', 4400, 18],
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
            'farming' => [
                ['helmet', 2, 900],
                ['chestplate', 6, 1100],
                ['leggings', 5, 1050],
                ['boots', 2, 950],
            ],
            'saphir' => [
                ['helmet', 3, 2600],
                ['chestplate', 8, 3000],
                ['leggings', 6, 2800],
                ['boots', 3, 2500],
            ],
            'platine' => [
                ['helmet', 3, 3100],
                ['chestplate', 8, 3500],
                ['leggings', 6, 3300],
                ['boots', 3, 3000],
            ],
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
