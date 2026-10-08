<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$removed = FileDrop\App::boot(dirname(__DIR__))->files->purgeExpired();
echo sprintf("[%s] File scaduti rimossi: %d\n", date('c'), $removed);