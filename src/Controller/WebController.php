<?php
declare(strict_types=1);

namespace FileDrop\Controller;

use FileDrop\Exception\HttpException;
use FileDrop\Http\Request;
use FileDrop\Http\Response;
use FileDrop\Service\FileService;
use FileDrop\Support\Config;
use FileDrop\Support\View;

final class WebController extends Controller
{
    private const EXPIRY_PRESETS = [
        3600 => '1 ora',
        86400 => '24 ore',
        604800 => '7 giorni',
        2592000 => '30 giorni',
    ];

    public function __construct(FileService $files, Config $config, private readonly View $view)
    {
        parent::__construct($files, $config);
    }

    public function home(Request $request): Response
    {
        $options = array_filter(self::EXPIRY_PRESETS, fn(int $s) => $s <= $this->config->maxExpiry, ARRAY_FILTER_USE_KEY);
        $options[$this->config->defaultExpiry] ??= $this->humanDuration($this->config->defaultExpiry);
        ksort($options);

        return Response::html($this->view->render('home', [
            'title' => 'File Drop',
            'script' => true,
            'maxSize' => $this->config->maxFileSize,
            'options' => $options,
            'defaultExpiry' => $this->config->defaultExpiry,
            'baseUrl' => $this->baseUrl($request),
        ]));
    }

    /** GET /d/{code} */
    public function show(Request $request, array $params): Response
    {
        $file = $this->files->get($params['code']);

        return Response::html($this->view->render('download', [
            'title' => $file->originalName . ' · File Drop',
            'script' => true,
            'file' => $file,
            'error' => null,
        ]));
    }

    public function download(Request $request, array $params): Response
    {
        $file = $this->files->get($params['code']);
        $password = $request->method === 'POST' ? ($request->post['password'] ?? null) : null;

        if ($file->isProtected() && $request->method === 'GET') {
            return Response::redirect('/d/' . $file->code);
        }

        try {
            $this->files->checkPassword($file, is_string($password) ? $password : null);
        } catch (HttpException $e) {
            return Response::html($this->view->render('download', [
                'title' => $file->originalName . ' · File Drop',
                'script' => true,
                'file' => $file,
                'error' => $e->getMessage(),
            ]), $e->status);
        }

        $path = $this->files->path($file);
        $this->files->recordDownload($file);

        return Response::file($path, $file->originalName, $file->mimeType, $file->size);
    }

    public function error(HttpException $e): Response
    {
        return Response::html($this->view->render('error', [
            'title' => 'Errore ' . $e->status . ' · File Drop',
            'script' => false,
            'status' => $e->status,
            'message' => $e->getMessage(),
        ]), $e->status);
    }

    private function humanDuration(int $seconds): string
    {
        return $seconds % 86400 === 0 ? ($seconds / 86400) . ' giorni' : ($seconds % 3600 === 0 ? ($seconds / 3600) . ' ore' : $seconds . ' secondi');
    }
}