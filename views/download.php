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
            <?php if ($file->maxDownloads === 1): ?>
                <strong>Si elimina dopo il primo download:</strong> il codice smette di funzionare appena parte.
            <?php elseif ($file->maxDownloads !== null): ?>
                Si elimina dopo <?= (int) $file->maxDownloads ?> download (<?= (int) $file->remainingDownloads() ?>
                rimasti).
            <?php endif; ?>
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

        <div class="share-qr" id="share-qr" data-url="<?= $this->e($shareUrl) ?>" hidden>
            <div class="qr" id="share-qr-code"></div>
            <p>
                <strong>Aprilo sul telefono</strong><br>
                Inquadra il codice con la fotocamera per passare questo link al telefono.
            </p>
        </div>
    </div>
</section>
<?php if (($kind = $file->previewKind()) !== null):
    $src = '/d/' . rawurlencode($file->code) . '/preview'; ?>
    <section class="preview" aria-label="Anteprima">
        <?php if ($kind === 'image'): ?>
            <img src="<?= $this->e($src) ?>" alt="Anteprima di <?= $this->e($file->originalName) ?>">
        <?php elseif ($kind === 'video'): ?>
            <video src="<?= $this->e($src) ?>" controls preload="metadata" playsinline></video>
        <?php elseif ($kind === 'text'): ?>
            <pre class="preview-text" id="preview-text" data-src="<?= $this->e($src) ?>" data-size="<?= (int) $file->size ?>"
                hidden></pre>
            <p class="preview-note" id="preview-note" hidden>Anteprima limitata ai primi 64 KB.</p>
        <?php else: ?>
            <a class="btn btn-small" href="<?= $this->e($src) ?>" target="_blank" rel="noopener">Apri anteprima PDF</a>
        <?php endif; ?>
    </section>
<?php endif; ?>
<script src="/assets/vendor/qrcode.js" defer></script>