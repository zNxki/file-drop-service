<?php
declare(strict_types=1);

namespace FileDrop\Repository;

use FileDrop\Model\StoredFile;
use PDO;
use PDOException;

final class FileRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function insert(StoredFile $f): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO files (code, original_name, stored_name, mime_type, size, password_hash, delete_token_hash, downloads, created_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)'
        );
        try {
            $stmt->execute([
                $f->code,
                $f->originalName,
                $f->storedName,
                $f->mimeType,
                $f->size,
                $f->passwordHash,
                $f->deleteTokenHash,
                $f->createdAt,
                $f->expiresAt,
            ]);
            return true;
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 19) { // SQLITE_CONSTRAINT
                return false;
            }
            throw $e;
        }
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
        $this->pdo->prepare('DELETE FROM files WHERE code = ?')->execute([$code]);
    }

    public function incrementDownloads(string $code): void
    {
        $this->pdo->prepare('UPDATE files SET downloads = downloads + 1 WHERE code = ?')->execute([$code]);
    }

    public function findExpired(int $now): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM files WHERE expires_at <= ?');
        $stmt->execute([$now]);
        return array_map(StoredFile::fromRow(...), $stmt->fetchAll());
    }
}