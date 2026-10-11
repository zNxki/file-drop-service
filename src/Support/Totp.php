<?php
declare(strict_types=1);

namespace FileDrop\Support;

final class Totp
{
    public const PERIOD = 30;
    public const ISSUER = 'FILE DROP';
    private const DIGITS = 6;
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        return self::encode(random_bytes($bytes));
    }

    public static function verify(string $secret, string $code, ?int $now = null, int $window = 1): ?int
    {
        $key = self::decode($secret);
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if ($key === null || preg_match('/^\d{' . self::DIGITS . '}$/', $code) !== 1) {
            return null;
        }

        $current = intdiv($now ?? time(), self::PERIOD);
        $found = null;
        for ($step = $current - $window; $step <= $current + $window; $step++) {
            if (hash_equals(self::code($key, $step), $code) && $found === null) {
                $found = $step;
            }
        }

        return $found;
    }

    public static function uri(string $secret, string $account, string $issuer = self::ISSUER): string
    {
        return sprintf(
            'otpauth://totp/%s:%%20%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($account),
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD
        );
    }

    public static function isValidSecret(string $secret): bool
    {
        $key = self::decode($secret);

        return $key !== null && strlen($key) >= 10;
    }

    public static function code(string $key, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function encode(string $binary): string
    {
        $bits = '';
        foreach (str_split($binary) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function decode(string $secret): ?string
    {
        $secret = strtoupper(str_replace([' ', '-', '='], '', $secret));
        if ($secret === '' || preg_match('/^[A-Z2-7]+$/', $secret) !== 1) {
            return null;
        }

        $bits = '';
        foreach (str_split($secret) as $char) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}