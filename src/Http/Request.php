<?php
declare(strict_types=1);

namespace FileDrop\Http;

final readonly class Request
{
    public function __construct(
        public string $method,
        public string $path,
        public array $query,
        public array $post,
        public array $files,
        public array $server,
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            '/' . trim(rawurldecode($uri), '/'),
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER,
        );
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $this->server[$key] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }

    public function isApi(): bool
    {
        return $this->path === '/api' || str_starts_with($this->path, '/api/');
    }

    public function origin(): string
    {
        $https = !empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off';
        return ($https ? 'https' : 'http') . '://' . ($this->server['HTTP_HOST'] ?? 'localhost');
    }

    public function contentLength(): int
    {
        return (int) ($this->server['CONTENT_LENGTH'] ?? 0);
    }
}