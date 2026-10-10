<?php
declare(strict_types=1);

namespace FileDrop\Model;

final readonly class StoredItem
{
    public function __construct(
        public int $position,
        public string $originalName,
        public string $storedName,
        public string $mimeType,
        public int $size,
        public int $crc32,
    ) {
    }

    public static function fromRow(array $r): self
    {
        return new self(
            (int) $r['position'],
            $r['original_name'],
            $r['stored_name'],
            $r['mime_type'],
            (int) $r['size'],
            (int) $r['crc32'],
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->originalName,
            'size' => $this->size,
            'mime_type' => $this->mimeType,
        ];
    }
}
