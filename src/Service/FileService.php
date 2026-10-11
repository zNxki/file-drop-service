<?php
declare(strict_types=1);

namespace FileDrop\Service;

use FileDrop\Exception\HttpException;
use FileDrop\Repository\FileRepository;
use FileDrop\Repository\StatsRepository;
use FileDrop\Support\Config;
use FileDrop\Model\StoredFile;
use FileDrop\Model\StoredItem;
use FileDrop\Support\ZipStream;

final class FileService
{
    public const MAX_DOWNLOADS = 1000;
    public const LOG_RETENTION_DAYS = 90;
    public const ENCRYPTION_MAGIC = 'FDE1';

    public function __construct(
        private readonly Config $config,
        private readonly FileRepository $repository,
        private readonly CodeGenerator $codes,
        private readonly StatsRepository $stats,
        private readonly QuotaService $quotas,
        private readonly ?VirusScanner $scanner = null,
    ) {
    }

    public function maxFiles(): int
    {
        $php = (int) ini_get('max_file_uploads');
        return $php > 0 ? max(1, min($this->config->maxFiles, $php - 1)) : $this->config->maxFiles;
    }

    public function sizeLimit(int $count, bool $encrypted): int
    {
        $maxSize = $this->config->maxFileSize;
        if ($count > 1) {
            return min($maxSize, ZipStream::MAX_BYTES);
        }
        return $encrypted ? $maxSize + intdiv($maxSize, 65536) + 4096 : $maxSize;
    }

    public function upload(array $uploads, ?int $ttl, ?string $password, ?int $maxDownloads = null, bool $encrypted = false, string $client = ''): array
    {
        $ttl ??= $this->config->defaultExpiry;
        if ($ttl < 60 || $ttl > $this->config->maxExpiry) {
            throw new HttpException(422, sprintf(
                'La scadenza deve essere tra 60 e %d secondi.',
                $this->config->maxExpiry
            ));
        }

        $password = ($password === null || $password === '') ? null : $password;
        if ($password !== null && (strlen($password) < 4 || strlen($password) > 200)) {
            throw new HttpException(422, 'La password deve contenere da 4 a 200 caratteri.');
        }

        if ($maxDownloads !== null && ($maxDownloads < 1 || $maxDownloads > self::MAX_DOWNLOADS)) {
            throw new HttpException(422, sprintf('Il numero di download deve essere tra 1 e %d.', self::MAX_DOWNLOADS));
        }

        $uploads = array_values($uploads);
        $count = count($uploads);
        if ($count === 0) {
            throw new HttpException(400, 'Nessun file ricevuto: invia i file nel campo "file" (per più file usa "file[]").');
        }
        if ($count > $this->maxFiles()) {
            throw new HttpException(422, sprintf(
                'Puoi caricare al massimo %d file alla volta (se ne hai inviati di più, alcuni potrebbero essere stati scartati dal server).',
                $this->maxFiles()
            ));
        }
        if ($encrypted && $count > 1) {
            throw new HttpException(422, 'I file cifrati vanno inviati come un unico archivio: crea lo zip nel browser prima di cifrare.');
        }

        $multiple = $count > 1;
        $maxSize = $this->sizeLimit($count, $encrypted);
        $limit = $this->formatBytes($multiple ? $maxSize : $this->config->maxFileSize);

        $sizes = [];
        foreach ($uploads as $upload) {
            $sizes[] = $this->checkUpload($upload, $limit, $multiple);
        }
        $total = array_sum($sizes);
        if ($total > $maxSize) {
            throw new HttpException(413, $multiple
                ? "Il totale dei file supera il limite consentito ($limit)."
                : "Il file supera il limite consentito ($limit).");
        }
        if ($encrypted && !$this->hasEncryptionHeader((string) $uploads[0]['tmp_name'])) {
            throw new HttpException(422, 'Il file non è nel formato cifrato previsto.');
        }
        if ($client !== '') {
            $this->quotas->assert($client, $total);
        }
        if ($this->scanner !== null && !$encrypted) {
            foreach ($uploads as $upload) {
                $this->scanner->scan((string) $upload['tmp_name'], $this->sanitizeName((string) ($upload['name'] ?? '')));
            }
        }

        $dir = $this->config->storagePath . '/uploads';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new HttpException(500, 'Cartella di archiviazione non scrivibile.');
        }

