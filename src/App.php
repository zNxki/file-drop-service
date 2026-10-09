<?php

declare(strict_types=1);

namespace FileDrop;

use FileDrop\Controller\{ApiController, WebController};
use FileDrop\Exception\HttpException;
use FileDrop\Http\{Request, Response, Router};
use FileDrop\Repository\FileRepository;
use FileDrop\Service\{CodeGenerator, FileService};
use FileDrop\Support\{Config, Database, View};

final class App
{
    private function __construct(
        public readonly Config $config,
        public readonly FileService $files,
        private readonly Router $router,
        private readonly WebController $web,
    ) {
    }

    public static function boot(string $root): self
    {
        date_default_timezone_set('UTC');

        $config = Config::load($root);
        $files = new FileService($config, new FileRepository(Database::connect($config->dbPath)), new CodeGenerator());
        $web = new WebController($files, $config, new View($root . '/views'));
        $api = new ApiController($files, $config);

        $router = new Router();
        $router->add('GET', '/', $web->home(...));
        $router->add('GET', '/d/{code}', $web->show(...));
        $router->add('GET', '/d/{code}/preview', $web->preview(...));
        $router->add('GET', '/d/{code}/download', $web->download(...));
        $router->add('POST', '/d/{code}/download', $web->download(...));

        $router->add('POST', '/api/files', $api->upload(...));
        $router->add('GET', '/api/files/{code}', $api->info(...));
        $router->add('GET', '/api/files/{code}/download', $api->download(...));
        $router->add('DELETE', '/api/files/{code}', $api->delete(...));

        return new self($config, $files, $router, $web);
    }

    public function run(): void
    {
        $request = Request::fromGlobals();

        try {
            $response = $this->router->dispatch($request);
        } catch (HttpException $e) {
            $response = $this->errorResponse($request, $e);
        } catch (\Throwable $e) {
            error_log((string) $e);
            $message = $this->config->debug ? $e->getMessage() : 'Errore interno del server.';
            $response = $this->errorResponse($request, new HttpException(500, $message));
        }

        $response->send();
    }

    private function errorResponse(Request $request, HttpException $e): Response
    {
        return $request->isApi()
            ? Response::json(['error' => ['status' => $e->status, 'message' => $e->getMessage()]], $e->status)
            : $this->web->error($e);
    }
}