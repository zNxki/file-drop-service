<?php
declare(strict_types=1);

namespace FileDrop\Support;

use PDO;

final class Database
{
    public static function connect(string $path): PDO
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException("Couldn't create the folder: $dir");
        }

        $pdo = new PDO('sqlite:' . $path, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;');
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS files (
                code              TEXT PRIMARY KEY,
                original_name     TEXT NOT NULL,
                stored_name       TEXT NOT NULL UNIQUE,
                mime_type         TEXT NOT NULL,
                size              INTEGER NOT NULL,
                password_hash     TEXT,
                delete_token_hash TEXT NOT NULL,
                downloads         INTEGER NOT NULL DEFAULT 0,
                created_at        INTEGER NOT NULL,
                expires_at        INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_files_expires ON files (expires_at);

            CREATE TABLE IF NOT EXISTS download_log (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                code          TEXT NOT NULL,
                original_name TEXT NOT NULL,
                size          INTEGER NOT NULL,
                downloaded_at INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_download_log_time ON download_log (downloaded_at);
            CREATE INDEX IF NOT EXISTS idx_download_log_code ON download_log (code);

            CREATE TABLE IF NOT EXISTS login_attempts (
                ip           TEXT NOT NULL,
                attempted_at INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_login_attempts ON login_attempts (ip, attempted_at);

            CREATE TABLE IF NOT EXISTS file_items (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                code          TEXT NOT NULL,
                position      INTEGER NOT NULL,
                original_name TEXT NOT NULL,
                stored_name   TEXT NOT NULL UNIQUE,
                mime_type     TEXT NOT NULL,
                size          INTEGER NOT NULL,
                crc32         INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_file_items_code ON file_items (code, position);
        SQL);

        $columns = array_column($pdo->query('PRAGMA table_info(files)')->fetchAll(), 'name');
        $migrations = [
            'max_downloads' => 'INTEGER',
            'encrypted' => 'INTEGER NOT NULL DEFAULT 0',
            'item_count' => 'INTEGER NOT NULL DEFAULT 1',
        ];
        foreach ($migrations as $column => $definition) {
            if (in_array($column, $columns, true)) {
                continue;
            }
            try {
                $pdo->exec("ALTER TABLE files ADD COLUMN $column $definition");
            } catch (\PDOException $e) {
                if (!str_contains($e->getMessage(), 'duplicate column')) {
                    throw $e;
                }
            }
        }

        return $pdo;
    }
}