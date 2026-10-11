<?php
declare(strict_types=1);

namespace FileDrop\Controller;

use FileDrop\Http\Request;
use FileDrop\Http\Response;
use FileDrop\Support\Totp;

final class SettingsController extends PanelController
{
    public function show(Request $request): Response
    {
        if (($denied = $this->guard($request)) !== null) {
            return $denied;
        }

        $secret = $this->user->hasTotp() ? null : $this->session->setupSecret();

        return $this->page('admin/settings', 'Impostazioni', 'settings', [
            'minPassword' => self::MIN_PASSWORD,
            'setupSecret' => $secret,
            'setupUri' => $secret === null ? null : Totp::uri($secret, $this->user->username),
            'setupLabel' => Totp::ISSUER . ': ' . $this->user->username,
            'extraScripts' => ['/assets/js/admin.js'],
        ]);
    }

    public function password(Request $request): Response
    {
        if (($denied = $this->guard($request)) !== null) {
            return $denied;
        }
        $back = Response::redirect('/admin/settings#password');
        if (!$this->validPost($request)) {
            return $back;
        }

        $current = $request->post['current'] ?? null;
        if (!is_string($current) || strlen($current) > self::MAX_PASSWORD || !password_verify($current, $this->user->passwordHash)) {
            usleep(500_000);
            $this->session->flash('error', 'La password attuale non è corretta.');

            return $back;
        }

        $error = $this->passwordError($request->post['password'] ?? null, $request->post['confirm'] ?? null);
        if ($error !== null) {
            $this->session->flash('error', $error);

            return $back;
        }

        $this->users->setPassword($this->user->id, $this->hash($request->post['password']));
        $this->restamp();
        $this->audit('password_changed', $request, $this->user->username);
        $this->session->flash('ok', 'Password aggiornata. Le altre sessioni sono state chiuse.');

        return $back;
    }

    public function twoFactorStart(Request $request): Response
    {
        if (($denied = $this->guard($request)) !== null) {
            return $denied;
        }
        if ($this->validPost($request)) {
            if ($this->user->hasTotp()) {
                $this->session->flash('error', 'La verifica in due passaggi è già attiva.');
            } else {
                $this->session->storeSetupSecret(Totp::generateSecret());
            }
        }

        return Response::redirect('/admin/settings#2fa');
    }

    public function twoFactorCancel(Request $request): Response
    {
        if (($denied = $this->guard($request)) !== null) {
            return $denied;
        }
        if ($this->validPost($request)) {
            $this->session->clearSetupSecret();
        }

        return Response::redirect('/admin/settings#2fa');
    }

    public function twoFactorEnable(Request $request): Response
    {
        if (($denied = $this->guard($request)) !== null) {
            return $denied;
        }
        $back = Response::redirect('/admin/settings#2fa');
        if (!$this->validPost($request)) {
            return $back;
        }

        $secret = $this->session->setupSecret();
        if ($this->user->hasTotp() || $secret === null) {
            return $back;
        }

        $code = $request->post['code'] ?? null;
        $step = is_string($code) && strlen($code) <= 16 ? Totp::verify($secret, $code) : null;
        if ($step === null) {
            usleep(500_000);
            $this->session->flash('error', 'Codice non valido. Controlla l\'ora del telefono e riprova.');

            return $back;
        }

        $this->users->setTotp($this->user->id, $secret, $step);
        $this->session->clearSetupSecret();
        $this->restamp();
        $this->audit('2fa_enabled', $request, $this->user->username);
        $this->session->flash('ok', 'Verifica in due passaggi attivata.');

        return $back;
    }

    public function twoFactorDisable(Request $request): Response
    {
        if (($denied = $this->guard($request)) !== null) {
            return $denied;
        }
        $back = Response::redirect('/admin/settings#2fa');
        if (!$this->validPost($request) || !$this->user->hasTotp()) {
            return $back;
        }

        $password = $request->post['password'] ?? null;
        $code = $request->post['code'] ?? null;
        $passwordOk = is_string($password) && strlen($password) <= self::MAX_PASSWORD && password_verify($password, $this->user->passwordHash);
        $step = is_string($code) && strlen($code) <= 16 ? Totp::verify((string) $this->user->totpSecret, $code) : null;

        if (!$passwordOk || $step === null || !$this->users->claimTotpStep($this->user->id, $step)) {
            usleep(500_000);
            $this->session->flash('error', 'Password o codice non validi.');

            return $back;
        }

        $this->users->setTotp($this->user->id, null);
        $this->restamp();
        $this->audit('2fa_disabled', $request, $this->user->username);
        $this->session->flash('ok', 'Verifica in due passaggi disattivata.');

        return $back;
    }

    private function restamp(): void
    {
        $fresh = $this->users->find($this->user->id);
        if ($fresh !== null) {
            $this->session->restamp($fresh->authStamp());
        }
    }
}