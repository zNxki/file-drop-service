<?php
declare(strict_types=1);

namespace FileDrop\Support;

final class I18n
{
    public const COOKIE = 'fd-lang';

    private static string $root = '';
    private static string $default = 'it';
    private static array $locales = [];
    private static string $current = 'it';
    private static array $messages = [];

    public static function boot(string $root): void
    {
        $config = require $root . '/config/locales.php';
        self::$root = $root;
        self::$locales = $config['locales'];
        self::$default = $config['default'];
        self::$current = self::$default;
    }

    public static function codes(): array
    {
        return array_keys(self::$locales);
    }

    public static function locales(): array
    {
        return self::$locales;
    }

    public static function has(string $code): bool
    {
        return isset(self::$locales[$code]);
    }

    public static function default(): string
    {
        return self::$default;
    }

    public static function current(): string
    {
        return self::$current;
    }

    public static function set(string $code): void
    {
        self::$current = self::has($code) ? $code : self::$default;
    }

    public static function setting(string $key): string
    {
        return self::$locales[self::$current][$key];
    }

    public static function negotiate(?string $cookie, ?string $acceptLanguage): string
    {
        if ($cookie !== null && self::has($cookie)) {
            return $cookie;
        }

        $ranked = [];
        foreach (explode(',', (string) $acceptLanguage) as $part) {
            [$tag, $q] = array_pad(explode(';q=', trim($part), 2), 2, '1');
            $primary = strtolower(explode('-', trim($tag))[0]);
            if (self::has($primary) && !isset($ranked[$primary])) {
                $ranked[$primary] = (float) $q;
            }
        }
        arsort($ranked);

        return array_key_first($ranked) ?? self::$default;
    }

    public static function t(string $key, array $params = []): string
    {
        $text = self::messages(self::$current)[$key] ?? self::messages(self::$default)[$key] ?? $key;

        if ($params === []) {
            return $text;
        }

        $map = [];
        foreach ($params as $name => $value) {
            $map['{' . $name . '}'] = (string) $value;
        }

        return strtr($text, $map);
    }

    public static function plural(string $key, int $count, array $params = []): string
    {
        return self::t($key . ($count === 1 ? '.one' : '.other'), ['n' => $count] + $params);
    }

    public static function duration(int $seconds): string
    {
        return match (true) {
            $seconds % 86400 === 0 => self::plural('duration.days', intdiv($seconds, 86400)),
            $seconds % 3600 === 0 => self::plural('duration.hours', intdiv($seconds, 3600)),
            default => self::plural('duration.seconds', $seconds),
        };
    }

    public static function client(): array
    {
        $out = [];
        foreach (self::messages(self::$default) as $key => $_) {
            if (str_starts_with($key, 'js.')) {
                $out[$key] = self::messages(self::$current)[$key] ?? self::messages(self::$default)[$key];
            }
        }

        return $out;
    }

    private static function messages(string $code): array
    {
        return self::$messages[$code] ??= require self::$root . '/lang/' . $code . '.php';
    }
}
