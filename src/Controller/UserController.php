<?php
declare(strict_types=1);

namespace FileDrop\Controller;

use FileDrop\Http\Request;
use FileDrop\Http\Response;
use FileDrop\Model\User;
use FileDrop\Support\Permissions;

final class UserController extends PanelController
{
    public function index(Request $request): Response
    {
        if (($denied = $this->guard($request, Permissions::USERS_MANAGE)) !== null) {
            return $denied;
        }

        return $this->page('admin/users', 'Account', 'users', [
            'accounts' => $this->users->all(),
            'assignable' => $this->assignable(),
            'form' => ['username' => '', 'permissions' => []],
        ]);
    }

    public function create(Request $request): Response
    {
        if (($denied = $this->guard($request, Permissions::USERS_MANAGE)) !== null) {
            return $denied;
        }
        if (!$this->validPost($request)) {
            return Response::redirect('/admin/users');
        }

        $username = $this->text($request->post['username'] ?? null, 32);
        $permissions = array_values(array_intersect($this->assignable(), Permissions::normalize($request->post['permissions'] ?? [])));
        $error = $this->usernameError($username)
            ?? $this->passwordError($request->post['password'] ?? null, $request->post['confirm'] ?? null);

        if ($error === null && $this->users->create($username, $this->hash($request->post['password']), $permissions, time()) === null) {
            $error = 'Esiste già un account con questo nome utente.';
        }
        if ($error !== null) {
            return $this->page('admin/users', 'Account', 'users', [
                'accounts' => $this->users->all(),
                'assignable' => $this->assignable(),
                'form' => ['username' => $username, 'permissions' => $permissions],
                'error' => $error,
            ], 422);
        }

        error_log(sprintf('[admin-users] created user=%s by=%s', $username, $this->user->username));
        $this->session->flash('ok', sprintf('Account «%s» creato.', $username));

        return Response::redirect('/admin/users');
    }

    public function edit(Request $request): Response
    {
        if (($denied = $this->guard($request, Permissions::USERS_MANAGE)) !== null) {
            return $denied;
        }

        $target = $this->target($request->query['id'] ?? null);
        if ($target === null) {
            return Response::redirect('/admin/users');
        }

        return $this->page('admin/user-edit', 'Modifica ' . $target->username, 'users', [
            'target' => $target,
            'assignable' => $this->assignable(),
        ]);
    }

    public function update(Request $request): Response
    {
        if (($denied = $this->guard($request, Permissions::USERS_MANAGE)) !== null) {
            return $denied;
        }
        if (!$this->validPost($request)) {
            return Response::redirect('/admin/users');
        }

        $target = $this->target($request->post['id'] ?? null);
        if ($target === null) {
            return Response::redirect('/admin/users');
        }
        $back = Response::redirect('/admin/users/edit?id=' . $target->id);

        $newPassword = $request->post['password'] ?? '';
        if (is_string($newPassword) && $newPassword !== '') {
            $error = $this->passwordError($newPassword, $request->post['confirm'] ?? null);
            if ($error !== null) {
                $this->session->flash('error', $error);

                return $back;
            }
            $this->users->setPassword($target->id, $this->hash($newPassword));
        }

        $permissions = array_values(array_intersect($this->assignable(), Permissions::normalize($request->post['permissions'] ?? [])));
        if (!$this->user->isAdmin && $target->can(Permissions::USERS_MANAGE)) {
            $permissions[] = Permissions::USERS_MANAGE;
        }
        $this->users->updateAccess($target->id, array_values(array_unique($permissions)), !empty($request->post['active']));

        error_log(sprintf('[admin-users] updated user=%s by=%s', $target->username, $this->user->username));
        $this->session->flash('ok', sprintf('Account «%s» aggiornato.', $target->username));

        return $back;
    }

    public function resetTwoFactor(Request $request): Response
    {
        if (($denied = $this->guard($request, Permissions::USERS_MANAGE)) !== null) {
            return $denied;
        }
        $target = $this->validPost($request) ? $this->target($request->post['id'] ?? null) : null;
        if ($target === null) {
            return Response::redirect('/admin/users');
        }

        $this->users->setTotp($target->id, null);
        error_log(sprintf('[admin-users] 2fa reset user=%s by=%s', $target->username, $this->user->username));
        $this->session->flash('ok', sprintf('Verifica in due passaggi disattivata per «%s».', $target->username));

        return Response::redirect('/admin/users/edit?id=' . $target->id);
    }

    public function delete(Request $request): Response
    {
        if (($denied = $this->guard($request, Permissions::USERS_MANAGE)) !== null) {
            return $denied;
        }
        $target = $this->validPost($request) ? $this->target($request->post['id'] ?? null) : null;
        if ($target === null) {
            return Response::redirect('/admin/users');
        }

        if ($this->users->delete($target->id)) {
            error_log(sprintf('[admin-users] deleted user=%s by=%s', $target->username, $this->user->username));
            $this->session->flash('ok', sprintf('Account «%s» eliminato.', $target->username));
        }

        return Response::redirect('/admin/users');
    }

    private function assignable(): array
    {
        return $this->user->isAdmin
            ? Permissions::all()
            : array_values(array_diff(Permissions::all(), [Permissions::USERS_MANAGE]));
    }

    private function target(mixed $id): ?User
    {
        $target = is_string($id) && ctype_digit($id) && strlen($id) < 10 ? $this->users->find((int) $id) : null;

        $allowed = $target !== null
            && !$target->isAdmin
            && $target->id !== $this->user->id
            && ($this->user->isAdmin || !$target->can(Permissions::USERS_MANAGE));

        if (!$allowed) {
            $this->session->flash('error', 'Account non trovato o non modificabile da qui.');

            return null;
        }

        return $target;
    }
}