<?php
declare(strict_types=1);

namespace FileDrop\Exception;

final class VirusDetectedException extends HttpException
{
    public function __construct(string $message)
    {
        parent::__construct(422, $message);
    }
}