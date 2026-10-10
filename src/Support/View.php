<?php
declare(strict_types=1);

namespace FileDrop\Support;

final class View
{
    public function __construct(private readonly string $dir)
    {
    }

    public function render(string $template, array $data = []): string
    {
        $content = $this->capture($template, $data);
        return $this->capture('layout', $data + ['content' => $content]);
    }

    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function number(int $n): string
    {
        return number_format($n, 0, ',', '.');
    }

    public function time(int $timestamp): string
    {
        return sprintf(
            '<time datetime="%s" data-local>%s UTC</time>',
            gmdate('Y-m-d\TH:i:s\Z', $timestamp),
            gmdate('d/m/Y H:i', $timestamp),
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
        return number_format($n, $i === 0 ? 0 : 1, ',', '.') . ' ' . $units[$i];
    }

    private function capture(string $template, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $this->dir . '/' . $template . '.php';
        return (string) ob_get_clean();
    }
}