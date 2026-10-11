<?php
declare(strict_types=1);

namespace FileDrop\Controller;

use FileDrop\Http\Request;
use FileDrop\Http\Response;
use FileDrop\Model\User;
use FileDrop\Repository\UserRepository;
use FileDrop\Service\FileService;
use FileDrop\Support\{AdminSession, Config, Permissions, View};

abstract class PanelController extends Controller
{
    protected const MIN_PASSWORD = 10;
    protected const MAX_PASSWORD = 1024;

    protected ?User $user = null;

    public function __construct(
        FileService $files,
        Config $config,
        protected readonly AdminSession $session,
        protected readonly View $view,
        protected readonly UserRepository $users,
    ) {
        parent::__construct($files, $config);
    }

    protected function open(Request $request): void
    {
        $secure = $request->isSecure() || str_starts_with($this->config->appUrl, 'https://');
        $this->session->start($secure, $request->header('User-Agent') ?? '');
    }

    protected function guard(Request $request, ?string $permission = null): ?Response
    {
        $this->open($request);

        if ($this->users->count() === 0) {
            return Response::redirect('/admin/setup');
        }
        if (!$this->session->isAuthenticated()) {
            return Response::redirect('/admin/login');
        }

        $user = $this->users->find($this->session->userId());
        if ($user === null || !$user->active || !hash_equals($user->authStamp(), $this->session->stamp())) {
            $this->session->logout();

            return Response::redirect('/admin/login');
        }

        $this->user = $user;
        if ($permission !== null && !$user->can($permission)) {
            return $this->page('admin/forbidden', 'Accesso negato', $this->homeSection(), [], 403);
        }

        return null;
    }

    protected function homeUrl(): string
    {
        return match (true) {
            $this->user === null => '/admin/login',
            $this->user->can(Permissions::STATS_VIEW) => '/admin',
            $this->user->can(Permissions::FILES_VIEW) => '/admin/files',
            $this->user->can(Permissions::SECURITY_VIEW) => '/admin/security',
            $this->user->can(Permissions::USERS_MANAGE) => '/admin/users',
            default => '/admin/settings',
        };
    }

    protected function homeSection(): string
    {
        return match ($this->homeUrl()) {
            '/admin' => 'overview',
            '/admin/files' => 'files',
            '/admin/security' => 'security',
            '/admin/users' => 'users',
            default => 'settings',
        };
    }

    protected function page(string $template, string $title, string $section, array $data = [], int $status = 200, array $headers = []): Response
    {
        return Response::html($this->view->render($template, $data + [
            'title' => $title . ' · File Drop',
            'panel' => true,
            'section' => $section,
            'script' => false,
            'wide' => true,
            'noindex' => true,
            'csrf' => $this->session->csrf(),
            'flash' => $this->session->pullFlash(),
            'user' => $this->user,
        ]), $status, $headers);
    }

    protected function plain(string $template, string $title, array $data = [], int $status = 200, array $headers = []): Response
    {
        return Response::html($this->view->render($template, $data + [
            'title' => $title . ' · File Drop',
            'script' => false,
            'noindex' => true,
            'csrf' => $this->session->csrf(),
        ]), $status, $headers);
    }

    protected function validPost(Request $request): bool
    {
        if ($this->sameOrigin($request) && $this->session->validCsrf($request->post['csrf'] ?? null)) {
            return true;
        }
        $this->session->flash('error', 'La pagina è scaduta: l\'azione non è stata eseguita. Riprova.');

        return false;
    }

    protected function sameOrigin(Request $request): bool
    {
        $site = $request->header('Sec-Fetch-Site');
        if ($site !== null) {
            return in_array($site, ['same-origin', 'none'], true);
        }

        $origin = $request->header('Origin');
        if ($origin === null) {
            return true;
        }

        $originHost = parse_url($origin, PHP_URL_HOST);
        $host = parse_url('//' . ($request->server['HTTP_HOST'] ?? ''), PHP_URL_HOST);

        return is_string($originHost) && is_string($host) && strcasecmp($originHost, $host) === 0;
    }

    protected function audit(string $event, Request $request, ?string $username = null): void
    {
        error_log(sprintf('[admin-auth] %s ip=%s%s', $event, $request->ip(), $username === null ? '' : ' user=' . $username));
    }

    protected function text(mixed $value, int $max): string
    {
        return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
    }

    protected function usernameError(string $username): ?string
    {
        return preg_match('/^[A-Za-z0-9._-]{3,32}$/', $username) === 1
            ? null
            : 'Il nome utente deve avere da 3 a 32 caratteri: lettere, numeri, punto, trattino e underscore.';
    }

    protected function passwordError(mixed $password, mixed $confirm): ?string
    {
        if (!is_string($password) || !is_string($confirm)) {
            return 'Password non valida.';
        }
        if (mb_strlen($password) < self::MIN_PASSWORD) {
            return sprintf('La password deve avere almeno %d caratteri.', self::MIN_PASSWORD);
        }
        if (strlen($password) > self::MAX_PASSWORD) {
            return 'La password è troppo lunga.';
        }
        if (!hash_equals($password, $confirm)) {
            return 'Le due password non coincidono.';
        }

        return null;
    }

    protected function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}