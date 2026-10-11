<?php

declare(strict_types=1);

namespace FileDrop;

use FileDrop\Controller\{AdminController, ApiController, AuthController, SettingsController, UserController, WebController};
use FileDrop\Exception\HttpException;
use FileDrop\Http\{Request, Response, Router};
use FileDrop\Repository\{FileRepository, IpBlockRepository, LimitEventRepository, LoginAttemptRepository, QuotaRepository, StatsRepository, UserRepository};
use FileDrop\Service\{ChunkService, CodeGenerator, FileService, QuotaService, VirusScanner};
use FileDrop\Support\{AdminSession, Config, Database, I18n, IpRange, RateLimiter, View};

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

        I18n::boot($root);
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
        $prefixed = false;

        try {
            [$request, $redirect, $prefixed] = $this->resolveLocale($request);
            $this->assertNotBlocked($request);
            $this->limiter->check($request);
            $response = $redirect !== null
                ? Response::redirect($redirect, 302)
                    ->withHeader('Cache-Control', 'no-store')
                    ->withHeader('Vary', 'Accept-Language, Cookie')
                : $this->router->dispatch($request);
        } catch (HttpException $e) {
            $response = $this->errorResponse($request, $e);
        } catch (\Throwable $e) {
            error_log((string) $e);
            $message = $this->config->debug ? $e->getMessage() : I18n::t('err.internal');
            $response = $this->errorResponse($request, new HttpException(500, $message));
        }

        $secure = $request->isSecure() || str_starts_with($this->config->appUrl, 'https://');
        if ($prefixed && $request->cookie(I18n::COOKIE) !== I18n::current()) {
            $response = $response->withHeader('Set-Cookie', sprintf(
                '%s=%s; Path=/; Max-Age=31536000; SameSite=Lax; HttpOnly%s',
                I18n::COOKIE,
                I18n::current(),
                $secure ? '; Secure' : '',
            ));
        }

        $response->send($secure);
    }

    private function resolveLocale(Request $request): array
    {
        $path = $request->path;

        if (preg_match('~^/(admin|api|assets)(?:/|$)~', $path, $m) === 1) {
            I18n::set($m[1] === 'admin' ? I18n::default() : $this->negotiate($request));
            return [$request, null, false];
        }

        [$first, $rest] = array_pad(explode('/', ltrim($path, '/'), 2), 2, '');
        if (I18n::has($first)) {
            I18n::set($first);
            $stripped = '/' . $rest;
            $this->view->localize($stripped, $this->origin($request));
            return [$request->withPath($stripped), null, true];
        }

        I18n::set($this->negotiate($request));
        $this->view->localize($path, $this->origin($request));

        $direct = $request->method !== 'GET' && $request->method !== 'HEAD'
            || preg_match('~^/d/[A-Za-z0-9]+/(?:download|preview)$~', $path) === 1;
        if ($direct) {
            return [$request, null, false];
        }

        $query = (string) ($request->server['QUERY_STRING'] ?? '');
        $target = '/' . I18n::current() . ($path === '/' ? '' : $path) . ($query !== '' ? '?' . $query : '');

        return [$request, $target, false];
    }

    private function origin(Request $request): string
    {
        return $this->config->appUrl !== '' ? $this->config->appUrl : $request->origin();
    }

    private function negotiate(Request $request): string
    {
        return I18n::negotiate($request->cookie(I18n::COOKIE), $request->header('Accept-Language'));
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
            throw new HttpException(403, I18n::t('err.blocked'));
        }
    }

    private function errorResponse(Request $request, HttpException $e): Response
    {
        return $request->isApi()
            ? Response::json(['error' => ['status' => $e->status, 'message' => $e->getMessage()]], $e->status, $e->headers)
            : $this->web->error($e);
    }
}