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
        public string $dbHost,
        public int $dbPort,
        public string $dbName,
        public string $dbUser,
        public string $dbPassword,
        public string $storagePath,
        public float $cleanupProbability,
        public int $maxFiles = 20,
        public bool $rateLimitEnabled = true,
        public int $rateLimitWindow = 60,
        public int $rateLimitRequests = 120,
        public int $rateLimitUploads = 20,
        public int $rateLimitDownloads = 30,
        public int $rateLimitChunks = 600,
        public int $quotaMaxFiles = 50,
        public int $quotaStorage = 1073741824,
        public int $quotaDaily = 5368709120,
        public array $trustedProxies = [],
        public bool $clamavEnabled = false,
        public string $clamavHost = '127.0.0.1',
        public int $clamavPort = 3310,
        public int $clamavTimeout = 120,
        public bool $clamavRejectOnError = true,
    ) {
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
            dbHost: $env('DB_HOST', 'localhost'),
            dbPort: min(65535, max(1, (int) $env('DB_PORT', '5432'))),
            dbName: $env('DB_NAME', 'filedrop'),
            dbUser: $env('DB_USER', 'filedrop'),
            dbPassword: $env('DB_PASSWORD'),
            storagePath: rtrim($path($env('STORAGE_PATH', 'storage')), '/\\'),
            cleanupProbability: min(1.0, max(0.0, (float) $env('CLEANUP_PROBABILITY', '0.02'))),
            maxFiles: min(100, max(1, (int) $env('MAX_FILES', '20'))),
            rateLimitEnabled: filter_var($env('RATE_LIMIT_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),
            rateLimitWindow: min(86400, max(1, (int) $env('RATE_LIMIT_WINDOW', '60'))),
            rateLimitRequests: max(0, (int) $env('RATE_LIMIT_REQUESTS', '120')),
            rateLimitUploads: max(0, (int) $env('RATE_LIMIT_UPLOADS', '20')),
            rateLimitDownloads: max(0, (int) $env('RATE_LIMIT_DOWNLOADS', '30')),
            rateLimitChunks: max(0, (int) $env('RATE_LIMIT_CHUNKS', '600')),
            quotaMaxFiles: max(0, (int) $env('QUOTA_IP_MAX_FILES', '50')),
            quotaStorage: (int) (max(0.0, (float) $env('QUOTA_IP_STORAGE_MB', '1024')) * 1048576),
            quotaDaily: (int) (max(0.0, (float) $env('QUOTA_IP_DAILY_MB', '5120')) * 1048576),
            trustedProxies: array_values(array_filter(array_map('trim', explode(',', $env('TRUSTED_PROXIES'))), static fn(string $p): bool => $p !== '')),
            clamavEnabled: filter_var($env('CLAMAV_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN),
            clamavHost: $env('CLAMAV_HOST', '127.0.0.1'),
            clamavPort: min(65535, max(1, (int) $env('CLAMAV_PORT', '3310'))),
            clamavTimeout: min(3600, max(1, (int) $env('CLAMAV_TIMEOUT', '120'))),
            clamavRejectOnError: strtolower($env('CLAMAV_ON_ERROR', 'reject')) !== 'allow',
        );
    }
}