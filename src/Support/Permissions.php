<?php
declare(strict_types=1);

namespace FileDrop\Support;

final class Permissions
{
    public const STATS_VIEW = 'stats.view';
    public const FILES_VIEW = 'files.view';
    public const FILES_DELETE = 'files.delete';
    public const SECURITY_VIEW = 'security.view';
    public const SECURITY_MANAGE = 'security.manage';
    public const USERS_MANAGE = 'users.manage';

    public const LABELS = [
        self::STATS_VIEW => 'Vedere le statistiche',
        self::FILES_VIEW => 'Vedere i file caricati',
        self::FILES_DELETE => 'Eliminare file e ripulire i scaduti',
        self::SECURITY_VIEW => 'Vedere limiti e IP bloccati',
        self::SECURITY_MANAGE => 'Bloccare e sbloccare IP',
        self::USERS_MANAGE => 'Gestire gli account',
    ];

    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    public static function normalize(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }

        return array_values(array_intersect(self::all(), array_filter($input, 'is_string')));
    }
}