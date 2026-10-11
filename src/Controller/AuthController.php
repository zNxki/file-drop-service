<?php
declare(strict_types=1);

namespace FileDrop\Controller;

use FileDrop\Http\Request;
use FileDrop\Http\Response;
use FileDrop\Model\User;
use FileDrop\Repository\LoginAttemptRepository;
use FileDrop\Repository\UserRepository;
use FileDrop\Service\FileService;
use FileDrop\Support\{AdminSession, Config, Totp, View};

final class AuthController extends PanelController
{
    private const MAX_FAILED_LOGINS = 5;
    private const LOGIN_WINDOW = 900;
    private const MAX_DELAY_MICROSECONDS = 3_000_000;
    private const MAX_FAILED_2FA_GLOBAL = 20;
    private const GLOBAL_2FA_KEY = '2fa:global';
    private const DUMMY_HASH = '$2y$10$LHngar9n3u2fYHUkzNWoueq6RqOhjho9hy7n0EHHHZkl/Zy.7PSsW';

    public function __construct(
        FileService $files,
        Config $config,
        AdminSession $session,
        View $view,
        UserRepository $users,
        private readonly LoginAttemptRepository $attempts,
    ) {
        parent::__construct($files, $config, $session, $view, $users);
    }

    public function setupForm(Request $request): Response
    {
        $this->open($request);
        if ($this->users->count() > 0) {
            return Response::redirect('/admin/login');
        }

        return $this->renderSetup();
    }

    public function setup(Request $request): Response
    {
        $this->open($request);
        if ($this->users->count() > 0) {
            return Response::redirect('/admin/login');
        }
        if (!$this->sameOrigin($request)) {
            return $this->renderSetup('Richiesta non valida.', '', 403);
        }

        $username = $this->text($request->post['username'] ?? null, 32);
        if (!$this->session->validCsrf($request->post['csrf'] ?? null)) {
            return $this->renderSetup('La pagina è scaduta. Riprova.', $username, 400);
        }

        $error = $this->usernameError($username)
            ?? $this->passwordError($request->post['password'] ?? null, $request->post['confirm'] ?? null);
        if ($error !== null) {
            return $this->renderSetup($error, $username, 422);
        }

        $now = time();
        $id = $this->users->createAdmin($username, $this->hash($request->post['password']), $now);
        $created = $id === null ? null : $this->users->find($id);
        if ($created === null) {
            return Response::redirect('/admin/login');
        }

        $this->users->touchLogin($created->id, $now);
        $this->session->login($created->id, $created->authStamp());
        $this->session->flash('ok', 'Account amministratore creato.');
        $this->audit('setup_ok', $request, $created->username);

        return Response::redirect('/admin');
    }

    public function loginForm(Request $request): Response
    {
        $this->open($request);
        if ($this->users->count() === 0) {
            return Response::redirect('/admin/setup');
        }
        if ($this->session->isAuthenticated()) {
            return Response::redirect('/admin');
        }

        return $this->renderLogin();
    }

    public function login(Request $request): Response
    {
        $this->open($request);
        if ($this->users->count() === 0) {
            return Response::redirect('/admin/setup');
        }
        if (!$this->sameOrigin($request)) {
            return $this->renderLogin('Richiesta non valida.', '', 403);
        }

        $username = $this->text($request->post['username'] ?? null, 32);
        if (!$this->session->validCsrf($request->post['csrf'] ?? null)) {
            return $this->renderLogin('La pagina è scaduta. Riprova.', $username, 400);
        }

        $ip = $request->ip();
        $now = time();
        $since = $now - self::LOGIN_WINDOW;
        if ($this->attempts->countSince($ip, $since) >= self::MAX_FAILED_LOGINS) {
            $wait = max(1, ($this->attempts->oldestSince($ip, $since) ?? $now) + self::LOGIN_WINDOW - $now);
            $this->audit('login_locked', $request);

            return $this->renderLogin(
                sprintf('Troppi tentativi falliti. Riprova tra %d minuti.', (int) ceil($wait / 60)),
                $username,
                429,
                ['Retry-After' => (string) $wait]
            );
        }

        $password = $request->post['password'] ?? null;
        $candidate = $username === '' ? null : $this->users->findByUsername($username);
        $passwordOk = is_string($password) && $password !== '' && strlen($password) <= self::MAX_PASSWORD
            && password_verify($password, $candidate?->passwordHash ?? self::DUMMY_HASH)
            && $candidate !== null;

        if ($passwordOk && $candidate->active) {
            if ($candidate->hasTotp()) {
                $this->session->startTwoFactor($candidate->id);
                $this->audit('login_password_ok', $request, $candidate->username);

                return Response::redirect('/admin/2fa');
            }

            return $this->finishLogin($candidate, $request, $ip);
        }

        $this->attempts->record($ip, $now);
        $failures = $this->attempts->countSince($ip, $since);
        usleep(min(self::MAX_DELAY_MICROSECONDS, 300_000 * 2 ** max(0, $failures - 1)));
        $this->audit('login_failed', $request, $username);

        return $this->renderLogin('Nome utente o password errati.', $username, 403);
    }

