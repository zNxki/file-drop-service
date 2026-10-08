<?php
declare(strict_types=1);

namespace FileDrop\Controller;

use FileDrop\Exception\HttpException;
use FileDrop\Http\Request;
use FileDrop\Http\Response;

final class ApiController extends Controller
{
    public function upload(Request $request): Response
    {
        $postMax = $this->iniBytes((string) ini_get('post_max_size'));
        if ($request->files === [] && $postMax > 0 && $request->contentLength() > $postMax) {
            throw new HttpException(413, 'Il file supera il limite del server (post_max_size).');
        }

        $ttl = $request->post['expires_in'] ?? null;
        if ($ttl !== null && (!is_scalar($ttl) || !ctype_digit((string) $ttl))) {
            throw new HttpException(422, '"expires_in" deve essere un numero di secondi.');
        }
        $password = $request->post['password'] ?? null;

        $result = $this->files->upload(
            is_array($request->files['file'] ?? null) ? $request->files['file'] : null,
            $ttl === null ? null : (int) $ttl,
            is_string($password) ? $password : null,
        );
        $this->files->maybePurge();

        $data = $result['file']->toArray($this->baseUrl($request));
        $data['delete_token'] = $result['deleteToken'];

        return Response::json(['data' => $data], 201, ['Location' => $data['url']]);
    }

    public function info(Request $request, array $params): Response
    {
        $file = $this->files->get($params['code']);
        return Response::json(['data' => $file->toArray($this->baseUrl($request))]);
    }

    public function download(Request $request, array $params): Response
    {
        $file = $this->files->get($params['code']);
        $this->files->checkPassword($file, $request->header('X-File-Password'));
        $path = $this->files->path($file);
        $this->files->recordDownload($file);

        return Response::file($path, $file->originalName, $file->mimeType, $file->size);
    }

    public function delete(Request $request, array $params): Response
    {
        $this->files->delete($params['code'], $request->header('X-Delete-Token'));
        return Response::json(['data' => ['deleted' => true]]);
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