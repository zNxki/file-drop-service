<?php
declare(strict_types=1);

namespace FileDrop\Controller;

use FileDrop\Exception\HttpException;
use FileDrop\Http\Request;
use FileDrop\Http\Response;
use FileDrop\Service\FileService;
use FileDrop\Support\Config;
use FileDrop\Support\I18n;
use FileDrop\Support\Preview;
use FileDrop\Support\View;

final class WebController extends Controller
{
    private const EXPIRY_PRESETS = [
        3600 => ['hours', 1],
        86400 => ['hours', 24],
        604800 => ['days', 7],
        2592000 => ['days', 30],
    ];

    public function __construct(FileService $files, Config $config, private readonly View $view)
    {
        parent::__construct($files, $config);
    }

    public function home(Request $request): Response
    {
        $options = [];
        foreach (self::EXPIRY_PRESETS as $seconds => [$unit, $count]) {
            if ($seconds <= $this->config->maxExpiry) {
                $options[$seconds] = I18n::plural('duration.' . $unit, $count);
            }
        }
        $options[$this->config->defaultExpiry] ??= I18n::duration($this->config->defaultExpiry);
        ksort($options);

        return Response::html($this->view->render('home', [
            'title' => 'File Drop',
            'script' => true,
            'maxSize' => $this->config->maxFileSize,
            'options' => $options,
            'defaultExpiry' => $this->config->defaultExpiry,
            'maxDownloads' => FileService::MAX_DOWNLOADS,
            'maxFiles' => $this->files->maxFiles(),
            'baseUrl' => $this->baseUrl($request),
        ]));
    }

    public function show(Request $request, array $params): Response
    {
        return $this->downloadPage($request, $this->files->get($params['code']));
    }

    public function download(Request $request, array $params): Response
    {
        $file = $this->files->get($params['code']);
        $password = $request->method === 'POST' ? ($request->post['password'] ?? null) : null;

        if ($file->isProtected() && $request->method === 'GET') {
            return Response::redirect($this->view->url('/d/' . $file->code));
        }

        try {
            $this->files->checkPassword($file, is_string($password) ? $password : null);
        } catch (HttpException $e) {
            return $this->downloadPage($request, $file, $e->getMessage(), $e->status);
        }

        return $this->downloadResponse($file);
    }

    public function preview(Request $request, array $params): Response
    {
        $file = $this->files->get($params['code']);
        $kind = $file->previewKind();
        if ($kind === null) {
            throw new HttpException(404, I18n::t('err.preview_unavailable'));
        }

        return Response::inline(
            $this->files->openPreview($file),
            $file->originalName,
            Preview::contentType($kind, $file->mimeType),
            $file->size,
            $request->header('Range'),
            Preview::headers($kind),
        );
    }

    public function error(HttpException $e): Response
    {
        return Response::html($this->view->render('error', [
            'title' => I18n::t('error.title', ['status' => $e->status]),
            'script' => false,
            'status' => $e->status,
            'message' => $e->getMessage(),
        ]), $e->status, $e->headers);
    }

    private function downloadPage(Request $request, \FileDrop\Model\StoredFile $file, ?string $error = null, int $status = 200): Response
    {
        return Response::html($this->view->render('download', [
            'title' => $file->displayName() . ' · File Drop',
            'script' => true,
            'file' => $file,
            'items' => $this->files->items($file),
            'error' => $error,
            'shareUrl' => $this->baseUrl($request) . '/d/' . $file->code,
        ]), $status);
    }
}
