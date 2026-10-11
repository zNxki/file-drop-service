<?php
declare(strict_types=1);

namespace FileDrop\Support;

final class Flags
{
    public static function svg(string $country): string
    {
        $body = self::shapes(strtolower($country));
        if ($body === null) {
            return '<svg class="flag flag-none" viewBox="0 0 36 24" aria-hidden="true" focusable="false">'
                . '<rect width="36" height="24" fill="currentColor" opacity=".12"/>'
                . '<g fill="none" stroke="currentColor" stroke-width="1.4" opacity=".6" transform="translate(0 0)">'
                . '<circle cx="18" cy="12" r="7"/><ellipse cx="18" cy="12" rx="3" ry="7"/><path d="M11 12h14"/></g></svg>';
        }

        return '<svg class="flag" viewBox="0 0 36 24" aria-hidden="true" focusable="false">' . $body . '</svg>';
    }

    private static function v(string $a, string $b, string $c): string
    {
        return sprintf('<rect width="12" height="24" fill="%s"/><rect x="12" width="12" height="24" fill="%s"/><rect x="24" width="12" height="24" fill="%s"/>', $a, $b, $c);
    }

    private static function h(string $a, string $b, string $c): string
    {
        return sprintf('<rect width="36" height="8" fill="%s"/><rect y="8" width="36" height="8" fill="%s"/><rect y="16" width="36" height="8" fill="%s"/>', $a, $b, $c);
    }

    private static function cross(string $bg, string $fg, float $x, float $w): string
    {
        return sprintf('<rect width="36" height="24" fill="%s"/><rect x="%s" width="%s" height="24" fill="%s"/><rect y="%s" width="36" height="%s" fill="%s"/>', $bg, $x, $w, $fg, 12 - $w / 2, $w, $fg);
    }

    private static function star(float $cx, float $cy, float $r, string $fill, float $rot = -90): string
    {
        $pts = [];
        for ($i = 0; $i < 10; $i++) {
            $rad = deg2rad($rot + $i * 36);
            $d = $i % 2 === 0 ? $r : $r * 0.382;
            $pts[] = round($cx + $d * cos($rad), 2) . ',' . round($cy + $d * sin($rad), 2);
        }

        return sprintf('<polygon points="%s" fill="%s"/>', implode(' ', $pts), $fill);
    }

    private static function shapes(string $cc): ?string
    {
        return match ($cc) {
            'it' => self::v('#009246', '#fff', '#ce2b37'),
            'fr' => self::v('#002654', '#fff', '#ed2939'),
            'ie' => self::v('#169b62', '#fff', '#ff883e'),
            'ro' => self::v('#002b7f', '#fcd116', '#ce1126'),
            'be' => self::v('#000', '#fdda24', '#ef3340'),
            'de' => self::h('#000', '#dd0000', '#ffce00'),
            'nl' => self::h('#ae1c28', '#fff', '#21468b'),
            'ru' => self::h('#fff', '#0039a6', '#d52b1e'),
            'at' => self::h('#ed2939', '#fff', '#ed2939'),
            'hu' => self::h('#ce2939', '#fff', '#477050'),
            'bg' => self::h('#fff', '#00966e', '#d62612'),
            'in' => self::h('#f93', '#fff', '#138808') . '<circle cx="18" cy="12" r="2.6" fill="none" stroke="#000080" stroke-width=".7"/>',
            'pl' => '<rect width="36" height="12" fill="#fff"/><rect y="12" width="36" height="12" fill="#dc143c"/>',
            'ua' => '<rect width="36" height="12" fill="#0057b7"/><rect y="12" width="36" height="12" fill="#ffd700"/>',
            'es' => '<rect width="36" height="24" fill="#aa151b"/><rect y="6" width="36" height="12" fill="#f1bf00"/>',
            'pt' => '<rect width="36" height="24" fill="#f00"/><rect width="14" height="24" fill="#060"/><circle cx="14" cy="12" r="4.4" fill="#ff0"/><circle cx="14" cy="12" r="2.6" fill="#f00"/>',
            'cz' => '<rect width="36" height="12" fill="#fff"/><rect y="12" width="36" height="12" fill="#d7141a"/><polygon points="0,0 18,12 0,24" fill="#11457e"/>',
            'ch' => '<rect width="36" height="24" fill="#d52b1e"/><rect x="15" y="4" width="6" height="16" fill="#fff"/><rect x="10" y="9" width="16" height="6" fill="#fff"/>',
            'se' => self::cross('#006aa7', '#fecc00', 10, 4),
            'fi' => self::cross('#fff', '#003580', 10, 5),
            'dk' => self::cross('#c8102e', '#fff', 10, 4),
            'no' => self::cross('#ba0c2f', '#fff', 10, 6) . '<rect x="11.2" width="3.6" height="24" fill="#00205b"/><rect y="10.2" width="36" height="3.6" fill="#00205b"/>',
            'gb' => '<rect width="36" height="24" fill="#012169"/>'
                . '<path d="M0 0L36 24M36 0L0 24" stroke="#fff" stroke-width="4.8"/>'
                . '<path d="M0 0L36 24M36 0L0 24" stroke="#c8102e" stroke-width="1.6"/>'
                . '<rect x="14" width="8" height="24" fill="#fff"/><rect y="8" width="36" height="8" fill="#fff"/>'
                . '<rect x="15.2" width="5.6" height="24" fill="#c8102e"/><rect y="9.2" width="36" height="5.6" fill="#c8102e"/>',
            'jp' => '<rect width="36" height="24" fill="#fff"/><circle cx="18" cy="12" r="7" fill="#bc002d"/>',
            'tr' => '<rect width="36" height="24" fill="#e30a17"/><circle cx="14" cy="12" r="6.2" fill="#fff"/><circle cx="15.8" cy="12" r="5" fill="#e30a17"/>' . self::star(20.4, 12, 2.6, '#fff', 0),
            'cn' => '<rect width="36" height="24" fill="#de2910"/>' . self::star(6, 6, 3.6, '#ffde00')
                . self::star(12, 2.6, 1.1, '#ffde00') . self::star(14.4, 5.2, 1.1, '#ffde00')
                . self::star(14.4, 8.6, 1.1, '#ffde00') . self::star(12, 11.2, 1.1, '#ffde00'),
            'br' => '<rect width="36" height="24" fill="#009c3b"/><polygon points="18,3 33,12 18,21 3,12" fill="#ffdf00"/><circle cx="18" cy="12" r="5.2" fill="#002776"/><path d="M13 11.2q5-1.6 10 1.4" stroke="#fff" stroke-width=".9" fill="none"/>',
            default => null,
        };
    }
}
