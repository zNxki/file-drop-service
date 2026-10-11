<?php
declare(strict_types=1);

namespace FileDrop\Repository;

use PDO;

final class QuotaRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function active(string $uploader, int $now): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS files, COALESCE(SUM(size), 0) AS bytes FROM files WHERE uploader = ? AND expires_at > ?'
        );
        $stmt->execute([$uploader, $now]);
        $row = $stmt->fetch();

        return ['files' => (int) $row['files'], 'bytes' => (int) $row['bytes']];
    }

    public function uploadedSince(string $client, int $since): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(size), 0) FROM upload_log WHERE client = ? AND uploaded_at >= ?');
        $stmt->execute([$client, $since]);

        return (int) $stmt->fetchColumn();
    }

    public function record(string $client, int $size, int $at): void
    {
        $this->pdo->prepare('INSERT INTO upload_log (client, size, uploaded_at) VALUES (?, ?, ?)')->execute([$client, $size, $at]);
    }

    public function prune(int $before): void
    {
        $this->pdo->prepare('DELETE FROM upload_log WHERE uploaded_at < ?')->execute([$before]);
    }
}
