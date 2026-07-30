<?php

declare(strict_types=1);

namespace VisionEngineFFA\Ranks;

enum RankType: string
{
    case PAYSAN = '§7';
    case CONTRIBUTEUR = '§1';
    case CHEVALIER = '§e';
    case BOOSTER = '§d';
    case HERO = '§9';
    case VIDEASTE = '§5';
    case KING = '§c§c';
    case CUSTOM = '§6';
    case GUIDE = '§a';
    case MODERATEUR = '§2';
    case MODERATEURPLUS = '§2§2';
    case SUPERMODERATEUR = '§3';
    case ADMINISTRATEUR = '§c';
    case CREATEURS = '§4';

    public static function enumToString(self $type): string
    {
        return match ($type) {
            self::MODERATEURPLUS => 'Moderateur+',
            self::SUPERMODERATEUR => 'Super-Moderateur',
            self::CREATEURS => 'Créateur',
            default => ucfirst(strtolower($type->name)),
        };
    }

    public static function fromString(string $value): ?self
    {
        foreach (self::cases() as $case) {
            if (strtolower($case->name) === strtolower($value) || strtolower(self::enumToString($case)) === strtolower($value)) {
                return $case;
            }
        }
        return null;
    }
}
