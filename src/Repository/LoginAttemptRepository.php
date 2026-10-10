<?php
declare(strict_types=1);

namespace FileDrop\Repository;

use PDO;

final class LoginAttemptRepository
{
    private const KEEP_SECONDS = 86400;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function countSince(string $ip, int $since): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at >= ?');
        $stmt->execute([$ip, $since]);

        return (int) $stmt->fetchColumn();
    }

    public function oldestSince(string $ip, int $since): ?int
    {
        $stmt = $this->pdo->prepare('SELECT MIN(attempted_at) FROM login_attempts WHERE ip = ? AND attempted_at >= ?');
        $stmt->execute([$ip, $since]);
        $value = $stmt->fetchColumn();

        return $value === null || $value === false ? null : (int) $value;
    }

    public function record(string $ip, int $at): void
    {
        $this->pdo->prepare('INSERT INTO login_attempts (ip, attempted_at) VALUES (?, ?)')->execute([$ip, $at]);
        $this->pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([$at - self::KEEP_SECONDS]);
    }

    public function clear(string $ip): void
    {
        $this->pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
    }
}