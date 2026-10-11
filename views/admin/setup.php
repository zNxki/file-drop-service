<section class="login">
    <div class="card login-card">
        <svg class="login-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="4" y="10.5" width="16" height="10" rx="2.5"></rect>
            <path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"></path>
            <circle cx="12" cy="15.5" r="1.2" fill="currentColor" stroke="none"></circle>
        </svg>
        <h1>Configurazione iniziale</h1>
        <p class="login-lead">Scegli il nome utente e la master password dell'amministratore. Potrai creare gli altri
            account dal pannello.</p>

        <form class="login-form" method="post" action="/admin/setup">
            <input type="hidden" name="csrf" value="<?= $this->e($csrf) ?>">

            <div class="field">
                <label for="username">Nome utente</label>
                <input id="username" type="text" name="username" required autofocus autocomplete="username"
                    maxlength="32" value="<?= $this->e($username) ?>" autocapitalize="none" spellcheck="false">
            </div>

            <div class="field">
                <label for="password">Master password</label>
                <div class="password-wrap">
                    <input id="password" type="password" name="password" required autocomplete="new-password"
                        minlength="<?= (int) $minPassword ?>" maxlength="1024">
                    <button class="password-toggle" type="button" data-toggle-password aria-controls="password"
                        aria-pressed="false">Mostra</button>
                </div>
            </div>

            <div class="field">
                <label for="confirm">Ripeti la password</label>
                <input id="confirm" type="password" name="confirm" required autocomplete="new-password"
                    minlength="<?= (int) $minPassword ?>" maxlength="1024" <?= !empty($error) ? ' aria-invalid="true" aria-describedby="login-error"' : '' ?>>
            </div>

            <p class="login-hint">Almeno <?= (int) $minPassword ?> caratteri.</p>

            <?php if (!empty($error)): ?>
                <p class="login-error" id="login-error" role="alert"><?= $this->e($error) ?></p>
            <?php endif; ?>

            <button class="btn form-submit" type="submit">Crea amministratore</button>
        </form>
    </div>
</section>