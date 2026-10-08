<?php
declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($path !== '/' && is_file(__DIR__ . $path)) {
        return false;
    }
}

require __DIR__ . '/../vendor/autoload.php';
FileDrop\App::boot(dirname(__DIR__))->run();


