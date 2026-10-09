<?php
declare(strict_types=1);

namespace FileDrop\Service;

use FileDrop\Exception\HttpException;
use FileDrop\Repository\FileRepository;
use FileDrop\Support\Config;
use FileDrop\Model\StoredFile;

final class FileService
{
    public const MAX_DOWNLOADS = 1000;

    public function __construct(
        private readonly Config $config,
        private readonly FileRepository $repository,
        private readonly CodeGenerator $codes,
    ) {
    }

    public function upload(?array $upload, ?int $ttl, ?string $password, ?int $maxDownloads = null): array
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

        if ($upload === null || !isset($upload['error']) || is_array($upload['error'])) {
            throw new HttpException(400, 'Nessun file ricevuto: invia un solo file nel campo "file".');
        }

        $limit = $this->formatBytes($this->config->maxFileSize);
        match ($upload['error']) {
            UPLOAD_ERR_OK => null,
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => throw new HttpException(413, "Il file supera il limite consentito ($limit)."),
            UPLOAD_ERR_NO_FILE => throw new HttpException(400, 'Nessun file ricevuto.'),
            default => throw new HttpException(500, 'Caricamento non riuscito, riprova.'),
        };

        $size = (int) $upload['size'];
        if ($size <= 0) {
            throw new HttpException(422, 'Il file è vuoto.');
        }
        if ($size > $this->config->maxFileSize) {
            throw new HttpException(413, "Il file supera il limite consentito ($limit).");
        }

        $dir = $this->config->storagePath . '/uploads';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new HttpException(500, 'Cartella di archiviazione non scrivibile.');
        }
        $storedName = bin2hex(random_bytes(16));
        $target = $dir . '/' . $storedName;
        if (!move_uploaded_file($upload['tmp_name'], $target)) {
            throw new HttpException(500, 'Impossibile salvare il file.');
        }

        @chmod($target, 0640);

        $now = time();
        $deleteToken = bin2hex(random_bytes(16));
        $name = $this->sanitizeName((string) ($upload['name'] ?? ''));

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = $this->codes->generate($this->config->codeLength + intdiv($attempt, 5));
            $file = new StoredFile(
                $code,
                $name,
                $storedName,
                $this->detectMime($target),
                $size,
                $password === null ? null : password_hash($password, PASSWORD_DEFAULT),
                hash('sha256', $deleteToken),
                0,
                $now,
                $now + $ttl,
                $maxDownloads,
            );
            if ($this->repository->insert($file)) {
                return ['file' => $file, 'deleteToken' => $deleteToken];
            }
        }

        @unlink($target);
        throw new HttpException(500, 'Impossibile generare un codice univoco.');
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
            usleep(300_000); // rallenta i tentativi a raffica
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
        return $handle;
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
        $expired = $this->repository->findPurgeable(time());
        foreach ($expired as $file) {
            $this->remove($file);
        }
        return count($expired);
    }

    public function maybePurge(): void
    {
        if (mt_rand() / mt_getrandmax() < $this->config->cleanupProbability) {
            $this->purgeExpired();
        }
    }

    private function openHandle(StoredFile $file)
    {
        $handle = @fopen($this->path($file), 'rb');
        if ($handle === false) {
            throw new HttpException(500, 'Impossibile leggere il file.');
        }
        return $handle;
    }

    private function remove(StoredFile $file): void
    {
        @unlink($this->config->storagePath . '/uploads/' . $file->storedName);
        $this->repository->delete($file->code);
    }

    private function sanitizeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = trim(mb_substr($name, 0, 180));
        return $name === '' || $name === '.' || $name === '..' ? 'file' : $name;
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

    private function formatBytes(int $bytes): string
    {
        return round($bytes / 1048576, 1) . ' MB';
    }
}