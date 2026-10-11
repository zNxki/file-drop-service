<?php
declare(strict_types=1);

namespace FileDrop\Support;

use FileDrop\Exception\HttpException;
use FileDrop\Http\Request;
use FileDrop\Repository\LimitEventRepository;
use PDO;

final class RateLimiter
{
    private const PRUNE_PROBABILITY = 0.01;

    public function __construct(
        private readonly PDO $pdo,
        private readonly Config $config,
        private readonly ?LimitEventRepository $events = null,
    ) {
    }

    public function check(Request $request): void
    {
        if (!$this->config->rateLimitEnabled) {
            return;
        }

        [$bucket, $limit] = $this->bucket($request);
        if ($limit <= 0) {
            return;
        }

        $window = $this->config->rateLimitWindow;
        $now = time();
        $start = intdiv($now, $window) * $window;

        try {
            $hits = $this->hit($bucket, $request->clientKey(), $start);
            if (mt_rand() / mt_getrandmax() < self::PRUNE_PROBABILITY) {
                $this->prune($now);
            }
        } catch (\PDOException $e) {
            error_log('[rate-limit] ' . $e->getMessage());
            return;
        }

        if ($hits > $limit) {
            $this->events?->record($request->clientKey(), 'rate:' . $bucket, $now);
            $retry = max(1, $start + $window - $now);
            throw new HttpException(
                429,
                I18n::plural('err.rate', $retry),
                ['Retry-After' => (string) $retry]
            );
        }
    }

    public function prune(int $now): void
    {
        $this->pdo->prepare('DELETE FROM rate_limits WHERE window_start < ?')
            ->execute([$now - $this->config->rateLimitWindow * 2]);
    }

    private function bucket(Request $request): array
    {
        $config = $this->config;

        if ($request->method === 'PUT' && preg_match('~^/api/uploads/[A-Za-z0-9]+$~', $request->path) === 1) {
            return ['chunks', $config->rateLimitChunks];
        }
        if ($request->method === 'POST' && in_array($request->path, ['/api/files', '/api/uploads'], true)) {
            return ['uploads', $config->rateLimitUploads];
        }
        if (in_array($request->method, ['GET', 'POST'], true) && str_ends_with($request->path, '/download')) {
            return ['downloads', $config->rateLimitDownloads];
        }

        return ['requests', $config->rateLimitRequests];
    }

    private function hit(string $bucket, string $client, int $start): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rate_limits (bucket, client, window_start, hits) VALUES (?, ?, ?, 1)
             ON CONFLICT (bucket, client, window_start) DO UPDATE SET hits = rate_limits.hits + 1
             RETURNING hits'
        );
        $stmt->execute([$bucket, $client, $start]);

        return (int) $stmt->fetchColumn();
    }
}