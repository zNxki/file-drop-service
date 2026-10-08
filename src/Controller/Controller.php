<?php
declare(strict_types=1);

namespace FileDrop\Controller;

use FileDrop\Http\Request;
use FileDrop\Service\FileService;
use FileDrop\Support\Config;

abstract class Controller
{
    public function __construct(
        protected readonly FileService $files,
        protected readonly Config $config,
    ) {
    }

    protected function baseUrl(Request $request): string
    {
        return $this->config->appUrl !== '' ? $this->config->appUrl : $request->origin();
    }
}