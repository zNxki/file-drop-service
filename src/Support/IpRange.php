<?php
declare(strict_types=1);

namespace FileDrop\Support;

use FileDrop\Http\Request;

final class IpRange
{
    public const MIN_BITS_V4 = 16;
    public const MIN_BITS_V6 = 48;

    public static function normalize(string $input): ?string
    {
        $input = trim($input);
        [$address, $bits] = array_pad(explode('/', $input, 2), 2, null);

        $packed = filter_var($address, FILTER_VALIDATE_IP) !== false ? inet_pton($address) : false;
        if ($packed === false) {
            return null;
        }

        $max = strlen($packed) * 8;
        if ($bits !== null && ($bits === '' || !ctype_digit($bits) || strlen($bits) > 3)) {
            return null;
        }
        $bits = $bits === null ? $max : (int) $bits;
        $min = $max === 32 ? self::MIN_BITS_V4 : self::MIN_BITS_V6;
        if ($bits > $max || ($bits < $max && $bits < $min)) {
            return null;
        }

        $masked = '';
        foreach (str_split($packed) as $i => $byte) {
            $keep = max(0, min(8, $bits - $i * 8));
            $masked .= chr(ord($byte) & ((0xFF << (8 - $keep)) & 0xFF));
        }
        $text = (string) inet_ntop($masked);

        return $bits === $max ? $text : $text . '/' . $bits;
    }

    public static function fromClientKey(string $key): string
    {
        if (preg_match('~^([0-9a-f]{16})/64$~', $key, $m) === 1) {
            $text = inet_ntop((string) hex2bin($m[1]) . str_repeat("\0", 8));

            return ($text === false ? $key : $text) . '/64';
        }

        return $key;
    }

    public static function matches(string $ip, array $ranges): bool
    {
        return $ranges !== [] && Request::inRanges($ip, $ranges);
    }
}