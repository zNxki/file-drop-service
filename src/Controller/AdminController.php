<?php
declare(strict_types=1);

namespace FileDrop\Controller;

use FileDrop\Exception\HttpException;
use FileDrop\Http\Request;
use FileDrop\Http\Response;
use FileDrop\Repository\FileRepository;
use FileDrop\Repository\LoginAttemptRepository;
use FileDrop\Repository\StatsRepository;
use FileDrop\Service\FileService;
use FileDrop\Support\AdminSession;
use FileDrop\Support\Config;
use FileDrop\Support\View;

final class AdminController extends Controller
{
    private const PER_PAGE = 20;
    private const CHART_DAYS = 30;
    private const MAX_FAILED_LOGINS = 5;
    private const LOGIN_WINDOW = 900;
    private const MAX_DELAY_MICROSECONDS = 3_000_000;

    private bool $secure = false;

    public function __construct(
        FileService $files,
        Config $config,
        private readonly StatsRepository $stats,
        private readonly LoginAttemptRepository $attempts,
        private readonly AdminSession $session,
        private readonly View $view
    ) {
        parent::__construct($files, $config);
    }

    public function loginForm(Request $request): Response
    {
        $this->open($request);
        if ($this->session->isAuthenticated()) {
            return Response::redirect('/admin');
        }

        return $this->renderLogin();
    }

    public function login(Request $request): Response
    {
        $this->open($request);
        if (!$this->sameOrigin($request)) {
            return $this->renderLogin('Richiesta non valida.', 403);
        }
        if (!$this->session->validCsrf($request->post['csrf'] ?? null)) {
            return $this->renderLogin('La pagina è scaduta. Riprova.', 400);
        }

        $ip = $request->ip();
        $now = time();
        $since = $now - self::LOGIN_WINDOW;
        if ($this->attempts->countSince($ip, $since) >= self::MAX_FAILED_LOGINS) {
            $wait = max(1, ($this->attempts->oldestSince($ip, $since) ?? $now) + self::LOGIN_WINDOW - $now);
            $this->audit('login_locked', $request);

            return $this->renderLogin(
                sprintf('Troppi tentativi falliti. Riprova tra %d minuti.', (int) ceil($wait / 60)),
                429,
                ['Retry-After' => (string) $wait]
            );
        }

        $password = $request->post['password'] ?? null;
        if (
            is_string($password) && $password !== '' && strlen($password) <= 1024
            && password_verify($password, $this->config->adminPasswordHash)
        ) {
            $this->attempts->clear($ip);
            $this->session->login();
            $this->audit('login_ok', $request);

            return Response::redirect('/admin');
        }

        $this->attempts->record($ip, $now);
        $failures = $this->attempts->countSince($ip, $since);
        usleep(min(self::MAX_DELAY_MICROSECONDS, 300_000 * 2 ** max(0, $failures - 1)));
        $this->audit('login_failed', $request);

        return $this->renderLogin('Password errata.', 403);
    }

    public function logout(Request $request): Response
    {
        $this->open($request);
        if ($this->sameOrigin($request) && $this->session->validCsrf($request->post['csrf'] ?? null)) {
            $this->session->logout();
            $this->audit('logout', $request);
        }

        return Response::redirect('/admin/login');
    }

    public function dashboard(Request $request): Response
    {
        if (($denied = $this->guard($request)) !== null) {
            return $denied;
        }

        $query = $this->text($request->query['q'] ?? null, 100);
        $sort = $this->sortKey($request->query['sort'] ?? null);
        $dir = $request->query['dir'] ?? null;
        $ascending = $dir === 'asc' || ($dir !== 'desc' && $sort === 'name');
        $page = $this->pageNumber($request->query['page'] ?? null);

        $now = time();
        $disk = $this->files->storageUsage();
        $totals = $this->stats->fileTotals($now);
        $list = $this->files->adminList($query, $sort, $ascending, $page, self::PER_PAGE);

        return Response::html($this->view->render('admin/dashboard', [
            'title' => 'Amministrazione · File Drop',
            'script' => true,
            'wide' => true,
            'noindex' => true,
            'csrf' => $this->session->csrf(),
            'flash' => $this->session->pullFlash(),
            'now' => $now,
            'disk' => $disk,
            'usage' => $this->usage($disk, $totals),
            'totals' => $totals,
            'downloads' => $this->stats->downloadTotals($now),
            'perDay' => $this->stats->downloadsPerDay(self::CHART_DAYS, $now),
            'top' => $this->stats->topFiles($now - self::CHART_DAYS * 86400, 5),
            'recent' => $this->stats->recentDownloads(10),
            'list' => $list,
            'q' => $query,
            'sort' => $sort,
            'ascending' => $ascending,
            'perPage' => self::PER_PAGE,
            'retentionDays' => FileService::LOG_RETENTION_DAYS,
        ]), 200, $this->headers());
    }

