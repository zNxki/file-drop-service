<section class="ticket">
    <div class="ticket-main">
        <p class="code" aria-label="<?= $this->t('download.code_label', ['code' => $file->code]) ?>">
            <?= $this->e($file->code) ?></p>
    </div>
    <div class="ticket-stub">
        <h1 class="file-name"><?= $this->e($file->displayName()) ?></h1>
        <p class="file-meta">
            <?= $this->th('download.meta', ['size' => $this->e($this->bytes($file->size)), 'date' => $this->time($file->expiresAt)]) ?>
            <?= $this->tn('download.downloaded', (int) $file->downloads) ?>
            <?php if ($file->maxDownloads === 1): ?>
                <strong><?= $this->t('download.once_strong') ?></strong> <?= $this->t('download.once_rest') ?>
            <?php elseif ($file->maxDownloads !== null): ?>
                <?= $this->t('download.limited', ['max' => (int) $file->maxDownloads, 'left' => (int) $file->remainingDownloads()]) ?>
            <?php endif; ?>
        </p>

        <?php if ($file->isBundle() && $items !== []): ?>
            <ul class="item-list" aria-label="<?= $this->t('download.items') ?>">
                <?php foreach ($items as $item): ?>
                    <li>
                        <span class="item-name"><?= $this->e($item->originalName) ?></span>
                        <span class="item-size"><?= $this->e($this->bytes($item->size)) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="hint"><?= $this->t('download.bundle_hint') ?></p>
        <?php endif; ?>
        <?php
        $downloadLabel = $this->t($file->isBundle() ? 'download.button_all' : 'download.button');
        $downloadUrl = $this->url('/d/' . rawurlencode($file->code) . '/download');
        ?>

        <?php if ($file->encrypted): ?>
            <form method="post" action="<?= $this->e($downloadUrl) ?>" class="unlock" id="decrypt-form">
                <p class="hint"><?= $this->t('download.encrypted_hint') ?><?php if ($file->maxDownloads !== null): ?>
                        <?= $this->t('download.preview_counts') ?>    <?php endif; ?></p>
                <?php if ($file->isProtected()): ?>
                    <label class="field">
                        <span><?= $this->t('download.protected') ?></span>
                        <input type="password" name="password" required autocomplete="off"
                            aria-invalid="<?= $error ? 'true' : 'false' ?>">
                    </label>
                <?php endif; ?>
                <p class="error" id="decrypt-error" role="alert" <?= $error ? '' : 'hidden' ?>><?= $this->e($error ?? '') ?>
                </p>
                <div class="decrypt-actions">
                    <button class="btn" type="submit" id="decrypt-submit"><?= $this->t('download.decrypt') ?></button>
                    <button class="btn btn-secondary" type="button"
                        id="decrypt-preview-btn"><?= $this->t('download.preview') ?></button>
                </div>
                <p class="hint" id="decrypt-status" role="status"></p>
            </form>
        <?php elseif ($file->isProtected()): ?>
            <form method="post" action="<?= $this->e($downloadUrl) ?>" class="unlock">
                <label class="field">
                    <span><?= $this->t('download.protected') ?></span>
                    <input type="password" name="password" required autofocus autocomplete="off"
                        aria-invalid="<?= $error ? 'true' : 'false' ?>">
                </label>
                <?php if ($error): ?>
                    <p class="error" role="alert"><?= $this->e($error) ?></p><?php endif; ?>
                <button class="btn" type="submit"><?= $downloadLabel ?></button>
            </form>
        <?php else: ?>
            <a class="btn" href="<?= $this->e($downloadUrl) ?>"><?= $downloadLabel ?></a>
        <?php endif; ?>

        <div class="share-qr" id="share-qr" data-url="<?= $this->e($shareUrl) ?>" hidden>
            <div class="qr" id="share-qr-code"></div>
            <p>
                <strong><?= $this->t('download.qr_title') ?></strong><br>
                <?= $this->t('download.qr_text') ?>
            </p>
        </div>
    </div>
</section>
<?php if ($file->encrypted): ?>
    <section class="preview" id="decrypt-preview" aria-label="<?= $this->t('download.preview') ?>" hidden></section>
<?php endif; ?>
<?php if (($kind = $file->previewKind()) !== null):
    $src = '/d/' . rawurlencode($file->code) . '/preview'; ?>
    <section class="preview" aria-label="<?= $this->t('download.preview') ?>">
        <?php if ($kind === 'image'): ?>
            <img src="<?= $this->e($src) ?>" alt="<?= $this->t('js.preview_of', ['name' => $file->originalName]) ?>">
        <?php elseif ($kind === 'video'): ?>
            <video src="<?= $this->e($src) ?>" controls preload="metadata" playsinline></video>
        <?php elseif ($kind === 'text'): ?>
            <pre class="preview-text" id="preview-text" data-src="<?= $this->e($src) ?>" data-size="<?= (int) $file->size ?>"
                hidden></pre>
            <p class="preview-note" id="preview-note" hidden><?= $this->t('js.preview_limit') ?></p>
        <?php elseif ($kind === 'zip'): ?>
            <div class="zip-preview" id="zip-preview" data-src="<?= $this->e($src) ?>" data-size="<?= (int) $file->size ?>"
                hidden></div>
        <?php else: ?>
            <a class="btn btn-small" href="<?= $this->e($src) ?>" target="_blank"
                rel="noopener"><?= $this->t('js.open_pdf') ?></a>
        <?php endif; ?>
    </section>
<?php endif; ?>