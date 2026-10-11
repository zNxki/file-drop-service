<?php
$labels = \FileDrop\Support\Permissions::LABELS;
?>
<section class="admin-head">
    <h1>Account</h1>
</section>

<section class="card panel">
    <h2>Nuovo account</h2>

    <?php if (!empty($error)): ?>
        <p class="flash flash-error" role="alert"><?= $this->e($error) ?></p>
    <?php endif; ?>

    <form class="form-grid" method="post" action="/admin/users">
        <input type="hidden" name="csrf" value="<?= $this->e($csrf) ?>">

        <div class="field">
            <label for="new-username">Nome utente</label>
            <input id="new-username" type="text" name="username" required maxlength="32" autocomplete="off"
                autocapitalize="none" spellcheck="false" value="<?= $this->e($form['username']) ?>">
        </div>
        <div class="field">
            <label for="new-password">Password</label>
            <input id="new-password" type="password" name="password" required minlength="10" maxlength="1024"
                autocomplete="new-password">
        </div>
        <div class="field">
            <label for="new-confirm">Ripeti la password</label>
            <input id="new-confirm" type="password" name="confirm" required minlength="10" maxlength="1024"
                autocomplete="new-password">
        </div>

        <fieldset class="perm-group">
            <legend>Permessi</legend>
            <?php foreach ($assignable as $permission): ?>
                <label class="perm">
                    <input type="checkbox" name="permissions[]" value="<?= $this->e($permission) ?>"
                        <?= in_array($permission, $form['permissions'], true) ? 'checked' : '' ?>>
                    <span><?= $this->e($labels[$permission]) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <button class="btn btn-small" type="submit">Crea account</button>
    </form>
    <p class="hint">Ogni account può attivare la verifica in due passaggi dalle proprie impostazioni.</p>
</section>

<section class="card panel">
    <h2>Account esistenti <span class="hint">(<?= $this->number(count($accounts)) ?>)</span></h2>
    <div class="table-wrap">
        <table class="files">
            <thead>
                <tr>
                    <th scope="col">Nome utente</th>
                    <th scope="col">Permessi</th>
                    <th scope="col">2FA</th>
                    <th scope="col">Ultimo accesso</th>
                    <th scope="col"><span class="visually-hidden">Azioni</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $account): ?>
                    <tr>
                        <td class="cell-name">
                            <?= $this->e($account->username) ?>
                            <?php if ($account->isAdmin): ?>
                                <span class="badge">Amministratore</span>
                            <?php endif; ?>
                            <?php if (!$account->active): ?>
                                <span class="badge badge-stale">Disattivato</span>
                            <?php endif; ?>
                            <?php if ($account->id === $user->id): ?>
                                <span class="badge">Tu</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($account->isAdmin): ?>
                                <span class="badge badge-ok">Tutti</span>
                            <?php elseif ($account->permissions === []): ?>
                                <span class="hint">Nessuno</span>
                            <?php else: ?>
                                <?php foreach ($account->permissions as $permission): ?>
                                    <span class="badge"><?= $this->e($labels[$permission]) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td><?= $account->hasTotp() ? '<span class="badge badge-ok">Attiva</span>' : '<span class="hint">No</span>' ?>
                        </td>
                        <td><?= $account->lastLoginAt === null ? 'Mai' : $this->time($account->lastLoginAt) ?></td>
                        <td>
                            <?php if (!$account->isAdmin && $account->id !== $user->id && ($user->isAdmin || !$account->can(\FileDrop\Support\Permissions::USERS_MANAGE))): ?>
                                <a class="link" href="/admin/users/edit?id=<?= (int) $account->id ?>">Modifica</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>