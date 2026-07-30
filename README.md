# VisionEngineFFA

[![Lire en français](https://img.shields.io/badge/README-Fran%C3%A7ais-0969da)](README_FR.md)
[![Discord](https://img.shields.io/badge/Discord-Rejoindre_VisionMC-5865F2?logo=discord&logoColor=white)](https://discord.gg/visionmc)

PocketMine-MP KitFFA plugin using NayTools custom items and forms.

## Features

- KitFFA zone configuration with `/kitffa`.
- Automatic kit when leaving the KitFFA zone.
- Custom swords and armor registered through NayTools.
- Player `/settings` form using NayTools forms.
- Lobby hotbar items inside the KitFFA zone.
- JSON FFA stats: kills, deaths, K/D, current streak and best streak.
- Combat system with 15 second timer.
- Ender pearl and golden apple cooldowns.
- Optional combat visibility isolation.
- Basic anticheat alerts and kicks.
- Maintenance command with whitelist toggle.
- Automatic resource pack installer.

## Requirements

- PocketMine-MP API 5.
- NayTools installed and enabled.

NayTools is not included in this repository and will not be provided.

This project is shared for developers who want to study it or use it as a base with their own compatible tooling.

## Commands

- `/kitffa pos1` - set first KitFFA zone corner. OP only.
- `/kitffa pos2` - set second KitFFA zone corner. OP only.
- `/kitffa info` - show configured zone. OP only.
- `/kitffa givekit` - give yourself the FFA kit. OP only.
- `/maintenance` - toggle whitelist maintenance and kick non-OP players. OP only.
- `/settings` - open player settings.
- `/stats [player]` - show FFA stats.

## Configuration

Main public configuration is in `plugin_data/VisionEngineFFA/config.yml`.

You can configure:

- Server name.
- Server IP shown in scoreboard.
- Server colors.
- Scoreboard titles.
- Message prefixes.
- Creative group names.
- Kit item display names.

Database configuration is kept in `config.json` / `database.json`.

## Resource Pack

The plugin ships with `resources/VisionPackFFA.zip`.

It contains only:

- Saphir armor textures.
- Platine armor textures.
- Visionne armor textures.
- Saphir sword texture.
- Platine sword texture.
- Visionne sword texture.
- The required `textures/item_texture.json`.

On startup, the plugin copies the pack into the server `resource_packs` folder and adds it to `resource_packs.yml`.

PocketMine loads resource packs before plugins, so the first install/update needs one server restart.

## Texture License

The plugin code can be reused according to the repository license.

The textures included in `VisionPackFFA.zip` are not open assets.

You are not allowed to:

- Resell the textures.
- Reuse the textures in another production server or project.
- Redistribute the textures as an asset pack.
- Claim the textures as your own.

Use of these textures outside this project requires explicit permission from the owner.

## Placeholders

See `resources/placeholders.yml` for configurable placeholders.
