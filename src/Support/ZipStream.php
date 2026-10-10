<?php
declare(strict_types=1);

namespace FileDrop\Support;

final class ZipStream
{
    public const MAX_BYTES = 4_000_000_000;

    private const CHUNK = 262144;
    private const FLAG_UTF8 = 0x0800;

    private array $entries = [];
    private readonly int $dosTime;
    private readonly int $dosDate;
    private readonly int $length;

    public function __construct(array $entries, int $mtime)
    {
        if ($entries === [] || count($entries) > 0xFFFF) {
            throw new \LengthException('Numero di file non valido per un archivio ZIP.');
        }

        $year = max(1980, (int) gmdate('Y', $mtime));
        $this->dosDate = (($year - 1980) << 9) | ((int) gmdate('n', $mtime) << 5) | (int) gmdate('j', $mtime);
        $this->dosTime = ((int) gmdate('G', $mtime) << 11) | ((int) gmdate('i', $mtime) << 5) | ((int) gmdate('s', $mtime) >> 1);

        $offset = 0;
        $central = 0;
        foreach ($entries as $entry) {
            $entry['offset'] = $offset;
            $this->entries[] = $entry;
            $offset += 30 + strlen($entry['name']) + $entry['size'];
            $central += 46 + strlen($entry['name']);
        }

        $length = $offset + $central + 22;
        if ($length > 0xFFFFFFFF) {
            throw new \LengthException("Archivio troppo grande: ZIP64 non supportato!");
        }

        $this->length = $length;
    }

    public function length(): int
    {
        return $this->length;
    }

    public function chunks(): \Generator
    {
        try {
            foreach ($this->entries as $i => $entry) {
                yield $this->localHeader($entry);

                $remaining = $entry['size'];
                while ($remaining > 0) {
                    $data = fread($entry['handle'], min(self::CHUNK, $remaining));
                    if ($data === false || $data === '') {
                        throw new \RuntimeException("File più corto del previsto durante la lettura: " . $entry['name']);
                    }

                    $remaining -= strlen($data);
                    yield $data;
                }

                fclose($entry['handle']);
                unset($this->entries[$i]['handle']);
            }

            $start = $this->length - 22;
            $size = 0;
            foreach ($this->entries as $entry) {
                $header = $this->centralHeader($entry);
                $size += strlen($header);
                yield $header;
            }
            yield pack('VvvvvVVv', 0x06054B50, 0, 0, count($this->entries), count($this->entries), $size, $start - $size, 0);
        } finally {
            foreach ($this->entries as $entry) {
                if (isset($entry['handle']) && is_resource($entry['handle'])) {
                    fclose($entry['handle']);
                }
            }
        }
    }


    private function localHeader(array $e): string
    {
        return pack(
            'VvvvvvVVVvv',
            0x04034B50,
            20,
            self::FLAG_UTF8,
            0,
            $this->dosTime,
            $this->dosDate,
            $e['crc'],
            $e['size'],
            $e['size'],
            strlen($e['name']),
            0,
        ) . $e['name'];
    }

    private function centralHeader(array $e): string
    {
        return pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014B50,
            20,
            20,
            self::FLAG_UTF8,
            0,
            $this->dosTime,
            $this->dosDate,
            $e['crc'],
            $e['size'],
            $e['size'],
            strlen($e['name']),
            0,
            0,
            0,
            0,
            0,
            $e['offset'],
        ) . $e['name'];
    }
}