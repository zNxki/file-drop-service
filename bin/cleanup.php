<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = FileDrop\App::boot(dirname(__DIR__));
$removed = $app->files->purgeExpired();
$stale = $app->chunks->purgeStale();
echo sprintf("[%s] File scaduti rimossi: %d, caricamenti abbandonati rimossi: %d\n", date('c'), $removed, $stale);