        $moved = [];
        $items = [];
        $taken = [];
        try {
            foreach ($uploads as $i => $upload) {
                $storedName = bin2hex(random_bytes(16));
                $target = $dir . '/' . $storedName;
                $stored = !empty($upload['chunked'])
                    ? @rename((string) $upload['tmp_name'], $target)
                    : move_uploaded_file($upload['tmp_name'], $target);
                if (!$stored) {
                    throw new HttpException(500, 'Impossibile salvare il file.');
                }
                @chmod($target, 0640);
                $moved[] = $target;

                $items[] = new StoredItem(
                    $i,
                    $encrypted ? 'encrypted.bin' : $this->uniqueName($this->sanitizeName((string) ($upload['name'] ?? '')), $taken),
                    $storedName,
                    $encrypted ? 'application/octet-stream' : $this->detectMime($target),
                    $sizes[$i],
                    $multiple ? $this->crc32($target) : 0,
                );
            }
        } catch (\Throwable $e) {
            foreach ($moved as $path) {
                @unlink($path);
            }
            throw $e;
        }

        $now = time();
        $deleteToken = bin2hex(random_bytes(16));
        $bundleStoredName = 'bundle-' . bin2hex(random_bytes(12));

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = $this->codes->generate($this->config->codeLength + intdiv($attempt, 5));
            $first = $items[0];
            $file = new StoredFile(
                $code,
                $multiple ? "file-drop-$code.zip" : $first->originalName,
                $multiple ? $bundleStoredName : $first->storedName,
                $multiple ? 'application/zip' : $first->mimeType,
                $total,
                $password === null ? null : password_hash($password, PASSWORD_DEFAULT),
                hash('sha256', $deleteToken),
                0,
                $now,
                $now + $ttl,
                $maxDownloads,
                $encrypted,
                $count,
            );
            if ($this->repository->insert($file, $multiple ? $items : [], $client === '' ? null : $this->quotas->key($client))) {
                if ($client !== '') {
                    $this->quotas->record($client, $total);
                }
                return ['file' => $file, 'deleteToken' => $deleteToken, 'items' => $multiple ? $items : []];
            }
        }

        foreach ($moved as $path) {
            @unlink($path);
        }
        throw new HttpException(500, 'Impossibile generare un codice univoco.');
    }

    public function assertQuota(string $client, int $bytes): void
    {
        $this->quotas->assert($client, $bytes);
    }

    public function items(StoredFile $file): array
    {
        return $file->isBundle() ? $this->repository->items($file->code) : [];
    }

    public function get(string $code): StoredFile
    {
        $file = $this->repository->find($code);
        if ($file === null) {
            throw new HttpException(404, 'File non trovato. Controlla il codice.');
        }
        if ($file->isExpired()) {
            $this->remove($file);
            throw new HttpException(410, 'Il file è scaduto ed è stato eliminato.');
        }
        if ($file->isExhausted()) {
            throw new HttpException(410, 'Il file ha raggiunto il numero massimo di download.');
        }
        return $file;
    }

    public function checkPassword(StoredFile $file, ?string $password): void
    {
        if (!$file->isProtected()) {
            return;
        }
        if ($password === null || $password === '') {
            throw new HttpException(401, 'Questo file è protetto da password.');
        }
        if (!password_verify($password, (string) $file->passwordHash)) {
            usleep(300_000);
            throw new HttpException(403, 'Password errata.');
        }
    }

    public function path(StoredFile $file): string
    {
        $path = $this->config->storagePath . '/uploads/' . $file->storedName;
        if (!is_file($path)) {
            $this->repository->delete($file->code);
            throw new HttpException(404, 'Il file non è più disponibile.');
        }
        return $path;
    }

    public function openDownload(StoredFile $file)
    {
        $handle = $this->openHandle($file);
        if (!$this->repository->claimDownload($file->code)) {
            fclose($handle);
            throw new HttpException(410, 'Il file ha raggiunto il numero massimo di download.');
        }
        $this->logDownload($file);
        return $handle;
    }

    public function openBundleDownload(StoredFile $file): ZipStream
    {
        $items = $this->repository->items($file->code);
        if ($items === [] || count($items) !== $file->itemCount) {
            $this->remove($file);
            throw new HttpException(404, 'Il file non è più disponibile.');
        }

        $entries = [];
        $close = static function () use (&$entries): void {
            foreach ($entries as $entry) {
                fclose($entry['handle']);
            }
        };

        foreach ($items as $item) {
            $path = $this->config->storagePath . '/uploads/' . $item->storedName;
            $handle = is_file($path) ? @fopen($path, 'rb') : false;
            if ($handle === false) {
                $close();
                $this->remove($file);
                throw new HttpException(404, 'Il file non è più disponibile.');
            }
            $entries[] = ['name' => $item->originalName, 'handle' => $handle, 'size' => $item->size, 'crc' => $item->crc32];
            if ((fstat($handle)['size'] ?? -1) !== $item->size) {
                $close();
                throw new HttpException(500, 'Impossibile leggere il file.');
            }
        }

        try {
            $zip = new ZipStream($entries, $file->createdAt);
        } catch (\LengthException $e) {
            $close();
            throw new HttpException(500, 'Impossibile creare l\'archivio.');
        }

        if (!$this->repository->claimDownload($file->code)) {
            $close();
            throw new HttpException(410, 'Il file ha raggiunto il numero massimo di download.');
        }
        $this->logDownload($file);

        return $zip;
    }

    public function completeDownload(string $code): void
    {
        $file = $this->repository->find($code);
        if ($file !== null && $file->isExhausted()) {
            $this->remove($file);
        }
    }

    public function openPreview(StoredFile $file)
    {
        return $this->openHandle($file);
    }

    public function delete(string $code, ?string $token): void
    {
        $file = $this->get($code);
        if ($token === null || !hash_equals($file->deleteTokenHash, hash('sha256', $token))) {
            throw new HttpException(403, 'Token di eliminazione non valido.');
        }
        $this->remove($file);
    }

    public function purgeExpired(): int
    {
        $now = time();
        $expired = $this->repository->findPurgeable($now);
        foreach ($expired as $file) {
            $this->remove($file);
        }
        $this->stats->pruneLog($now - self::LOG_RETENTION_DAYS * 86400);
        $this->quotas->prune($now);
        return count($expired);
    }

    public function adminList(string $query, string $sort, bool $ascending, int $page, int $perPage): array
    {
        $total = $this->repository->count($query);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        return [
            'files' => $this->repository->search($query, $sort, $ascending, $perPage, ($page - 1) * $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    public function adminDelete(string $code): StoredFile
    {
        $file = $this->repository->find($code);
        if ($file === null) {
            throw new HttpException(404, 'File non trovato: forse è già stato eliminato.');
        }
        $this->remove($file);
        return $file;
    }

    public function storageUsage(): array
    {
        $dir = $this->config->storagePath . '/uploads';
        $files = 0;
        $bytes = 0;
        if (is_dir($dir)) {
            foreach (new \FilesystemIterator($dir, \FilesystemIterator::SKIP_DOTS) as $entry) {
                if ($entry->isFile()) {
                    $files++;
                    $bytes += $entry->getSize();
                }
            }
        }

        $free = @disk_free_space($this->config->storagePath);
        $total = @disk_total_space($this->config->storagePath);

        return [
            'files' => $files,
            'bytes' => $bytes,
            'free' => $free === false ? null : (int) $free,
            'total' => $total === false ? null : (int) $total,
        ];
    }

    public function maybePurge(): void
    {
        if (mt_rand() / mt_getrandmax() < $this->config->cleanupProbability) {
            $this->purgeExpired();
        }
    }

    private function logDownload(StoredFile $file): void
    {
        try {
            $this->stats->recordDownload($file);
        } catch (\Throwable $e) {
            error_log('Registrazione download non riuscita: ' . $e->getMessage());
        }
    }

    private function openHandle(StoredFile $file)
    {
        if ($file->isBundle()) {
            throw new HttpException(404, 'Anteprima non disponibile per questo file.');
        }
        $handle = @fopen($this->path($file), 'rb');
        if ($handle === false) {
            throw new HttpException(500, 'Impossibile leggere il file.');
        }
        return $handle;
    }

    private function remove(StoredFile $file): void
    {
        if ($file->isBundle()) {
            foreach ($this->repository->items($file->code) as $item) {
                @unlink($this->config->storagePath . '/uploads/' . $item->storedName);
            }
        } else {
            @unlink($this->config->storagePath . '/uploads/' . $file->storedName);
        }
        $this->repository->delete($file->code);
    }

    private function checkUpload(array $upload, string $limit, bool $named): int
    {
        if (!isset($upload['error']) || is_array($upload['error'])) {
            throw new HttpException(400, 'Nessun file ricevuto: invia i file nel campo "file" (per più file usa "file[]").');
        }

        $label = $named ? ' «' . $this->sanitizeName((string) ($upload['name'] ?? '')) . '»' : '';
        match ($upload['error']) {
            UPLOAD_ERR_OK => null,
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => throw new HttpException(413, "Il file$label supera il limite consentito ($limit)."),
            UPLOAD_ERR_NO_FILE => throw new HttpException(400, 'Nessun file ricevuto.'),
            default => throw new HttpException(500, 'Caricamento non riuscito, riprova.'),
        };

        $size = (int) $upload['size'];
        if ($size <= 0) {
            throw new HttpException(422, $named ? "Il file$label è vuoto." : 'Il file è vuoto.');
        }
        return $size;
    }

    private function uniqueName(string $name, array &$taken): string
    {
        $dot = strrpos($name, '.');
        $base = ($dot === false || $dot === 0) ? $name : substr($name, 0, $dot);
        $ext = ($dot === false || $dot === 0) ? '' : substr($name, $dot);

        $candidate = $name;
        for ($n = 2; isset($taken[mb_strtolower($candidate)]); $n++) {
            $candidate = "$base ($n)$ext";
        }
        $taken[mb_strtolower($candidate)] = true;

        return $candidate;
    }

    private function crc32(string $path): int
    {
        $hash = hash_file('crc32b', $path);
        if ($hash === false) {
            throw new HttpException(500, 'Impossibile leggere il file.');
        }
        return (int) hexdec($hash);
    }

    private function sanitizeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = trim(mb_substr($name, 0, 180));
        return $name === '' || $name === '.' || $name === '..' ? 'file' : $name;
    }

    private function hasEncryptionHeader(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $head = fread($handle, strlen(self::ENCRYPTION_MAGIC));
        fclose($handle);

        return $head === self::ENCRYPTION_MAGIC;
    }

    private function detectMime(string $path): string
    {
        if (function_exists('finfo_open') && ($finfo = finfo_open(FILEINFO_MIME_TYPE)) !== false) {
            $mime = finfo_file($finfo, $path);
            finfo_close($finfo);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }
        return 'application/octet-stream';
    }

    public function formatBytes(int $bytes): string
    {
        return round($bytes / 1048576, 1) . ' MB';
    }
}