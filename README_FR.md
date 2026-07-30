# VisionEngineFFA

[![Read in English](https://img.shields.io/badge/README-English-0969da)](README.md)
[![Discord](https://img.shields.io/badge/Discord-Rejoindre_VisionMC-5865F2?logo=discord&logoColor=white)](https://discord.gg/visionmc)

Plugin KitFFA pour PocketMine-MP utilisant les objets et formulaires personnalisés de NayTools.

## Fonctionnalités

- Configuration de la zone KitFFA avec `/kitffa`.
- Kit automatique à la sortie de la zone KitFFA.
- Épées et armures personnalisées enregistrées avec NayTools.
- Menu `/settings` utilisant les formulaires NayTools.
- Objets de raccourci dans la barre rapide de la zone KitFFA.
- Statistiques FFA sauvegardées en JSON : kills, morts, K/D, série actuelle et meilleure série.
- Système de combat avec délai de 15 secondes.
- Délais pour les perles de l'End et les pommes dorées.
- Isolation optionnelle de la visibilité pendant les combats.
- Alertes et sanctions anticheat basiques.
- Mode maintenance avec gestion de la whitelist.
- Installation automatique du pack de ressources.
- Classements holographiques des kills et des morts.

## Prérequis

- PocketMine-MP API 5.
- NayTools installé et activé.

NayTools n'est pas inclus dans ce dépôt et ne sera pas fourni. Il est réservé aux développeurs qui souhaitent s'en inspirer ou utiliser leur propre outil compatible.

## Commandes

- `/kitffa pos1` et `/kitffa pos2` : configure la zone KitFFA. OP uniquement.
- `/kitffa info` : affiche la zone configurée. OP uniquement.
- `/kitffa givekit` : donne le kit FFA. OP uniquement.
- `/leaderboard <kills|deaths|remove>` : gère les classements. OP uniquement.
- `/maintenance` : active ou désactive la maintenance. OP uniquement.
- `/settings` : ouvre les paramètres du joueur.
- `/stats [joueur]` : affiche les statistiques FFA.
- `/rekit` ou `/refill` : redonne le kit FFA.

## Configuration

La configuration publique principale se trouve dans `plugin_data/VisionEngineFFA/config.yml`.

Elle permet de modifier le nom, l'adresse IP, les couleurs, les titres du scoreboard, les préfixes et les noms affichés des objets. La base de données est configurée dans `config.json` et `database.json`.

## Pack de ressources

Le plugin fournit `resources/VisionPackFFA.zip`. Il contient uniquement les textures nécessaires aux armures et épées Saphir, Platine et Visionne, ainsi que les fichiers requis par Minecraft.

Au démarrage, le plugin copie automatiquement le pack dans `resource_packs` et l'ajoute à `resource_packs.yml`. Un redémarrage est nécessaire après la première installation ou une mise à jour du pack.

## Licence des textures

Le code du plugin peut être réutilisé selon la licence du dépôt. Les textures de `VisionPackFFA.zip` ne sont pas des ressources libres.

Il est interdit de les revendre, de les redistribuer, de les réutiliser sur un autre serveur en production ou de se les attribuer. Toute utilisation extérieure à ce projet exige l'autorisation explicite du propriétaire.

## Placeholders

Consultez `resources/placeholders.yml` pour les placeholders configurables.

## Communauté

Rejoignez VisionMC : [discord.gg/visionmc](https://discord.gg/visionmc)
