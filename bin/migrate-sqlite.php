<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FileDrop\Support\{Config, Database};

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$source = $argv[1] ?? '';
if ($source === '' || !is_file($source)) {
    fwrite(STDERR, "Uso: php bin/migrate-sqlite.php percorso/filedrop.sqlite\n");
    exit(1);
}
if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "Serve l'estensione pdo_sqlite.\n");
    exit(1);
}

$sqlite = new PDO('sqlite:' . $source, options: [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pg = Database::connect(Config::load(dirname(__DIR__)));

$tables = [
    'files' => [['code', 'original_name', 'stored_name', 'mime_type', 'size', 'password_hash', 'delete_token_hash', 'downloads', 'max_downloads', 'created_at', 'expires_at', 'encrypted', 'item_count', 'uploader'], 'code'],
    'file_items' => [['code', 'position', 'original_name', 'stored_name', 'mime_type', 'size', 'crc32'], 'stored_name'],
    'download_log' => [['code', 'original_name', 'size', 'downloaded_at'], null],
];

$pg->beginTransaction();
try {
    foreach ($tables as $table => [$columns, $conflict]) {
        $available = array_column($sqlite->query("PRAGMA table_info($table)")->fetchAll(), 'name');
        $columns = array_values(array_intersect($columns, $available));
        $insert = $pg->prepare(sprintf(
            'INSERT INTO %s (%s) VALUES (%s)%s',
            $table,
            implode(', ', $columns),
            implode(', ', array_fill(0, count($columns), '?')),
            $conflict === null ? '' : " ON CONFLICT ($conflict) DO NOTHING"
        ));

        $count = 0;
        foreach ($sqlite->query(sprintf('SELECT %s FROM %s', implode(', ', $columns), $table)) as $row) {
            $insert->execute(array_values($row));
            $count += $insert->rowCount();
        }
        echo sprintf("%s: %d righe importate\n", $table, $count);
    }
    $pg->commit();
} catch (Throwable $e) {
    $pg->rollBack();
    fwrite(STDERR, 'Importazione annullata: ' . $e->getMessage() . "\n");
    exit(1);
}
