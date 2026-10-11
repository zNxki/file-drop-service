<?php
declare(strict_types=1);

namespace FileDrop\Service;

use FileDrop\Exception\HttpException;
use FileDrop\Repository\{LimitEventRepository, QuotaRepository};
use FileDrop\Support\Config;

final class QuotaService
{
    public const DAILY_WINDOW = 86400;

    public function __construct(
        private readonly Config $config,
        private readonly QuotaRepository $repository,
        private readonly ?LimitEventRepository $events = null,
    ) {
    }

    public function key(string $client): string
    {
        return hash('sha256', $client);
    }

    public function assert(string $client, int $bytes): void
    {
        try {
            $this->check($client, $bytes);
        } catch (QuotaExceeded $e) {
            $this->events?->record($client, 'quota:' . $e->reason);
            throw $e->toHttp();
        }
    }

    private function check(string $client, int $bytes): void
    {
        $config = $this->config;
        $key = $this->key($client);
        $now = time();

        if ($config->quotaMaxFiles > 0 || $config->quotaStorage > 0) {
            $active = $this->repository->active($key, $now);

            if ($config->quotaMaxFiles > 0 && $active['files'] >= $config->quotaMaxFiles) {
                throw new QuotaExceeded('files', sprintf(
                    'Hai raggiunto il numero massimo di caricamenti attivi (%d). Elimina un file o attendi la scadenza.',
                    $config->quotaMaxFiles
                ));
            }
            if ($config->quotaStorage > 0 && $active['bytes'] + $bytes > $config->quotaStorage) {
                throw new QuotaExceeded('storage', sprintf(
                    'Spazio insufficiente: occupi %s su %s e questo caricamento ne richiede %s. Elimina un file o attendi la scadenza.',
                    $this->format($active['bytes']),
                    $this->format($config->quotaStorage),
                    $this->format($bytes)
                ));
            }
        }

        if ($config->quotaDaily > 0) {
            $sent = $this->repository->uploadedSince($key, $now - self::DAILY_WINDOW);
            if ($sent + $bytes > $config->quotaDaily) {
                throw new QuotaExceeded('daily', sprintf(
                    'Limite giornaliero di caricamento raggiunto (%s nelle ultime 24 ore su %s). Riprova più tardi.',
                    $this->format($sent),
                    $this->format($config->quotaDaily)
                ));
            }
        }
    }

    public function record(string $client, int $bytes): void
    {
        if ($this->config->quotaDaily > 0) {
            $this->repository->record($this->key($client), $bytes, time());
        }
    }

    public function prune(int $now): void
    {
        $this->repository->prune($now - self::DAILY_WINDOW * 2);
    }

    private function format(int $bytes): string
    {
        return round($bytes / 1048576, 1) . ' MB';
    }
}
