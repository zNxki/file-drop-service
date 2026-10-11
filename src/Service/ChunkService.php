<?php
declare(strict_types=1);

namespace FileDrop\Service;

use FileDrop\Exception\HttpException;
use FileDrop\Support\Config;
use FileDrop\Support\I18n;

final class ChunkService
{
    public const CHUNK_SIZE = 8388608;
    public const MAX_CHUNK = 33554432;
    public const SESSION_TTL = 86400;

    public function __construct(
        private readonly Config $config,
        private readonly FileService $files,
    ) {
    }

    public function init(array $declared, bool $encrypted, string $client = ''): array
    {
        $count = count($declared);
        if ($count === 0) {
            throw new HttpException(400, I18n::t('err.no_files_declared'));
        }
        if ($count > $this->files->maxFiles()) {
            throw new HttpException(422, I18n::t('err.too_many', ['max' => $this->files->maxFiles()]));
        }
        if ($encrypted && $count > 1) {
            throw new HttpException(422, I18n::t('err.encrypted_single'));
        }

        $entries = [];
        $total = 0;
        foreach (array_values($declared) as $item) {
            $name = is_array($item) ? ($item['name'] ?? null) : null;
            $size = is_array($item) ? ($item['size'] ?? null) : null;
            if (!is_string($name) || !is_int($size) || $size <= 0) {
                throw new HttpException(422, I18n::t('err.file_invalid'));
            }
            $entries[] = ['name' => $name, 'size' => $size];
            $total += $size;
        }

        $limit = $this->files->sizeLimit($count, $encrypted);
        if ($total > $limit) {
            throw new HttpException(413, I18n::t($count > 1 ? 'err.total_over' : 'err.file_over', ['limit' => $this->files->formatBytes($limit)]));
        }

        if ($client !== '') {
            $this->files->assertQuota($client, $total);
        }

        $id = bin2hex(random_bytes(16));
        $dir = $this->dir($id);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new HttpException(500, I18n::t('err.storage_unwritable'));
        }
        file_put_contents($dir . '/meta.json', json_encode([
            'created' => time(),
            'encrypted' => $encrypted,
            'files' => $entries,
        ], JSON_THROW_ON_ERROR), LOCK_EX);

        return $this->status($id);
    }

    public function status(string $id): array
    {
        $meta = $this->meta($id);
        $files = [];
        foreach ($meta['files'] as $i => $entry) {
            $files[] = [
                'name' => $entry['name'],
                'size' => $entry['size'],
                'received' => $this->received($id, $i),
            ];
        }

        return ['id' => $id, 'chunk_size' => self::CHUNK_SIZE, 'encrypted' => $meta['encrypted'], 'files' => $files];
    }

    public function append(string $id, int $index, int $offset, $stream, int $length): array
    {
        $meta = $this->meta($id);
        $entry = $meta['files'][$index] ?? null;
        if ($entry === null) {
            throw new HttpException(404, I18n::t('err.not_in_upload'));
        }
        if ($length <= 0 || $length > self::MAX_CHUNK) {
            throw new HttpException(413, I18n::t('err.chunk_size', ['max' => $this->files->formatBytes(self::MAX_CHUNK)]));
        }
        if ($offset < 0 || $offset + $length > $entry['size']) {
            throw new HttpException(422, I18n::t('err.chunk_over'));
        }

        $handle = @fopen($this->part($id, $index), 'c+b');
        if ($handle === false) {
            throw new HttpException(500, I18n::t('err.chunk_save'));
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new HttpException(500, I18n::t('err.chunk_save'));
            }
            $current = (int) (fstat($handle)['size'] ?? 0);
            if ($offset !== $current) {
                throw new HttpException(409, I18n::t('err.offset', ['received' => $current]));
            }

            fseek($handle, $offset);
            $written = stream_copy_to_stream($stream, $handle, $length);
            fflush($handle);
            if ($written !== $length) {
                throw new HttpException(400, I18n::t('err.chunk_incomplete'));
            }
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return ['received' => $offset + $length, 'complete' => $offset + $length === $entry['size']];
    }

    public function take(string $id): array
    {
        $meta = $this->meta($id);
        $uploads = [];
        foreach ($meta['files'] as $i => $entry) {
            clearstatcache(true, $this->part($id, $i));
            if ($this->received($id, $i) !== $entry['size']) {
                throw new HttpException(409, I18n::t('err.upload_incomplete'));
            }
            $uploads[] = [
                'name' => $entry['name'],
                'type' => '',
                'tmp_name' => $this->part($id, $i),
                'error' => UPLOAD_ERR_OK,
                'size' => $entry['size'],
                'chunked' => true,
            ];
        }

        return ['uploads' => $uploads, 'encrypted' => $meta['encrypted']];
    }

    public function discard(string $id): void
    {
        $dir = $this->dir($id);
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($dir);
    }

    public function exists(string $id): bool
    {
        return $this->validId($id) && is_file($this->dir($id) . '/meta.json');
    }

    public function purgeStale(): int
    {
        $root = $this->config->storagePath . '/chunks';
        $removed = 0;
        if (!is_dir($root)) {
            return 0;
        }
        $limit = time() - self::SESSION_TTL;
        foreach (new \FilesystemIterator($root, \FilesystemIterator::SKIP_DOTS) as $entry) {
            if ($entry->isDir() && $this->latestChange($entry->getPathname()) < $limit) {
                $this->discard($entry->getFilename());
                $removed++;
            }
        }
        return $removed;
    }

    public function maybePurge(): void
    {
        if (mt_rand() / mt_getrandmax() < $this->config->cleanupProbability) {
            $this->purgeStale();
        }
    }

    private function latestChange(string $dir): int
    {
        $latest = (int) @filemtime($dir);
        foreach (glob($dir . '/*') ?: [] as $path) {
            $latest = max($latest, (int) @filemtime($path));
        }
        return $latest;
    }

    private function meta(string $id): array
    {
        if (!$this->exists($id)) {
            throw new HttpException(404, I18n::t('err.upload_missing'));
        }
        $meta = json_decode((string) file_get_contents($this->dir($id) . '/meta.json'), true);
        if (!is_array($meta) || !isset($meta['files']) || !is_array($meta['files'])) {
            throw new HttpException(404, I18n::t('err.upload_missing'));
        }
        return $meta;
    }

    private function received(string $id, int $index): int
    {
        $path = $this->part($id, $index);
        clearstatcache(true, $path);
        return is_file($path) ? (int) filesize($path) : 0;
    }

    private function validId(string $id): bool
    {
        return preg_match('/^[a-f0-9]{32}$/', $id) === 1;
    }

    private function dir(string $id): string
    {
        return $this->config->storagePath . '/chunks/' . $id;
    }

    private function part(string $id, int $index): string
    {
        return $this->dir($id) . '/' . $index . '.part';
    }
}
