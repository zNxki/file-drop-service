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
        public array $trustedProxies = [],
    ) {
    }

    public static function fromGlobals(array $trustedProxies = []): self
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            '/' . trim(rawurldecode($uri), '/'),
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER,
            $trustedProxies,
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

    public function isSecure(): bool
    {
        return !empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off';
    }

    public function origin(): string
    {
        return ($this->isSecure() ? 'https' : 'http') . '://' . ($this->server['HTTP_HOST'] ?? 'localhost');
    }

    public function ip(): string
    {
        $remote = $this->server['REMOTE_ADDR'] ?? '';
        $remote = is_string($remote) && filter_var($remote, FILTER_VALIDATE_IP) !== false ? $remote : '0.0.0.0';

        if ($this->trustedProxies === [] || !self::inRanges($remote, $this->trustedProxies)) {
            return $remote;
        }

        $forwarded = $this->header('X-Forwarded-For');
        if ($forwarded === null) {
            return $remote;
        }
        foreach (array_reverse(explode(',', $forwarded)) as $candidate) {
            $candidate = trim($candidate);
            if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
                return $remote;
            }
            if (!self::inRanges($candidate, $this->trustedProxies)) {
                return $candidate;
            }
        }

        return $remote;
    }

    public function clientKey(): string
    {
        $ip = $this->ip();
        $packed = inet_pton($ip);
        if ($packed === false || strlen($packed) !== 16) {
            return $ip;
        }
        if (str_starts_with($packed, str_repeat("\0", 10) . "\xFF\xFF")) {
            return (string) inet_ntop(substr($packed, 12));
        }

        return bin2hex(substr($packed, 0, 8)) . '/64';
    }

    public static function inRanges(string $ip, array $ranges): bool
    {
        $packed = inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        foreach ($ranges as $range) {
            [$subnet, $bits] = array_pad(explode('/', (string) $range, 2), 2, null);
            $network = inet_pton((string) $subnet);
            if ($network === false || strlen($network) !== strlen($packed)) {
                continue;
            }

            $max = strlen($network) * 8;
            if ($bits !== null && !ctype_digit($bits)) {
                continue;
            }
            $bits = $bits === null ? $max : (int) $bits;
            if ($bits > $max) {
                continue;
            }

            $bytes = intdiv($bits, 8);
            $rest = $bits % 8;
            if ($bytes > 0 && substr($packed, 0, $bytes) !== substr($network, 0, $bytes)) {
                continue;
            }
            if ($rest > 0) {
                $mask = (0xFF << (8 - $rest)) & 0xFF;
                if ((ord($packed[$bytes]) & $mask) !== (ord($network[$bytes]) & $mask)) {
                    continue;
                }
            }

            return true;
        }

        return false;
    }

    public function contentLength(): int
    {
        return (int) ($this->server['CONTENT_LENGTH'] ?? 0);
    }
}