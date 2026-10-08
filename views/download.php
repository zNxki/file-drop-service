<section class="ticket">
    <div class="ticket-main">
        <p class="code" aria-label="Codice <?= $this->e($file->code) ?>"><?= $this->e($file->code) ?></p>
    </div>
    <div class="ticket-stub">
        <h1 class="file-name"><?= $this->e($file->originalName) ?></h1>
        <p class="file-meta">
            <?= $this->e($this->bytes($file->size)) ?>,
            scade il <time datetime="<?= $this->e(date(DATE_ATOM, $file->expiresAt)) ?>"
                data-expires><?= $this->e(date('d/m/Y H:i', $file->expiresAt)) ?> UTC</time>.
            Scaricato <?= (int) $file->downloads ?> <?= $file->downloads === 1 ? 'volta' : 'volte' ?>.
        </p>

        <?php if ($file->isProtected()): ?>
            <form method="post" action="/d/<?= $this->e($file->code) ?>/download" class="unlock">
                <label class="field">
                    <span>Questo file è protetto da password</span>
                    <input type="password" name="password" required autofocus autocomplete="off"
                        aria-invalid="<?= $error ? 'true' : 'false' ?>">
                </label>
                <?php if ($error): ?>
                    <p class="error" role="alert"><?= $this->e($error) ?></p><?php endif; ?>
                <button class="btn" type="submit">Scarica</button>
            </form>
        <?php else: ?>
            <a class="btn" href="/d/<?= $this->e($file->code) ?>/download">Scarica</a>
        <?php endif; ?>
    </div>
</section>