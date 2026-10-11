<section class="login">
    <div class="card login-card">
        <svg class="login-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 3l7 3v5c0 4.5-3 8.2-7 10-4-1.8-7-5.5-7-10V6z"></path>
            <path d="M9 12l2 2 4-4"></path>
        </svg>
        <h1>Verifica in due passaggi</h1>
        <p class="login-lead">Inserisci il codice a 6 cifre mostrato dalla tua app di autenticazione.</p>

        <form class="login-form" method="post" action="/admin/2fa">
            <input type="hidden" name="csrf" value="<?= $this->e($csrf) ?>">

            <div class="field">
                <label for="code">Codice</label>
                <input id="code" type="text" name="code" required autofocus inputmode="numeric" pattern="[0-9 ]*"
                    autocomplete="one-time-code" maxlength="16" <?= !empty($error) ? ' aria-invalid="true" aria-describedby="login-error"' : '' ?>>
            </div>

            <?php if (!empty($error)): ?>
                <p class="login-error" id="login-error" role="alert"><?= $this->e($error) ?></p>
            <?php endif; ?>

            <button class="btn form-submit" type="submit">Verifica</button>
        </form>
    </div>
    <a class="login-back" href="/admin/login">← Torna alla password</a>
</section>