<?php
$labels = \FileDrop\Support\Permissions::LABELS;
?>
<section class="admin-head">
    <h1>Modifica «<?= $this->e($target->username) ?>»</h1>
    <a class="link" href="/admin/users">← Tutti gli account</a>
</section>

<section class="card panel">
    <h2>Accesso e permessi</h2>
    <form class="form-grid" method="post" action="/admin/users/update">
        <input type="hidden" name="csrf" value="<?= $this->e($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int) $target->id ?>">

        <label class="perm">
            <input type="checkbox" name="active" value="1" <?= $target->active ? 'checked' : '' ?>>
            <span>Account attivo</span>
        </label>

        <fieldset class="perm-group">
            <legend>Permessi</legend>
            <?php foreach ($assignable as $permission): ?>
                <label class="perm">
                    <input type="checkbox" name="permissions[]" value="<?= $this->e($permission) ?>"
                        <?= in_array($permission, $target->permissions, true) ? 'checked' : '' ?>>
                    <span><?= $this->e($labels[$permission]) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <div class="field">
            <label for="edit-password">Nuova password <span class="hint">(lascia vuoto per non cambiarla)</span></label>
            <input id="edit-password" type="password" name="password" minlength="10" maxlength="1024"
                autocomplete="new-password">
        </div>
        <div class="field">
            <label for="edit-confirm">Ripeti la nuova password</label>
            <input id="edit-confirm" type="password" name="confirm" minlength="10" maxlength="1024"
                autocomplete="new-password">
        </div>

        <button class="btn btn-small" type="submit">Salva modifiche</button>
    </form>
</section>

<section class="card panel">
    <h2>Verifica in due passaggi</h2>
    <p>Stato:
        <?= $target->hasTotp() ? '<span class="badge badge-ok">Attiva</span>' : '<span class="badge">Non attiva</span>' ?>
    </p>
    <?php if ($target->hasTotp()): ?>
        <form method="post" action="/admin/users/reset-2fa"
            data-confirm="Disattivare la verifica in due passaggi per «<?= $this->e($target->username) ?>»?">
            <input type="hidden" name="csrf" value="<?= $this->e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int) $target->id ?>">
            <button class="btn btn-small btn-secondary" type="submit">Disattiva 2FA</button>
        </form>
    <?php endif; ?>
</section>

<section class="card panel">
    <h2>Elimina account</h2>
    <p class="hint">L'account viene rimosso subito e le sue sessioni si chiudono. L'operazione non si può annullare.</p>
    <form method="post" action="/admin/users/delete"
        data-confirm="Eliminare l'account «<?= $this->e($target->username) ?>»?">
        <input type="hidden" name="csrf" value="<?= $this->e($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int) $target->id ?>">
        <button class="btn btn-small danger-btn" type="submit">Elimina account</button>
    </form>
</section>