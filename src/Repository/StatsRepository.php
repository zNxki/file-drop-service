<?php
declare(strict_types=1);

namespace FileDrop\Repository;

use FileDrop\Model\StoredFile;
use PDO;

final class StatsRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function recordDownload(StoredFile $file, ?int $at = null): void
    {
        $this->pdo->prepare(
            'INSERT INTO download_log (code, original_name, size, downloaded_at) VALUES (?, ?, ?, ?)'
        )->execute([$file->code, $file->originalName, $file->size, $at ?? time()]);
    }

    public function pruneLog(int $before): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM download_log WHERE downloaded_at < ?');
        $stmt->execute([$before]);
        return $stmt->rowCount();
    }

    public function fileTotals(int $now): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS files,
                    COALESCE(SUM(size), 0) AS bytes,
                    COALESCE(SUM(downloads), 0) AS downloads,
                    COALESCE(SUM(CASE WHEN expires_at <= :now1 OR (max_downloads IS NOT NULL AND downloads >= max_downloads)
                                      THEN 1 ELSE 0 END), 0) AS stale,
                    COALESCE(SUM(CASE WHEN expires_at <= :now2 OR (max_downloads IS NOT NULL AND downloads >= max_downloads)
                                      THEN size ELSE 0 END), 0) AS stale_bytes
             FROM files'
        );
        $stmt->execute(['now1' => $now, 'now2' => $now]);
        $row = $stmt->fetch();

        return array_map('intval', $row);
    }

    public function downloadTotals(int $now): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN downloaded_at >= :d1 THEN 1 ELSE 0 END), 0) AS day,
                    COALESCE(SUM(CASE WHEN downloaded_at >= :d7 THEN 1 ELSE 0 END), 0) AS week,
                    COALESCE(SUM(CASE WHEN downloaded_at >= :d30 THEN 1 ELSE 0 END), 0) AS month,
                    MIN(downloaded_at) AS since
             FROM download_log'
        );
        $stmt->execute(['d1' => $now - 86400, 'd7' => $now - 7 * 86400, 'd30' => $now - 30 * 86400]);
        $row = $stmt->fetch();

        return [
            'total' => (int) $row['total'],
            'day' => (int) $row['day'],
            'week' => (int) $row['week'],
            'month' => (int) $row['month'],
            'since' => $row['since'] === null ? null : (int) $row['since'],
        ];
    }

    public function downloadsPerDay(int $days, int $now): array
    {
        $today = intdiv($now, 86400) * 86400;
        $start = $today - ($days - 1) * 86400;

        $series = [];
        for ($t = $start; $t <= $today; $t += 86400) {
            $series[gmdate('Y-m-d', $t)] = 0;
        }

        $stmt = $this->pdo->prepare(
            "SELECT strftime('%Y-%m-%d', downloaded_at, 'unixepoch') AS day, COUNT(*) AS n
             FROM download_log WHERE downloaded_at >= ? GROUP BY day"
        );
        $stmt->execute([$start]);
        foreach ($stmt->fetchAll() as $row) {
            if (isset($series[$row['day']])) {
                $series[$row['day']] = (int) $row['n'];
            }
        }

        return $series;
    }

    public function topFiles(int $since, int $limit): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT code, original_name AS name, MAX(size) AS size, COUNT(*) AS downloads
             FROM download_log WHERE downloaded_at >= :since
             GROUP BY code, original_name
             ORDER BY downloads DESC, MAX(downloaded_at) DESC
             LIMIT :limit'
        );
        $stmt->bindValue('since', $since, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static fn(array $r): array => [
            'code' => (string) $r['code'],
            'name' => (string) $r['name'],
            'size' => (int) $r['size'],
            'downloads' => (int) $r['downloads'],
        ], $stmt->fetchAll());
    }

    public function recentDownloads(int $limit): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT code, original_name AS name, size, downloaded_at AS at
             FROM download_log ORDER BY id DESC LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static fn(array $r): array => [
            'code' => (string) $r['code'],
            'name' => (string) $r['name'],
            'size' => (int) $r['size'],
            'at' => (int) $r['at'],
        ], $stmt->fetchAll());
    }
}