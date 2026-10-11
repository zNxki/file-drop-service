<?php
declare(strict_types=1);

namespace FileDrop\Support;

final class View
{
    private const PAGE_SCRIPTS = [
        '/assets/js/crypto.js',
        '/assets/js/zip.js',
        '/assets/js/highlight.js',
        '/assets/js/zip-preview.js',
        '/assets/js/app.js',
    ];

    private bool $fragment = false;
    private bool $localized = false;
    private string $path = '/';
    private string $origin = '';

    public function __construct(private readonly string $dir)
    {
    }

    public function fragment(bool $on = true): void
    {
        $this->fragment = $on;
    }

    public function localize(string $path, string $origin = ''): void
    {
        $this->localized = true;
        $this->path = $path;
        $this->origin = $origin;
    }

    public function locales(): array
    {
        return I18n::locales();
    }

    public function flag(array $info): string
    {
        return Flags::svg((string) ($info['flag'] ?? ''));
    }

    public function alternate(string $code): string
    {
        return $this->origin . $this->switchUrl($code);
    }

    public function localized(): bool
    {
        return $this->localized;
    }

    public function lang(): string
    {
        return I18n::current();
    }

    public function url(string $path = '/'): string
    {
        if (!$this->localized) {
            return $path;
        }

        return '/' . I18n::current() . ($path === '/' ? '' : $path);
    }

    public function switchUrl(string $code): string
    {
        return '/' . $code . ($this->path === '/' ? '' : $this->path);
    }

    public function raw(string $key, array $params = []): string
    {
        return I18n::t($key, $params);
    }

    public function defaultLocale(): string
    {
        return I18n::default();
    }

    public function t(string $key, array $params = []): string
    {
        return $this->e(I18n::t($key, $params));
    }

    public function tn(string $key, int $count, array $params = []): string
    {
        return $this->e(I18n::plural($key, $count, $params));
    }

    public function th(string $key, array $html): string
    {
        $map = [];
        foreach ($html as $name => $value) {
            $map['{' . $name . '}'] = (string) $value;
        }

        return strtr($this->e(I18n::t($key)), $map);
    }

    public function words(string $text, int &$index): string
    {
        $out = [];
        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            $out[] = sprintf('<span class="w" style="--i:%d">%s</span>', $index++, $this->e($word));
        }

        return implode(' ', $out);
    }

    public function i18nJson(): string
    {
        return json_encode(
            ['lang' => I18n::current(), 'tag' => I18n::setting('tag'), 'messages' => I18n::client()],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR,
        );
    }

    public function render(string $template, array $data = []): string
    {
        $content = $this->capture($template, $data);
        if (!empty($data['panel'])) {
            $content = $this->capture('admin/shell', $data + ['inner' => $content]);
        }
        $scripts = array_merge(!empty($data['script']) ? self::PAGE_SCRIPTS : [], $data['extraScripts'] ?? []);

        if ($this->fragment) {
            return sprintf(
                '<div id="fd-meta" hidden data-title="%s" data-wide="%d" data-noindex="%d" data-scripts="%s"></div>',
                $this->e($data['title'] ?? 'File Drop'),
                empty($data['wide']) ? 0 : 1,
                empty($data['noindex']) ? 0 : 1,
                $this->e(implode(' ', array_map($this->asset(...), $scripts))),
            ) . $content;
        }

        return $this->capture('layout', $data + ['content' => $content, 'pageScripts' => $scripts]);
    }

    public function asset(string $path): string
    {
        $file = dirname($this->dir) . '/public' . $path;
        return $path . (is_file($file) ? '?v=' . filemtime($file) : '');
    }

    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function number(int $n): string
    {
        return number_format($n, 0, I18n::setting('decimal'), I18n::setting('thousands'));
    }

    public function time(int $timestamp): string
    {
        return sprintf(
            '<time datetime="%s" data-local>%s UTC</time>',
            gmdate('Y-m-d\TH:i:s\Z', $timestamp),
            gmdate(I18n::setting('datetime'), $timestamp),
        );
    }

    public function bytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $n = (float) $bytes;
        while ($n >= 1024 && $i < count($units) - 1) {
            $n /= 1024;
            $i++;
        }
        return number_format($n, $i === 0 ? 0 : 1, I18n::setting('decimal'), I18n::setting('thousands')) . ' ' . $units[$i];
    }

    private function capture(string $template, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $this->dir . '/' . $template . '.php';
        return (string) ob_get_clean();
    }
}