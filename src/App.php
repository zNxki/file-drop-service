<?php

declare(strict_types=1);

namespace FileDrop;

use FileDrop\Controller\{AdminController, ApiController, WebController};
use FileDrop\Exception\HttpException;
use FileDrop\Http\{Request, Response, Router};
use FileDrop\Repository\{FileRepository, LoginAttemptRepository, StatsRepository};
use FileDrop\Service\{CodeGenerator, FileService};
use FileDrop\Support\{AdminSession, Config, Database, View};

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
        $pdo = Database::connect($config->dbPath);
        $stats = new StatsRepository($pdo);
        $files = new FileService($config, new FileRepository($pdo), new CodeGenerator(), $stats);
        $view = new View($root . '/views');
        $web = new WebController($files, $config, $view);
        $api = new ApiController($files, $config);
        $admin = new AdminController($files, $config, $stats, new LoginAttemptRepository($pdo), new AdminSession($config->storagePath . '/sessions'), $view);

        $router = new Router();
        $router->add('GET', '/', $web->home(...));
        $router->add('GET', '/d/{code}', $web->show(...));
        $router->add('GET', '/d/{code}/preview', $web->preview(...));
        $router->add('GET', '/d/{code}/download', $web->download(...));
        $router->add('POST', '/d/{code}/download', $web->download(...));

        $router->add('GET', '/admin', $admin->dashboard(...));
        $router->add('GET', '/admin/login', $admin->loginForm(...));
        $router->add('POST', '/admin/login', $admin->login(...));
        $router->add('POST', '/admin/logout', $admin->logout(...));
        $router->add('POST', '/admin/cleanup', $admin->cleanup(...));
        $router->add('POST', '/admin/files/{code}/delete', $admin->delete(...));

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