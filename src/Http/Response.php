<?php
declare(strict_types=1);

namespace FileDrop\Http;

final class Response
{
    private function __construct(
        private readonly int $status,
        private readonly array $headers,
        private readonly ?string $body = null,
        private $stream = null,
        private readonly int $offset = 0,
        private readonly int $length = 0,
        private readonly ?\Closure $onSent = null,
        private readonly ?\Traversable $chunks = null,
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

    public static function html(string $html, int $status = 200, array $headers = []): self
    {
        return new self($status, $headers + [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store',
            'Vary' => 'X-Fd-Nav',
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data: blob:; media-src 'self' blob:; object-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'",
        ], $html);
    }

    public static function redirect(string $url, int $status = 303): self
    {
        return new self($status, ['Location' => $url], '');
    }

    public function withHeader(string $name, string $value): self
    {
        return new self(
            $this->status,
            [$name => $value] + $this->headers,
            $this->body,
            $this->stream,
            $this->offset,
            $this->length,
            $this->onSent,
            $this->chunks,
        );
    }

    public static function download($handle, string $name, string $mime, int $size, ?\Closure $onSent = null): self
    {
        return new self(200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) $size,
            'Content-Disposition' => self::disposition('attachment', $name),
            'Accept-Ranges' => 'none',
            'Cache-Control' => 'private, no-store',
        ], null, $handle, 0, $size, $onSent);
    }

    public static function downloadChunks(\Traversable $chunks, string $name, string $mime, int $size, ?\Closure $onSent = null): self
    {
        return new self(200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) $size,
            'Content-Disposition' => self::disposition('attachment', $name),
            'Accept-Ranges' => 'none',
            'Cache-Control' => 'private, no-store',
        ], null, null, 0, $size, $onSent, $chunks);
    }

    public static function inline($handle, string $name, string $mime, int $size, ?string $range = null, array $extra = []): self
    {
        $headers = [
            'Content-Type' => $mime,
            'Content-Disposition' => self::disposition('inline', $name),
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, no-store',
        ] + $extra;

        $parsed = self::parseRange($range, $size);

        if ($parsed === false) {
            fclose($handle);
            return new self(416, ['Content-Range' => 'bytes */' . $size, 'Cache-Control' => 'no-store'], '');
        }
        if ($parsed === null) {
            return new self(200, $headers + ['Content-Length' => (string) $size], null, $handle, 0, $size);
        }

        [$start, $end] = $parsed;
        return new self(206, $headers + [
            'Content-Range' => sprintf('bytes %d-%d/%d', $start, $end, $size),
            'Content-Length' => (string) ($end - $start + 1),
        ], null, $handle, $start, $end - $start + 1);
    }

    private static function parseRange(?string $header, int $size): array|false|null
    {
        if ($header === null || !preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $m) || ($m[1] === '' && $m[2] === '')) {
            return null;
        }
        [, $from, $to] = $m;

        if ($from === '') { // "-N": ultimi N byte
            $n = (int) $to;
            return $n === 0 ? false : [max(0, $size - $n), $size - 1];
        }

        $start = (int) $from;
        if ($to !== '' && (int) $to < $start) {
            return null;
        }
        if ($start >= $size) {
            return false;
        }
        return [$start, $to === '' ? $size - 1 : min((int) $to, $size - 1)];
    }

    private static function disposition(string $type, string $name): string
    {
        $fallback = str_replace(['"', '\\', '%'], '_', preg_replace('/[^\x20-\x7E]/', '_', $name) ?? 'file');
        return sprintf('%s; filename="%s"; filename*=UTF-8\'\'%s', $type, $fallback, rawurlencode($name));
    }

    private const BASE_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'no-referrer',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), serial=(), bluetooth=(), interest-cohort=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Cross-Origin-Resource-Policy' => 'same-origin',
        'X-Frame-Options' => 'DENY',
    ];

    public function send(bool $secure = false): void
    {
        http_response_code($this->status);
        foreach (self::BASE_HEADERS as $name => $value) {
            header($name . ': ' . $value);
        }
        if ($secure) {
            header('Strict-Transport-Security: max-age=31536000');
        }
        $this->sendDefaultCsp();
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        if ($this->chunks !== null) {
            $this->streamChunks();
            return;
        }
        if ($this->stream !== null) {
            $this->stream();
            return;
        }
        echo $this->body;
    }

    private function sendDefaultCsp(): void
    {
        $headers = array_change_key_case($this->headers, CASE_LOWER);
        if (isset($headers['content-security-policy'])) {
            return;
        }
        if (str_starts_with(strtolower((string) ($headers['content-type'] ?? '')), 'application/pdf')) {
            return;
        }
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
    }

    private function streamChunks(): void
    {
        ignore_user_abort(true);
        @set_time_limit(0);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        try {
            foreach ($this->chunks as $chunk) {
                if (connection_aborted()) {
                    break;
                }
                echo $chunk;
                flush();
            }
        } catch (\Throwable $e) {
            error_log((string) $e);
        } finally {
            if ($this->onSent !== null) {
                ($this->onSent)();
            }
        }
    }

    private function stream(): void
    {
        ignore_user_abort(true);
        @set_time_limit(0);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $handle = $this->stream;
        if ($this->offset > 0) {
            fseek($handle, $this->offset);
        }
        $remaining = $this->length;
        while ($remaining > 0 && !feof($handle) && !connection_aborted()) {
            $chunk = fread($handle, min(262144, $remaining));
            if ($chunk === false || $chunk === '') {
                break;
            }
            echo $chunk;
            $remaining -= strlen($chunk);
            flush();
        }
        fclose($handle);

        if ($this->onSent !== null) {
            ($this->onSent)();
        }
    }
}