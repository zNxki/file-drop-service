<?php
declare(strict_types=1);

namespace FileDrop\Support;

final class AdminSession
{
    private const IDLE_TIMEOUT = 1800;
    private const ABSOLUTE_TIMEOUT = 28800;
    private const ROTATE_AFTER = 300;
    private const TWO_FACTOR_TIMEOUT = 300;
    public const TWO_FACTOR_MAX_FAILS = 5;

    private bool $secure = false;
    private string $agent = '';

    public function __construct(private readonly string $savePath)
    {
    }

    public function start(bool $secure, string $userAgent): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $this->secure = $secure;
        $this->agent = hash('sha256', $userAgent);

        if (!is_dir($this->savePath) && !mkdir($this->savePath, 0700, true) && !is_dir($this->savePath)) {
            throw new \RuntimeException("Couldn't create the session folder: {$this->savePath}");
        }

        $this->open();
    }

    public function isAuthenticated(): bool
    {
        if (empty($_SESSION['uid'])) {
            return false;
        }

        $now = time();
        $expired = $now - (int) ($_SESSION['started'] ?? 0) > self::ABSOLUTE_TIMEOUT
            || $now - (int) ($_SESSION['seen'] ?? 0) > self::IDLE_TIMEOUT
            || !hash_equals((string) ($_SESSION['agent'] ?? ''), $this->agent);

        if ($expired) {
            $this->reset();
            return false;
        }

        $_SESSION['seen'] = $now;
        if ($now - (int) ($_SESSION['rotated'] ?? 0) > self::ROTATE_AFTER) {
            session_regenerate_id(true);
            $_SESSION['rotated'] = $now;
        }

        return true;
    }

    public function userId(): int
    {
        return (int) ($_SESSION['uid'] ?? 0);
    }

    public function stamp(): string
    {
        return (string) ($_SESSION['stamp'] ?? '');
    }

    public function restamp(string $stamp): void
    {
        $_SESSION['stamp'] = $stamp;
    }

    public function startTwoFactor(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION = ['pending_2fa' => time(), 'pending_uid' => $userId, 'tf_fails' => 0, 'agent' => $this->agent];
    }

    public function pendingUserId(): int
    {
        return (int) ($_SESSION['pending_uid'] ?? 0);
    }

    public function pendingTwoFactor(): bool
    {
        $since = (int) ($_SESSION['pending_2fa'] ?? 0);
        if ($since === 0) {
            return false;
        }
        if (time() - $since > self::TWO_FACTOR_TIMEOUT || !hash_equals((string) ($_SESSION['agent'] ?? ''), $this->agent)) {
            $this->cancelTwoFactor();
            return false;
        }

        return true;
    }

    public function twoFactorFailed(): int
    {
        $_SESSION['tf_fails'] = (int) ($_SESSION['tf_fails'] ?? 0) + 1;

        return max(0, self::TWO_FACTOR_MAX_FAILS - $_SESSION['tf_fails']);
    }

    public function cancelTwoFactor(): void
    {
        unset($_SESSION['pending_2fa'], $_SESSION['pending_uid'], $_SESSION['tf_fails']);
    }

    public function setupSecret(): ?string
    {
        $secret = $_SESSION['totp_setup'] ?? null;

        return is_string($secret) ? $secret : null;
    }

    public function storeSetupSecret(string $secret): void
    {
        $_SESSION['totp_setup'] = $secret;
    }

    public function clearSetupSecret(): void
    {
        unset($_SESSION['totp_setup']);
    }

    public function login(int $userId, string $stamp): void
    {
        session_regenerate_id(true);
        $now = time();
        $_SESSION = [
            'uid' => $userId,
            'stamp' => $stamp,
            'started' => $now,
            'seen' => $now,
            'rotated' => $now,
            'agent' => $this->agent,
        ];
    }

    public function logout(): void
    {
        $params = session_get_cookie_params();
        $name = session_name();

        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        setcookie($name, '', [
            'expires' => 1,
            'path' => $params['path'],
            'secure' => $params['secure'],
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    public function csrf(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public function validCsrf(mixed $token): bool
    {
        return is_string($token)
            && isset($_SESSION['csrf'])
            && hash_equals($_SESSION['csrf'], $token);
    }

    public function flash(string $type, string $text): void
    {
        $_SESSION['flash'] = ['type' => $type, 'text' => $text];
    }

    public function pullFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        return is_array($flash) ? $flash : null;
    }

    private function reset(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_id(session_create_id());
        $this->open();
    }

    private function open(): void
    {
        session_name($this->secure ? '__Host-filedrop_sid' : 'filedrop_sid');
        session_save_path($this->savePath);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        $started = session_start([
            'use_strict_mode' => 1,
            'use_only_cookies' => 1,
            'use_trans_sid' => 0,
            'gc_maxlifetime' => self::ABSOLUTE_TIMEOUT,
            'gc_probability' => 1,
            'gc_divisor' => 50,
        ]);
        if (!$started) {
            throw new \RuntimeException("Couldn't start the session (save path: {$this->savePath})");
        }
    }
}