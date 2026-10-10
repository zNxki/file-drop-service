<?php
declare(strict_types=1);

namespace FileDrop\Controller;

use FileDrop\Http\Request;
use FileDrop\Http\Response;
use FileDrop\Model\StoredFile;
use FileDrop\Service\FileService;
use FileDrop\Support\Config;

abstract class Controller
{
    public function __construct(
        protected readonly FileService $files,
        protected readonly Config $config,
    ) {
    }

    protected function downloadResponse(StoredFile $file): Response
    {
        $onSent = fn() => $this->files->completeDownload($file->code);

        if ($file->isBundle()) {
            $zip = $this->files->openBundleDownload($file);
            return Response::downloadChunks($zip->chunks(), $file->originalName, 'application/zip', $zip->length(), $onSent);
        }

        return Response::download(
            $this->files->openDownload($file),
            $file->originalName,
            $file->mimeType,
            $file->size,
            $onSent,
        );
    }

    protected function baseUrl(Request $request): string
    {
        return $this->config->appUrl !== '' ? $this->config->appUrl : $request->origin();
    }
}