    public function twoFactorForm(Request $request): Response
    {
        $this->open($request);
        if ($this->session->isAuthenticated()) {
            return Response::redirect('/admin');
        }
        if (!$this->session->pendingTwoFactor()) {
            return Response::redirect('/admin/login');
        }

        return $this->renderTwoFactor();
    }

    public function twoFactor(Request $request): Response
    {
        $this->open($request);
        if (!$this->session->pendingTwoFactor()) {
            return Response::redirect('/admin/login');
        }
        if (!$this->sameOrigin($request)) {
            return $this->renderTwoFactor('Richiesta non valida.', 403);
        }
        if (!$this->session->validCsrf($request->post['csrf'] ?? null)) {
            return $this->renderTwoFactor('La pagina è scaduta. Riprova.', 400);
        }

        $candidate = $this->users->find($this->session->pendingUserId());
        if ($candidate === null || !$candidate->active || !$candidate->hasTotp()) {
            $this->session->cancelTwoFactor();

            return Response::redirect('/admin/login');
        }

        $ip = $request->ip();
        $now = time();
        $since = $now - self::LOGIN_WINDOW;
        $ipLocked = $this->attempts->countSince($ip, $since) >= self::MAX_FAILED_LOGINS;
        $globalLocked = $this->attempts->countSince(self::GLOBAL_2FA_KEY, $since) >= self::MAX_FAILED_2FA_GLOBAL;
        if ($ipLocked || $globalLocked) {
            $key = $ipLocked ? $ip : self::GLOBAL_2FA_KEY;
            $wait = max(1, ($this->attempts->oldestSince($key, $since) ?? $now) + self::LOGIN_WINDOW - $now);
            $this->audit('2fa_locked', $request, $candidate->username);

            return $this->renderTwoFactor(
                sprintf('Troppi tentativi falliti. Riprova tra %d minuti.', (int) ceil($wait / 60)),
                429,
                ['Retry-After' => (string) $wait]
            );
        }

        $code = $request->post['code'] ?? null;
        $step = is_string($code) && strlen($code) <= 16
            ? Totp::verify((string) $candidate->totpSecret, $code, $now)
            : null;

        if ($step !== null && $this->users->claimTotpStep($candidate->id, $step)) {
            return $this->finishLogin($candidate, $request, $ip);
        }

        $this->attempts->record($ip, $now);
        $this->attempts->record(self::GLOBAL_2FA_KEY, $now);
        $remaining = $this->session->twoFactorFailed();
        usleep(500_000);
        $this->audit($step === null ? '2fa_failed' : '2fa_replay', $request, $candidate->username);

        if ($remaining === 0) {
            $this->session->cancelTwoFactor();

            return $this->renderLogin('Troppi codici errati. Inserisci di nuovo le credenziali.', $candidate->username, 403);
        }

        return $this->renderTwoFactor('Codice non valido o già utilizzato.', 403);
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

    private function finishLogin(User $user, Request $request, string $ip): Response
    {
        $fresh = $this->users->find($user->id) ?? $user;
        $this->attempts->clear($ip);
        $this->users->touchLogin($fresh->id, time());
        $this->session->login($fresh->id, $fresh->authStamp());
        $this->audit('login_ok', $request, $fresh->username);

        return Response::redirect('/admin');
    }

    private function renderSetup(?string $error = null, string $username = '', int $status = 200): Response
    {
        return $this->plain('admin/setup', 'Configurazione iniziale', [
            'error' => $error,
            'username' => $username !== '' ? $username : 'admin',
            'minPassword' => self::MIN_PASSWORD,
        ], $status);
    }

    private function renderLogin(?string $error = null, string $username = '', int $status = 200, array $headers = []): Response
    {
        return $this->plain('admin/login', 'Accesso', ['error' => $error, 'username' => $username], $status, $headers);
    }

    private function renderTwoFactor(?string $error = null, int $status = 200, array $headers = []): Response
    {
        return $this->plain('admin/twofactor', 'Verifica in due passaggi', ['error' => $error], $status, $headers);
    }
}