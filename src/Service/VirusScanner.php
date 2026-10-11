<?php
declare(strict_types=1);

namespace FileDrop\Service;

use FileDrop\Exception\HttpException;
use FileDrop\Exception\VirusDetectedException;
use FileDrop\Support\Config;
use FileDrop\Support\I18n;

final class VirusScanner
{
    private const BLOCK = 262144;

    public function __construct(private readonly Config $config)
    {
    }

    public function scan(string $path, string $name): void
    {
        try {
            $signature = $this->query($path);
        } catch (\RuntimeException $e) {
            error_log('[antivirus] ' . $e->getMessage());
            if ($this->config->clamavRejectOnError) {
                throw new HttpException(503, I18n::t('err.antivirus_down'));
            }
            return;
        }

        if ($signature !== null) {
            throw new VirusDetectedException(I18n::t('err.malware', ['name' => $name, 'signature' => $signature]));
        }
    }

    private function query(string $path): ?string
    {
        $file = @fopen($path, 'rb');
        if ($file === false) {
            throw new \RuntimeException('impossibile leggere il file da analizzare');
        }

        $socket = false;
        try {
            $socket = @stream_socket_client(
                sprintf('tcp://%s:%d', $this->config->clamavHost, $this->config->clamavPort),
                $errno,
                $error,
                min(10, $this->config->clamavTimeout)
            );
            if ($socket === false) {
                throw new \RuntimeException(sprintf('connessione a clamd non riuscita (%s)', $error));
            }
            stream_set_timeout($socket, $this->config->clamavTimeout);

            $this->write($socket, "zINSTREAM\0");
            while (!feof($file)) {
                $block = fread($file, self::BLOCK);
                if ($block === false) {
                    throw new \RuntimeException('lettura del file non riuscita');
                }
                if ($block !== '') {
                    $this->write($socket, pack('N', strlen($block)) . $block);
                }
            }
            $this->write($socket, pack('N', 0));

            $reply = rtrim((string) stream_get_contents($socket), "\0\r\n ");
        } finally {
            fclose($file);
            if (is_resource($socket)) {
                fclose($socket);
            }
        }

        if ($reply === 'stream: OK') {
            return null;
        }
        if (preg_match('/^stream: (.+) FOUND$/', $reply, $m) === 1) {
            return $m[1];
        }

        throw new \RuntimeException('risposta inattesa da clamd: ' . ($reply === '' ? '(vuota)' : $reply));
    }

    private function write($socket, string $data): void
    {
        $length = strlen($data);
        for ($sent = 0; $sent < $length; ) {
            $written = @fwrite($socket, substr($data, $sent));
            if ($written === false || $written === 0) {
                throw new \RuntimeException('scrittura su clamd non riuscita');
            }
            $sent += $written;
        }
    }
}
