<?php
declare(strict_types=1);

namespace FileDrop\Support;

use Dotenv\Dotenv;

final readonly class Config
{
    public function __construct(
        public bool $debug,
        public string $appUrl,
        public int $maxFileSize,
        public int $defaultExpiry,
        public int $maxExpiry,
        public int $codeLength,
        public string $dbPath,
        public string $storagePath,
        public float $cleanupProbability,
        public string $adminPasswordHash = '',
        public int $maxFiles = 20,
    ) {
    }

    public function adminEnabled(): bool
    {
        return $this->adminPasswordHash !== '';
    }

    public static function load(string $root): self
    {
        Dotenv::createImmutable($root)->safeLoad();

        $env = static function (string $key, string $default = ''): string {
            $value = $_ENV[$key] ?? getenv($key);
            return ($value === false || $value === null || $value === '') ? $default : (string) $value;
        };
        $path = static fn(string $p): string =>
            (str_starts_with($p, '/') || preg_match('~^[A-Za-z]:[\\\\/]~', $p)) ? $p : $root . '/' . $p;

        $maxExpiry = max(1, (int) $env('MAX_EXPIRY_HOURS', '720')) * 3600;

        return new self(
            debug: filter_var($env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
            appUrl: rtrim($env('APP_URL'), '/'),
            maxFileSize: (int) (max(0.01, (float) $env('MAX_FILE_SIZE_MB', '100')) * 1048576),
            defaultExpiry: min($maxExpiry, max(1, (int) $env('DEFAULT_EXPIRY_HOURS', '24')) * 3600),
            maxExpiry: $maxExpiry,
            codeLength: min(32, max(3, (int) $env('CODE_LENGTH', '5'))),
            dbPath: $path($env('DB_PATH', 'storage/filedrop.sqlite')),
            storagePath: rtrim($path($env('STORAGE_PATH', 'storage')), '/\\'),
            cleanupProbability: min(1.0, max(0.0, (float) $env('CLEANUP_PROBABILITY', '0.02'))),
            adminPasswordHash: $env('ADMIN_PASSWORD_HASH'),
            maxFiles: min(100, max(1, (int) $env('MAX_FILES', '20'))),
        );
    }
}