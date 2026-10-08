<?php
declare(strict_types=1);

namespace FileDrop\Model;

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

    public function toArray(string $baseUrl): array
    {
        return [
            'code' => $this->code,
            'name' => $this->originalName,
            'size' => $this->size,
            'mime_type' => $this->mimeType,
            'protected' => $this->isProtected(),
            'downloads' => $this->downloads,
            'created_at' => date(DATE_ATOM, $this->createdAt),
            'expires_at' => date(DATE_ATOM, $this->expiresAt),
            'url' => $baseUrl . '/d/' . $this->code,
            'download_url' => $baseUrl . '/api/files/' . $this->code . '/download',
        ];
    }
}