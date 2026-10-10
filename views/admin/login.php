<section class="login">
    <div class="card login-card">
        <svg class="login-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="4" y="10.5" width="16" height="10" rx="2.5"></rect>
            <path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"></path>
            <circle cx="12" cy="15.5" r="1.2" fill="currentColor" stroke="none"></circle>
        </svg>
        <h1>Amministrazione</h1>
        <p class="login-lead">Accedi per gestire i file caricati e consultare lo spazio occupato e i download.</p>

        <form class="login-form" method="post" action="/admin/login">
            <input type="hidden" name="csrf" value="<?= $this->e($csrf) ?>">

            <div class="field">
                <label for="password">Password</label>
                <div class="password-wrap">
                    <input id="password" type="password" name="password" required autofocus
                        autocomplete="current-password" maxlength="1024" <?= !empty($error) ? ' aria-invalid="true" aria-describedby="login-error"' : '' ?>>
                    <button class="password-toggle" type="button" data-toggle-password aria-controls="password"
                        aria-pressed="false">Mostra</button>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <p class="login-error" id="login-error" role="alert"><?= $this->e($error) ?></p>
            <?php endif; ?>

            <button class="btn form-submit" type="submit">Accedi</button>
        </form>
    </div>
    <a class="login-back" href="/">← Torna alla home</a>
</section>