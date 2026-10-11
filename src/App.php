<?php

declare(strict_types=1);

namespace FileDrop;

use FileDrop\Controller\{AdminController, ApiController, AuthController, SettingsController, UserController, WebController};
use FileDrop\Exception\HttpException;
use FileDrop\Http\{Request, Response, Router};
use FileDrop\Repository\{FileRepository, IpBlockRepository, LimitEventRepository, LoginAttemptRepository, QuotaRepository, StatsRepository, UserRepository};
use FileDrop\Service\{ChunkService, CodeGenerator, FileService, QuotaService, VirusScanner};
use FileDrop\Support\{AdminSession, Config, Database, IpRange, RateLimiter, View};

final class App
{
    private function __construct(
        public readonly Config $config,
        public readonly FileService $files,
        public readonly ChunkService $chunks,
        private readonly Router $router,
        private readonly WebController $web,
        private readonly View $view,
        private readonly RateLimiter $limiter,
        private readonly IpBlockRepository $blocks,
    ) {
    }

    public static function boot(string $root): self
    {
        date_default_timezone_set('UTC');

        $config = Config::load($root);
        $pdo = Database::connect($config);
        $stats = new StatsRepository($pdo);
        $events = new LimitEventRepository($pdo);
        $blocks = new IpBlockRepository($pdo);
        $files = new FileService(
            $config,
            new FileRepository($pdo),
            new CodeGenerator(),
            $stats,
            new QuotaService($config, new QuotaRepository($pdo), $events),
            $config->clamavEnabled ? new VirusScanner($config) : null,
        );
        $chunks = new ChunkService($config, $files);
        $view = new View($root . '/views');
        $web = new WebController($files, $config, $view);
        $api = new ApiController($files, $config, $chunks);
        $users = new UserRepository($pdo);
        $session = new AdminSession($config->storagePath . '/sessions');
        $auth = new AuthController($files, $config, $session, $view, $users, new LoginAttemptRepository($pdo));
        $admin = new AdminController($files, $config, $session, $view, $users, $stats, $blocks, $events);
        $accounts = new UserController($files, $config, $session, $view, $users);
        $settings = new SettingsController($files, $config, $session, $view, $users);

        $router = new Router();
        $router->add('GET', '/', $web->home(...));
        $router->add('GET', '/d/{code}', $web->show(...));
        $router->add('GET', '/d/{code}/preview', $web->preview(...));
        $router->add('GET', '/d/{code}/download', $web->download(...));
        $router->add('POST', '/d/{code}/download', $web->download(...));

        $router->add('GET', '/admin', $admin->overview(...));
        $router->add('GET', '/admin/files', $admin->files(...));
        $router->add('GET', '/admin/security', $admin->security(...));
        $router->add('POST', '/admin/cleanup', $admin->cleanup(...));
        $router->add('POST', '/admin/files/{code}/delete', $admin->delete(...));
        $router->add('POST', '/admin/blocks', $admin->block(...));
        $router->add('POST', '/admin/blocks/delete', $admin->unblock(...));

        $router->add('GET', '/admin/setup', $auth->setupForm(...));
        $router->add('POST', '/admin/setup', $auth->setup(...));
        $router->add('GET', '/admin/login', $auth->loginForm(...));
        $router->add('POST', '/admin/login', $auth->login(...));
        $router->add('GET', '/admin/2fa', $auth->twoFactorForm(...));
        $router->add('POST', '/admin/2fa', $auth->twoFactor(...));
        $router->add('POST', '/admin/logout', $auth->logout(...));

        $router->add('GET', '/admin/users', $accounts->index(...));
        $router->add('POST', '/admin/users', $accounts->create(...));
        $router->add('GET', '/admin/users/edit', $accounts->edit(...));
        $router->add('POST', '/admin/users/update', $accounts->update(...));
        $router->add('POST', '/admin/users/reset-2fa', $accounts->resetTwoFactor(...));
        $router->add('POST', '/admin/users/delete', $accounts->delete(...));

        $router->add('GET', '/admin/settings', $settings->show(...));
        $router->add('POST', '/admin/settings/password', $settings->password(...));
        $router->add('POST', '/admin/settings/2fa/start', $settings->twoFactorStart(...));
        $router->add('POST', '/admin/settings/2fa/cancel', $settings->twoFactorCancel(...));
        $router->add('POST', '/admin/settings/2fa/enable', $settings->twoFactorEnable(...));
        $router->add('POST', '/admin/settings/2fa/disable', $settings->twoFactorDisable(...));

        $router->add('POST', '/api/files', $api->upload(...));
        $router->add('POST', '/api/uploads', $api->initUpload(...));
        $router->add('GET', '/api/uploads/{id}', $api->uploadStatus(...));
        $router->add('PUT', '/api/uploads/{id}', $api->uploadChunk(...));
        $router->add('POST', '/api/uploads/{id}/complete', $api->completeUpload(...));
        $router->add('DELETE', '/api/uploads/{id}', $api->discardUpload(...));
        $router->add('GET', '/api/files/{code}', $api->info(...));
        $router->add('GET', '/api/files/{code}/download', $api->download(...));
        $router->add('DELETE', '/api/files/{code}', $api->delete(...));

        return new self($config, $files, $chunks, $router, $web, $view, new RateLimiter($pdo, $config, $events), $blocks);
    }

    public function run(): void
    {
        $request = Request::fromGlobals($this->config->trustedProxies);
        $this->view->fragment($request->header('X-Fd-Nav') !== null && !$request->isApi());

        try {
            $this->assertNotBlocked($request);
            $this->limiter->check($request);
            $response = $this->router->dispatch($request);
        } catch (HttpException $e) {
            $response = $this->errorResponse($request, $e);
        } catch (\Throwable $e) {
            error_log((string) $e);
            $message = $this->config->debug ? $e->getMessage() : 'Errore interno del server.';
            $response = $this->errorResponse($request, new HttpException(500, $message));
        }

        $response->send($request->isSecure() || str_starts_with($this->config->appUrl, 'https://'));
    }

    private function assertNotBlocked(Request $request): void
    {
        try {
            $ranges = $this->blocks->activeRanges(time());
        } catch (\PDOException $e) {
            error_log('[ip-block] ' . $e->getMessage());
            return;
        }

        if (IpRange::matches($request->ip(), $ranges)) {
            throw new HttpException(403, 'Accesso non consentito da questo indirizzo.');
        }
    }

    private function errorResponse(Request $request, HttpException $e): Response
    {
        return $request->isApi()
            ? Response::json(['error' => ['status' => $e->status, 'message' => $e->getMessage()]], $e->status, $e->headers)
            : $this->web->error($e);
    }
}