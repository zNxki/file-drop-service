<?php
declare(strict_types=1);

namespace FileDrop\Repository;

use FileDrop\Model\StoredFile;
use FileDrop\Model\StoredItem;
use PDO;
use PDOException;

final class FileRepository
{
    public const SORTS = [
        'created' => 'created_at',
        'name' => 'original_name COLLATE NOCASE',
        'size' => 'size',
        'downloads' => 'downloads',
        'expires' => 'expires_at',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function insert(StoredFile $f, array $items = []): bool
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                'INSERT INTO files (code, original_name, stored_name, mime_type, size, password_hash, delete_token_hash, downloads, max_downloads, created_at, expires_at, encrypted, item_count)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)'
            )->execute([
                $f->code,
                $f->originalName,
                $f->storedName,
                $f->mimeType,
                $f->size,
                $f->passwordHash,
                $f->deleteTokenHash,
                $f->maxDownloads,
                $f->createdAt,
                $f->expiresAt,
                $f->encrypted ? 1 : 0,
                $f->itemCount,
            ]);

            if ($items !== []) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO file_items (code, position, original_name, stored_name, mime_type, size, crc32)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                foreach ($items as $item) {
                    $stmt->execute([$f->code, $item->position, $item->originalName, $item->storedName, $item->mimeType, $item->size, $item->crc32]);
                }
            }

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            if (($e->errorInfo[1] ?? null) === 19) { // SQLITE_CONSTRAINT
                return false;
            }
            throw $e;
        }
    }

    public function items(string $code): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM file_items WHERE code = ? ORDER BY position');
        $stmt->execute([$code]);
        return array_map(StoredItem::fromRow(...), $stmt->fetchAll());
    }

    public function find(string $code): ?StoredFile
    {
        $stmt = $this->pdo->prepare('SELECT * FROM files WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        return $row ? StoredFile::fromRow($row) : null;
    }

    public function delete(string $code): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM file_items WHERE code = ?')->execute([$code]);
            $this->pdo->prepare('DELETE FROM files WHERE code = ?')->execute([$code]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function claimDownload(string $code): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE files SET downloads = downloads + 1
             WHERE code = ? AND (max_downloads IS NULL OR downloads < max_downloads)'
        );
        $stmt->execute([$code]);
        return $stmt->rowCount() === 1;
    }

    public function search(string $query, string $sort, bool $ascending, int $limit, int $offset): array
    {
        [$where, $params] = $this->searchClause($query);
        $column = self::SORTS[$sort] ?? self::SORTS['created'];
        $direction = $ascending ? 'ASC' : 'DESC';

        $stmt = $this->pdo->prepare(
            "SELECT * FROM files $where ORDER BY $column $direction, code ASC LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $i => $value) {
            $stmt->bindValue($i + 1, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(StoredFile::fromRow(...), $stmt->fetchAll());
    }

    public function count(string $query = ''): int
    {
        [$where, $params] = $this->searchClause($query);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM files $where");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function searchClause(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['', []];
        }
        $like = '%' . strtr($query, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';

        return ["WHERE original_name LIKE ? ESCAPE '!' OR code LIKE ? ESCAPE '!'", [$like, $like]];
    }

    public function findPurgeable(int $now): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM files WHERE expires_at <= ? OR (max_downloads IS NOT NULL AND downloads >= max_downloads)'
        );
        $stmt->execute([$now]);
        return array_map(StoredFile::fromRow(...), $stmt->fetchAll());
    }
}