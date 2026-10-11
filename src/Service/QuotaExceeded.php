<?php
declare(strict_types=1);

namespace FileDrop\Service;

use FileDrop\Exception\HttpException;

final class QuotaExceeded extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public function toHttp(): HttpException
    {
        return new HttpException(429, $this->getMessage());
    }
}