    public function delete(Request $request, array $params): Response
    {
        if (($denied = $this->guard($request)) !== null) {
            return $denied;
        }
        $back = Response::redirect($this->listUrl($request->post));
        if (!$this->validPost($request)) {
            return $back;
        }

        try {
            $file = $this->files->adminDelete($params['code']);
            $this->session->flash('ok', sprintf('File «%s» eliminato.', $file->originalName));
        } catch (HttpException $e) {
            $this->session->flash('error', $e->getMessage());
        }

        return $back;
    }

    public function cleanup(Request $request): Response
    {
        if (($denied = $this->guard($request)) !== null) {
            return $denied;
        }
        if ($this->validPost($request)) {
            $removed = $this->files->purgeExpired();
            $this->session->flash('ok', match ($removed) {
                0 => 'Nessun file da eliminare.',
                1 => 'Eliminato 1 file scaduto o esaurito.',
                default => sprintf('Eliminati %d file scaduti o esauriti.', $removed),
            });
        }

        return Response::redirect($this->listUrl($request->post));
    }

    private function open(Request $request): void
    {
        if (!$this->config->adminEnabled()) {
            throw new HttpException(404, 'Pagina non trovata.');
        }
        $this->secure = $request->isSecure() || str_starts_with($this->config->appUrl, 'https://');
        $this->session->start($this->secure, $request->header('User-Agent') ?? '');
    }

    private function guard(Request $request): ?Response
    {
        $this->open($request);

        return $this->session->isAuthenticated() ? null : Response::redirect('/admin/login');
    }

    private function validPost(Request $request): bool
    {
        if ($this->sameOrigin($request) && $this->session->validCsrf($request->post['csrf'] ?? null)) {
            return true;
        }
        $this->session->flash('error', 'La pagina è scaduta: l\'azione non è stata eseguita. Riprova.');

        return false;
    }

    private function sameOrigin(Request $request): bool
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

    private function headers(array $extra = []): array
    {
        return $extra + ($this->secure ? ['Strict-Transport-Security' => 'max-age=31536000'] : []);
    }

    private function audit(string $event, Request $request): void
    {
        error_log(sprintf('[admin-auth] %s ip=%s', $event, $request->ip()));
    }

    private function renderLogin(?string $error = null, int $status = 200, array $headers = []): Response
    {
        return Response::html($this->view->render('admin/login', [
            'title' => 'Accesso · File Drop',
            'script' => false,
            'noindex' => true,
            'csrf' => $this->session->csrf(),
            'error' => $error,
        ]), $status, $this->headers($headers));
    }

    private function usage(array $disk, array $totals): array
    {
        $active = max(0, $totals['bytes'] - $totals['stale_bytes']);
        $stale = $totals['stale_bytes'];
        $other = max(0, $disk['bytes'] - $totals['bytes']);

        return [
            'active' => $active,
            'stale' => $stale,
            'other' => $other,
            'sum' => $active + $stale + $other,
        ];
    }

    private function listUrl(array $source): string
    {
        $params = [];
        $query = $this->text($source['q'] ?? null, 100);
        if ($query !== '') {
            $params['q'] = $query;
        }
        if (is_string($source['sort'] ?? null) && isset(FileRepository::SORTS[$source['sort']])) {
            $params['sort'] = $source['sort'];
        }
        if (in_array($source['dir'] ?? null, ['asc', 'desc'], true)) {
            $params['dir'] = $source['dir'];
        }
        $page = $this->pageNumber($source['page'] ?? null);
        if ($page > 1) {
            $params['page'] = $page;
        }

        return '/admin' . ($params === [] ? '' : '?' . http_build_query($params));
    }

    private function text(mixed $value, int $max): string
    {
        return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
    }

    private function sortKey(mixed $value): string
    {
        return is_string($value) && isset(FileRepository::SORTS[$value]) ? $value : 'created';
    }

    private function pageNumber(mixed $value): int
    {
        return is_string($value) && ctype_digit($value) && strlen($value) < 9 ? max(1, (int) $value) : 1;
    }
}