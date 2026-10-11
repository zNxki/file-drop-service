<?php
declare(strict_types=1);

namespace FileDrop\Support;

use PDO;

final class Database
{
    private const SCHEMA_VERSION = 1;
    private const LOCK_KEY = 727274;

    public static function connect(Config $config): PDO
    {
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $config->dbHost, $config->dbPort, $config->dbName);
        $pdo = new PDO($dsn, $config->dbUser, $config->dbPassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        if (!self::upToDate($pdo)) {
            self::migrate($pdo);
        }

        return $pdo;
    }

    private static function upToDate(PDO $pdo): bool
    {
        if (!$pdo->query("SELECT to_regclass('schema_version') IS NOT NULL")->fetchColumn()) {
            return false;
        }

        return (int) $pdo->query('SELECT COALESCE(MAX(version), 0) FROM schema_version')->fetchColumn() >= self::SCHEMA_VERSION;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->beginTransaction();
        try {
            $pdo->exec('SELECT pg_advisory_xact_lock(' . self::LOCK_KEY . ')');
            $pdo->exec('CREATE TABLE IF NOT EXISTS schema_version (version INTEGER NOT NULL)');

            if ((int) $pdo->query('SELECT COALESCE(MAX(version), 0) FROM schema_version')->fetchColumn() < self::SCHEMA_VERSION) {
                $pdo->exec(self::schema());
                $pdo->exec('DELETE FROM schema_version');
                $pdo->exec('INSERT INTO schema_version (version) VALUES (' . self::SCHEMA_VERSION . ')');
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function schema(): string
    {
        return <<<'SQL'
            CREATE TABLE IF NOT EXISTS files (
                code              TEXT PRIMARY KEY,
                original_name     TEXT NOT NULL,
                stored_name       TEXT NOT NULL UNIQUE,
                mime_type         TEXT NOT NULL,
                size              BIGINT NOT NULL,
                password_hash     TEXT,
                delete_token_hash TEXT NOT NULL,
                downloads         BIGINT NOT NULL DEFAULT 0,
                max_downloads     BIGINT,
                created_at        BIGINT NOT NULL,
                expires_at        BIGINT NOT NULL,
                encrypted         INTEGER NOT NULL DEFAULT 0,
                item_count        INTEGER NOT NULL DEFAULT 1,
                uploader          TEXT
            );
            CREATE INDEX IF NOT EXISTS idx_files_expires ON files (expires_at);
            CREATE INDEX IF NOT EXISTS idx_files_uploader ON files (uploader, expires_at);

            CREATE TABLE IF NOT EXISTS file_items (
                id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                code          TEXT NOT NULL,
                position      INTEGER NOT NULL,
                original_name TEXT NOT NULL,
                stored_name   TEXT NOT NULL UNIQUE,
                mime_type     TEXT NOT NULL,
                size          BIGINT NOT NULL,
                crc32         BIGINT NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_file_items_code ON file_items (code, position);

            CREATE TABLE IF NOT EXISTS download_log (
                id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                code          TEXT NOT NULL,
                original_name TEXT NOT NULL,
                size          BIGINT NOT NULL,
                downloaded_at BIGINT NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_download_log_time ON download_log (downloaded_at);
            CREATE INDEX IF NOT EXISTS idx_download_log_code ON download_log (code);

            CREATE TABLE IF NOT EXISTS login_attempts (
                ip           TEXT NOT NULL,
                attempted_at BIGINT NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_login_attempts ON login_attempts (ip, attempted_at);

            CREATE TABLE IF NOT EXISTS rate_limits (
                bucket       TEXT NOT NULL,
                client       TEXT NOT NULL,
                window_start BIGINT NOT NULL,
                hits         BIGINT NOT NULL,
                PRIMARY KEY (bucket, client, window_start)
            );
            CREATE INDEX IF NOT EXISTS idx_rate_limits_window ON rate_limits (window_start);

            CREATE TABLE IF NOT EXISTS upload_log (
                id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                client      TEXT NOT NULL,
                size        BIGINT NOT NULL,
                uploaded_at BIGINT NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_upload_log ON upload_log (client, uploaded_at);

            CREATE TABLE IF NOT EXISTS ip_blocks (
                id         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                cidr       TEXT NOT NULL UNIQUE,
                reason     TEXT NOT NULL DEFAULT '',
                created_at BIGINT NOT NULL,
                expires_at BIGINT
            );

            CREATE TABLE IF NOT EXISTS limit_events (
                client  TEXT NOT NULL,
                kind    TEXT NOT NULL,
                hour    BIGINT NOT NULL,
                hits    BIGINT NOT NULL,
                last_at BIGINT NOT NULL,
                PRIMARY KEY (client, kind, hour)
            );
            CREATE INDEX IF NOT EXISTS idx_limit_events_time ON limit_events (last_at);

            CREATE TABLE IF NOT EXISTS users (
                id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                username      TEXT NOT NULL,
                password_hash TEXT NOT NULL,
                is_admin      BOOLEAN NOT NULL DEFAULT FALSE,
                active        BOOLEAN NOT NULL DEFAULT TRUE,
                permissions   TEXT NOT NULL DEFAULT '[]',
                totp_secret   TEXT,
                totp_step     BIGINT NOT NULL DEFAULT 0,
                created_at    BIGINT NOT NULL,
                last_login_at BIGINT
            );
            CREATE UNIQUE INDEX IF NOT EXISTS idx_users_username ON users (LOWER(username));
            CREATE UNIQUE INDEX IF NOT EXISTS idx_users_single_admin ON users (is_admin) WHERE is_admin;
        SQL;
    }
}