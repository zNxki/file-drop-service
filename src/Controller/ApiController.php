<?php
declare(strict_types=1);

namespace FileDrop\Controller;

use FileDrop\Exception\HttpException;
use FileDrop\Http\Request;
use FileDrop\Http\Response;
use FileDrop\Service\ChunkService;
use FileDrop\Service\FileService;
use FileDrop\Support\Config;

final class ApiController extends Controller
{
    public function __construct(
        FileService $files,
        Config $config,
        private readonly ChunkService $chunks,
    ) {
        parent::__construct($files, $config);
    }

    public function upload(Request $request): Response
    {
        $postMax = $this->iniBytes((string) ini_get('post_max_size'));
        if ($request->files === [] && $postMax > 0 && $request->contentLength() > $postMax) {
            throw new HttpException(413, 'Il file supera il limite del server (post_max_size). Usa il caricamento a blocchi: POST /api/uploads.');
        }

        [$ttl, $password, $maxDownloads, $encrypted] = $this->options($request);

        $result = $this->files->upload(
            $this->uploadedFiles($request),
            $ttl,
            $password,
            $maxDownloads,
            $encrypted,
        );
        $this->files->maybePurge();

        return $this->uploadResponse($request, $result);
    }

    public function initUpload(Request $request): Response
    {
        $body = json_decode((string) stream_get_contents(fopen('php://input', 'rb'), 65536), true);
        if (!is_array($body) || !is_array($body['files'] ?? null)) {
            throw new HttpException(400, 'Corpo JSON non valido: atteso {"files": [{"name": "...", "size": 123}]}.');
        }
        $encrypted = $body['encrypted'] ?? false;
        if (!is_bool($encrypted)) {
            throw new HttpException(422, '"encrypted" deve essere true o false.');
        }

        $this->chunks->maybePurge();

        return Response::json(['data' => $this->chunks->init($body['files'], $encrypted)], 201);
    }

    public function uploadStatus(Request $request, array $params): Response
    {
        return Response::json(['data' => $this->chunks->status($params['id'])]);
    }

    public function uploadChunk(Request $request, array $params): Response
    {
        $index = $request->query['index'] ?? '0';
        $offset = $request->query['offset'] ?? null;
        if (!is_string($index) || !ctype_digit($index) || !is_string($offset) || !ctype_digit($offset)) {
            throw new HttpException(422, '"index" e "offset" devono essere numeri interi.');
        }

        $stream = fopen('php://input', 'rb');
        if ($stream === false) {
            throw new HttpException(500, 'Impossibile leggere il blocco.');
        }

        $data = $this->chunks->append($params['id'], (int) $index, (int) $offset, $stream, $request->contentLength());

        return Response::json(['data' => $data]);
    }

    public function completeUpload(Request $request, array $params): Response
    {
        $taken = $this->chunks->take($params['id']);
        [$ttl, $password, $maxDownloads] = $this->options($request);

        $result = $this->files->upload($taken['uploads'], $ttl, $password, $maxDownloads, $taken['encrypted']);
        $this->chunks->discard($params['id']);
        $this->files->maybePurge();

        return $this->uploadResponse($request, $result);
    }

    public function discardUpload(Request $request, array $params): Response
    {
        $this->chunks->status($params['id']);
        $this->chunks->discard($params['id']);

        return Response::json(['data' => ['deleted' => true]]);
    }

    private function options(Request $request): array
    {
        $ttl = $request->post['expires_in'] ?? null;
        if ($ttl !== null && (!is_scalar($ttl) || !ctype_digit((string) $ttl))) {
            throw new HttpException(422, '"expires_in" deve essere un numero di secondi.');
        }
        $password = $request->post['password'] ?? null;

        $maxDownloads = $request->post['max_downloads'] ?? null;
        if ($maxDownloads === '') {
            $maxDownloads = null;
        }
        if ($maxDownloads !== null && (!is_scalar($maxDownloads) || !ctype_digit((string) $maxDownloads))) {
            throw new HttpException(422, '"max_downloads" deve essere un numero intero positivo.');
        }

        $encrypted = $request->post['encrypted'] ?? null;
        if ($encrypted !== null && (!is_scalar($encrypted) || !in_array((string) $encrypted, ['', '0', '1'], true))) {
            throw new HttpException(422, '"encrypted" deve essere 0 o 1.');
        }

        return [
            $ttl === null ? null : (int) $ttl,
            is_string($password) ? $password : null,
            $maxDownloads === null ? null : (int) $maxDownloads,
            $encrypted === '1',
        ];
    }

    private function uploadResponse(Request $request, array $result): Response
    {
        $data = $result['file']->toArray($this->baseUrl($request));
        if ($result['items'] !== []) {
            $data['files'] = array_map(static fn($item) => $item->toArray(), $result['items']);
        }
        $data['delete_token'] = $result['deleteToken'];

        return Response::json(['data' => $data], 201, ['Location' => $data['url']]);
    }

    public function info(Request $request, array $params): Response
    {
        $file = $this->files->get($params['code']);
        $data = $file->toArray($this->baseUrl($request));
        if ($file->isBundle()) {
            $data['files'] = array_map(static fn($item) => $item->toArray(), $this->files->items($file));
        }

        return Response::json(['data' => $data]);
    }

    public function download(Request $request, array $params): Response
    {
        $file = $this->files->get($params['code']);
        $this->files->checkPassword($file, $request->header('X-File-Password'));

        return $this->downloadResponse($file);
    }

    public function delete(Request $request, array $params): Response
    {
        $this->files->delete($params['code'], $request->header('X-Delete-Token'));
        return Response::json(['data' => ['deleted' => true]]);
    }

    private function uploadedFiles(Request $request): array
    {
        $field = $request->files['file'] ?? null;
        if (!is_array($field)) {
            return [];
        }
        if (!is_array($field['name'] ?? null)) {
            return [$field];
        }

        $list = [];
        foreach (array_keys($field['name']) as $i) {
            $list[] = [
                'name' => $field['name'][$i] ?? '',
                'type' => $field['type'][$i] ?? '',
                'tmp_name' => $field['tmp_name'][$i] ?? '',
                'error' => $field['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $field['size'][$i] ?? 0,
            ];
        }
        return $list;
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;
        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1073741824,
            'm' => $number * 1048576,
            'k' => $number * 1024,
            default => $number,
        };
    }
}