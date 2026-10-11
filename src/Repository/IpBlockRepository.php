<?php
declare(strict_types=1);

namespace FileDrop\Repository;

use PDO;

final class IpBlockRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function activeRanges(int $now): array
    {
        $stmt = $this->pdo->prepare('SELECT cidr FROM ip_blocks WHERE expires_at IS NULL OR expires_at > ?');
        $stmt->execute([$now]);

        return array_column($stmt->fetchAll(), 'cidr');
    }

    public function all(int $now): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, cidr, reason, created_at, expires_at FROM ip_blocks
             WHERE expires_at IS NULL OR expires_at > ? ORDER BY created_at DESC'
        );
        $stmt->execute([$now]);

        return array_map(static fn(array $r): array => [
            'id' => (int) $r['id'],
            'cidr' => (string) $r['cidr'],
            'reason' => (string) $r['reason'],
            'created_at' => (int) $r['created_at'],
            'expires_at' => $r['expires_at'] === null ? null : (int) $r['expires_at'],
        ], $stmt->fetchAll());
    }

    public function add(string $cidr, string $reason, int $now, ?int $expiresAt): void
    {
        $this->pdo->prepare(
            'INSERT INTO ip_blocks (cidr, reason, created_at, expires_at) VALUES (?, ?, ?, ?)
             ON CONFLICT (cidr) DO UPDATE SET reason = excluded.reason, created_at = excluded.created_at, expires_at = excluded.expires_at'
        )->execute([$cidr, $reason, $now, $expiresAt]);
        $this->pdo->prepare('DELETE FROM ip_blocks WHERE expires_at IS NOT NULL AND expires_at <= ?')->execute([$now]);
    }

    public function remove(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM ip_blocks WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }
}
