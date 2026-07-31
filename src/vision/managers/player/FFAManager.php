<?php

declare(strict_types=1);

namespace vision\managers\player;

use vision\managers\Manager;

use pocketmine\block\utils\DyeColor;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\PotionType;
use pocketmine\item\StringToItemParser;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\world\Position;
use vision\Main;

final class FFAManager {
    public const LOBBY_TAG = 'vision_lobby_item';
    private Config $config;

    public function __construct(private readonly Main $plugin)  {
        $this->config = new Config($plugin->getDataFolder() . 'kitffa.json', Config::JSON, []);
    }

    public function setPos(int $index, Position $position): void  {
        $this->config->set('world', $position->getWorld()->getFolderName());
        $this->config->set('pos' . $index, [
            'x' => $position->getFloorX(),
            'y' => $position->getFloorY(),
            'z' => $position->getFloorZ(),
        ]);
        $this->config->save();
    }

    public function bounds(): ?array  {
        $pos1 = $this->config->get('pos1');
        $pos2 = $this->config->get('pos2');
        $world = $this->config->get('world');
        if (!is_array($pos1) || !is_array($pos2) || !is_string($world)) {
            return null;
        }
        return [
            'world' => $world,
            'minX' => min((int) $pos1['x'], (int) $pos2['x']),
            'maxX' => max((int) $pos1['x'], (int) $pos2['x']),
            'minY' => min((int) $pos1['y'], (int) $pos2['y']),
            'maxY' => max((int) $pos1['y'], (int) $pos2['y']),
            'minZ' => min((int) $pos1['z'], (int) $pos2['z']),
            'maxZ' => max((int) $pos1['z'], (int) $pos2['z']),
        ];
    }

    public function isInside(Position $position): bool  {
        $bounds = $this->bounds();
        if ($bounds === null || $position->getWorld()->getFolderName() !== $bounds['world']) {
            return false;
        }
        return $position->getFloorX() >= $bounds['minX'] && $position->getFloorX() <= $bounds['maxX']
            && $position->getFloorZ() >= $bounds['minZ'] && $position->getFloorZ() <= $bounds['maxZ'];
    }

    public function giveKit(Player $player): void  {
        $player->setHealth($player->getMaxHealth());
        $parser = StringToItemParser::getInstance();
        $sword = $parser->parse('visionengine:visionne_sword') ?? VanillaItems::DIAMOND_SWORD();
        $sword->setCustomName(Manager::BRANDING()->itemText('kit_names.sword', '§r{primary}Épée FFA'));
        $sword->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 5));

        $armorIds = [
            'helmet' => 'visionengine:visionne_helmet',
            'chestplate' => 'visionengine:visionne_chestplate',
            'leggings' => 'visionengine:visionne_leggings',
            'boots' => 'visionengine:visionne_boots',
        ];
        $armor = [];
        foreach ($armorIds as $slot => $id) {
            $item = $parser->parse($id);
            if ($item === null) {
                continue;
            }
            $item->setCustomName(Manager::BRANDING()->itemText('kit_names.' . $slot, '§r{primary}Armure FFA'));
            $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 4));
            $armor[] = $item;
        }

        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getInventory()->setItem(0, $sword);
        $player->getInventory()->setItem(1, VanillaItems::ENDER_PEARL()->setCount(16));
        $launcher = $parser->parse('visionengine:potion_launcher');
        if ($launcher !== null) {
            $player->getInventory()->setItem(2, $launcher);
        }
        $potion = VanillaItems::SPLASH_POTION()->setType(PotionType::STRONG_HEALING());
        for ($slot = 3; $slot < $player->getInventory()->getSize(); ++$slot) {
            $player->getInventory()->setItem($slot, clone $potion);
        }
        foreach ($armor as $item) {
            $player->getArmorInventory()->setItem($item->getArmorSlot(), $item);
        }
        $duration = 20 * 60 * 60 * 24;
        $player->getEffects()->add(new EffectInstance(VanillaEffects::STRENGTH(), $duration, 1, false));
        $player->getEffects()->add(new EffectInstance(VanillaEffects::RESISTANCE(), $duration, 0, false));
        $player->getEffects()->add(new EffectInstance(VanillaEffects::SPEED(), $duration, 1, false));
    }

    public function spawnPosition(): ?Position {
        $bounds = $this->bounds();
        if ($bounds === null) {
            return null;
        }
        $worldManager = $this->plugin->getServer()->getWorldManager();
        $worldManager->loadWorld($bounds['world']);
        $world = $worldManager->getWorldByName($bounds['world']);
        if ($world === null) {
            return null;
        }
        return new Position(
            (($bounds['minX'] + $bounds['maxX']) / 2) + 0.5,
            (($bounds['minY'] + $bounds['maxY']) / 2) + 1,
            (($bounds['minZ'] + $bounds['maxZ']) / 2) + 0.5,
            $world
        );
    }

    public function giveLobbyItems(Player $player, bool $playersHidden = false): void  {
        $inventory = $player->getInventory();
        $inventory->clearAll();
        $player->getArmorInventory()->clearAll();
        $this->updateVisibilityItem($player, $playersHidden);
        $inventory->setItem(4, $this->lobbyItem(VanillaItems::COMPASS(), 'settings', Manager::BRANDING()->format('§r{primary}Paramètres')));
        $inventory->setItem(6, $this->lobbyItem(VanillaItems::PAPER(), 'league', '§r§9Votre ligue'));
    }

    public function clearCombatEffects(Player $player): void {
        $nightVision = $player->getEffects()->get(VanillaEffects::NIGHT_VISION());
        $player->getEffects()->clear();
        if ($nightVision !== null) {
            $player->getEffects()->add(clone $nightVision);
        }
    }

    public function updateVisibilityItem(Player $player, bool $playersHidden): void  {
        $dye = VanillaItems::DYE()->setColor($playersHidden ? DyeColor::RED : DyeColor::GREEN);
        $player->getInventory()->setItem(2, $this->lobbyItem(
            $dye,
            'players_visibility',
            $playersHidden ? '§r§cAfficher les joueurs' : '§r§aCacher les joueurs'
        ));
    }

    public function hasLobbyItems(Player $player): bool  {
        return $this->isLobbyItem($player->getInventory()->getItem(2))
            && $this->isLobbyItem($player->getInventory()->getItem(4))
            && $this->isLobbyItem($player->getInventory()->getItem(6));
    }

    public function isLobbyItem(Item $item): bool  {
        return $item->getNamedTag()->getString(self::LOBBY_TAG, '') !== '';
    }

    public function lobbyAction(Item $item): string  {
        return $item->getNamedTag()->getString(self::LOBBY_TAG, '');
    }

    private function lobbyItem(Item $item, string $action, string $name): Item  {
        $item->setCustomName($name);
        $item->getNamedTag()->setString(self::LOBBY_TAG, $action);
        return $item;
    }
}
