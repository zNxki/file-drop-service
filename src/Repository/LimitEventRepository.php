<?php
declare(strict_types=1);

namespace FileDrop\Repository;

use FileDrop\Support\IpRange;
use PDO;

final class LimitEventRepository
{
    public const RETENTION = 7 * 86400;
    private const PRUNE_PROBABILITY = 0.02;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function record(string $clientKey, string $kind, ?int $now = null): void
    {
        if ($clientKey === '') {
            return;
        }
        $now ??= time();
        $hour = intdiv($now, 3600) * 3600;

        try {
            $this->pdo->prepare(
                'INSERT INTO limit_events (client, kind, hour, hits, last_at) VALUES (?, ?, ?, 1, ?)
                 ON CONFLICT (client, kind, hour) DO UPDATE SET hits = limit_events.hits + 1, last_at = excluded.last_at'
            )->execute([IpRange::fromClientKey($clientKey), $kind, $hour, $now]);

            if (mt_rand() / mt_getrandmax() < self::PRUNE_PROBABILITY) {
                $this->pdo->prepare('DELETE FROM limit_events WHERE hour < ?')->execute([$now - self::RETENTION]);
            }
        } catch (\PDOException $e) {
            error_log('[limit-events] ' . $e->getMessage());
        }
    }

    public function recent(int $since, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT client, kind, SUM(hits) AS hits, MAX(last_at) AS last_at FROM limit_events
             WHERE last_at >= ? GROUP BY client, kind'
        );
        $stmt->execute([$since]);

        $clients = [];
        foreach ($stmt->fetchAll() as $row) {
            $c = &$clients[$row['client']];
            $c ??= ['client' => (string) $row['client'], 'hits' => 0, 'last_at' => 0, 'kinds' => []];
            $c['hits'] += (int) $row['hits'];
            $c['last_at'] = max($c['last_at'], (int) $row['last_at']);
            $c['kinds'][(string) $row['kind']] = (int) $row['hits'];
            unset($c);
        }

        usort($clients, static fn(array $a, array $b): int => [$b['hits'], $b['last_at']] <=> [$a['hits'], $a['last_at']]);

        return array_slice(array_values($clients), 0, $limit);
    }
}