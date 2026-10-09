<?php
declare(strict_types=1);

namespace FileDrop\Support;

final class Preview
{
    private const KINDS = [
        'image/jpeg' => 'image',
        'image/png' => 'image',
        'image/gif' => 'image',
        'image/webp' => 'image',
        'image/avif' => 'image',
        'application/pdf' => 'pdf',
        'video/mp4' => 'video',
        'video/webm' => 'video',
        'video/ogg' => 'video',
        'text/plain' => 'text',
        'text/csv' => 'text',
        'text/markdown' => 'text',
        'application/json' => 'text',
    ];

    public static function kind(string $mime): ?string
    {
        return self::KINDS[strtolower(trim(explode(';', $mime)[0]))] ?? null;
    }

    public static function contentType(string $kind, string $mime): string
    {
        return $kind === 'text' ? 'text/plain; charset=utf-8' : strtolower(trim(explode(';', $mime)[0]));
    }

    public static function headers(string $kind): array
    {
        return $kind === 'pdf'
            ? []
            : ['Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox"];
    }
}