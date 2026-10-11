<section class="admin-head">
    <h1>Impostazioni</h1>
</section>

<section class="card panel">
    <h2>Account</h2>
    <dl class="meta">
        <div>
            <dt>Nome utente</dt>
            <dd><?= $this->e($user->username) ?></dd>
        </div>
        <div>
            <dt>Ruolo</dt>
            <dd><?= $user->isAdmin ? 'Amministratore' : 'Account' ?></dd>
        </div>
        <div>
            <dt>Creato</dt>
            <dd><?= $this->time($user->createdAt) ?></dd>
        </div>
    </dl>
</section>

<section class="card panel" id="password">
    <h2>Cambia password</h2>
    <form class="form-grid" method="post" action="/admin/settings/password">
        <input type="hidden" name="csrf" value="<?= $this->e($csrf) ?>">
        <div class="field">
            <label for="current">Password attuale</label>
            <input id="current" type="password" name="current" required autocomplete="current-password"
                maxlength="1024">
        </div>
        <div class="field">
            <label for="new-password">Nuova password</label>
            <input id="new-password" type="password" name="password" required minlength="<?= (int) $minPassword ?>"
                maxlength="1024" autocomplete="new-password">
        </div>
        <div class="field">
            <label for="new-confirm">Ripeti la nuova password</label>
            <input id="new-confirm" type="password" name="confirm" required minlength="<?= (int) $minPassword ?>"
                maxlength="1024" autocomplete="new-password">
        </div>
        <button class="btn btn-small" type="submit">Aggiorna password</button>
    </form>
    <p class="hint">Almeno <?= (int) $minPassword ?> caratteri. Le altre sessioni aperte con questo account vengono
        chiuse.</p>
</section>

<section class="card panel" id="2fa">
    <h2>Verifica in due passaggi</h2>

    <?php if ($user->hasTotp()): ?>
        <p>Stato: <span class="badge badge-ok">Attiva</span></p>
        <p class="hint">Per disattivarla conferma con la password e un codice generato dall'app.</p>
        <form class="form-grid" method="post" action="/admin/settings/2fa/disable">
            <input type="hidden" name="csrf" value="<?= $this->e($csrf) ?>">
            <div class="field">
                <label for="off-password">Password</label>
                <input id="off-password" type="password" name="password" required autocomplete="current-password"
                    maxlength="1024">
            </div>
            <div class="field">
                <label for="off-code">Codice</label>
                <input id="off-code" type="text" name="code" required inputmode="numeric" pattern="[0-9 ]*"
                    autocomplete="one-time-code" maxlength="16">
            </div>
            <button class="btn btn-small btn-secondary" type="submit">Disattiva 2FA</button>
        </form>
    <?php elseif ($setupSecret === null): ?>
        <p>Stato: <span class="badge">Non attiva</span></p>
        <p class="hint">Aggiungi un secondo livello di sicurezza: oltre alla password servirà un codice a 6 cifre generato
            da un'app di autenticazione (Google Authenticator, Aegis, 1Password, Bitwarden…).</p>
        <form method="post" action="/admin/settings/2fa/start">
            <input type="hidden" name="csrf" value="<?= $this->e($csrf) ?>">
            <button class="btn btn-small" type="submit">Attiva 2FA</button>
        </form>
    <?php else: ?>
        <p>Stato: <span class="badge">Configurazione in corso</span></p>
        <ol class="twofa-steps">
            <li>Nell'app di autenticazione scegli «Aggiungi account» e inquadra il QR code.</li>
            <li>Se non puoi usare la fotocamera scegli «Inserisci chiave» e incolla il token qui sotto.</li>
            <li>Inserisci il codice a 6 cifre mostrato dall'app per completare l'attivazione.</li>
        </ol>

        <div class="twofa-setup">
            <div class="qr" data-otpauth="<?= $this->e($setupUri) ?>" role="img"
                aria-label="QR code per la verifica in due passaggi"></div>
            <div class="twofa-token">
                <span class="field-label">Nome nell'app</span>
                <code><?= $this->e($setupLabel) ?></code>

                <span class="field-label">Token (chiave segreta)</span>
                <code class="token" id="totp-secret"><?= $this->e(implode(' ', str_split($setupSecret, 4))) ?></code>
                <button class="btn btn-small btn-secondary" type="button" data-copy="<?= $this->e($setupSecret) ?>">Copia
                    token</button>
                <p class="hint">Tipo: basato sul tempo · SHA1 · 6 cifre · 30 secondi. Il token è una credenziale: non
                    condividerlo.</p>
            </div>
        </div>

        <form class="form-grid" method="post" action="/admin/settings/2fa/enable">
            <input type="hidden" name="csrf" value="<?= $this->e($csrf) ?>">
            <div class="field">
                <label for="on-code">Codice di verifica</label>
                <input id="on-code" type="text" name="code" required inputmode="numeric" pattern="[0-9 ]*"
                    autocomplete="one-time-code" maxlength="16">
            </div>
            <button class="btn btn-small" type="submit">Conferma e attiva</button>
        </form>
        <form method="post" action="/admin/settings/2fa/cancel">
            <input type="hidden" name="csrf" value="<?= $this->e($csrf) ?>">
            <button class="link" type="submit">Annulla</button>
        </form>
    <?php endif; ?>
</section>