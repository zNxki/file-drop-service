<?php
declare(strict_types=1);

namespace FileDrop\Model;

use FileDrop\Support\Preview;

final readonly class StoredFile
{
    public function __construct(
        public string $code,
        public string $originalName,
        public string $storedName,
        public string $mimeType,
        public int $size,
        public ?string $passwordHash,
        public string $deleteTokenHash,
        public int $downloads,
        public int $createdAt,
        public int $expiresAt,
        public ?int $maxDownloads = null,
        public bool $encrypted = false,
    ) {
    }

    public static function fromRow(array $r): self
    {
        return new self(
            $r['code'],
            $r['original_name'],
            $r['stored_name'],
            $r['mime_type'],
            (int) $r['size'],
            $r['password_hash'],
            $r['delete_token_hash'],
            (int) $r['downloads'],
            (int) $r['created_at'],
            (int) $r['expires_at'],
            $r['max_downloads'] === null ? null : (int) $r['max_downloads'],
            !empty($r['encrypted']),
        );
    }

    public function isProtected(): bool
    {
        return $this->passwordHash !== null;
    }

    public function isExpired(?int $now = null): bool
    {
        return $this->expiresAt <= ($now ?? time());
    }

    public function isExhausted(): bool
    {
        return $this->maxDownloads !== null && $this->downloads >= $this->maxDownloads;
    }

    public function remainingDownloads(): ?int
    {
        return $this->maxDownloads === null ? null : max(0, $this->maxDownloads - $this->downloads);
    }

    public function displayName(): string
    {
        return $this->encrypted ? 'File cifrato' : $this->originalName;
    }

    public function previewKind(): ?string
    {
        if ($this->encrypted || $this->isProtected() || $this->maxDownloads !== null) {
            return null;
        }
        return Preview::kind($this->mimeType);
    }

    public function toArray(string $baseUrl): array
    {
        return [
            'code' => $this->code,
            'name' => $this->originalName,
            'size' => $this->size,
            'mime_type' => $this->mimeType,
            'protected' => $this->isProtected(),
            'encrypted' => $this->encrypted,
            'downloads' => $this->downloads,
            'max_downloads' => $this->maxDownloads,
            'remaining_downloads' => $this->remainingDownloads(),
            'created_at' => date(DATE_ATOM, $this->createdAt),
            'expires_at' => date(DATE_ATOM, $this->expiresAt),
            'url' => $baseUrl . '/d/' . $this->code,
            'download_url' => $baseUrl . '/api/files/' . $this->code . '/download',
            'preview_url' => $this->previewKind() === null ? null : $baseUrl . '/d/' . $this->code . '/preview',
        ];
    }
}