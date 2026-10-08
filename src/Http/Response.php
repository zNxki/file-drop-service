<?php
declare(strict_types=1);

namespace FileDrop\Http;

final class Response
{
    private function __construct(
        private readonly int $status,
        private readonly array $headers,
        private readonly ?string $body = null,
        private readonly ?string $file = null,
    ) {
    }

    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        return new self(
            $status,
            ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'] + $headers,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($status, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store',
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'none'",
        ], $html);
    }

    public static function redirect(string $url, int $status = 303): self
    {
        return new self($status, ['Location' => $url], '');
    }

    public static function file(string $path, string $name, string $mime, int $size): self
    {
        $fallback = str_replace(['"', '\\', '%'], '_', preg_replace('/[^\x20-\x7E]/', '_', $name) ?? 'file');

        return new self(200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) $size,
            'Content-Disposition' => sprintf('attachment; filename="%s"; filename*=UTF-8\'\'%s', $fallback, rawurlencode($name)),
            'Cache-Control' => 'private, no-store',
        ], null, $path);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        if ($this->file !== null) {
            $this->stream($this->file);
            return;
        }
        echo $this->body;
    }

    private function stream(string $path): void
    {
        @set_time_limit(0);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return;
        }
        while (!feof($handle) && !connection_aborted()) {
            echo fread($handle, 262144);
            flush();
        }
        fclose($handle);
    }
}