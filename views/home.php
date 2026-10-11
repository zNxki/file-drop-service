<section class="intro">
    <?php $w = 0; ?>
    <h1><?= $this->words($this->raw('home.title_a'), $w) ?>
        <mark class="swipe"><?= $this->words($this->raw('home.title_b'), $w) ?></mark>
    </h1>
    <p><?= $this->t('home.lead', ['size' => $this->bytes($maxSize), 'files' => (int) $maxFiles]) ?></p>
    <ul class="chips" aria-label="<?= $this->t('home.features') ?>">
        <li><svg viewBox="0 0 24 24" aria-hidden="true">
                <rect x="4" y="10.5" width="16" height="10" rx="2.5" />
                <path d="M8 10.5V7.5a4 4 0 0 1 8 0v3" />
            </svg><?= $this->t('home.chip.encrypt') ?></li>
        <li><svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 7v5l3 2" />
            </svg><?= $this->t('home.chip.expires') ?></li>
        <li><svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 3 3 8l9 5 9-5-9-5z" />
                <path d="m3 13 9 5 9-5" />
            </svg><?= $this->t('home.chip.files', ['files' => (int) $maxFiles]) ?></li>
    </ul>
</section>

<form id="upload-form" class="card" data-max-size="<?= (int) $maxSize ?>" data-max-files="<?= (int) $maxFiles ?>"
    novalidate>
    <label class="drop" id="drop" for="file">
        <input class="visually-hidden" type="file" id="file" multiple>
        <svg class="drop-icon" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 16V4" />
            <path d="m7 9 5-5 5 5" />
            <path d="M4 16v3a1.5 1.5 0 0 0 1.5 1.5h13A1.5 1.5 0 0 0 20 19v-3" />
        </svg>
        <span class="drop-title" id="drop-title"><?= $this->t('js.drop_title') ?></span>
        <span class="drop-hint" id="drop-hint"><?= $this->t('js.drop_hint') ?></span>
    </label>
    <ul class="item-list" id="file-list" aria-label="<?= $this->t('home.selected') ?>" hidden></ul>

    <div class="fields">
        <label class="field wide check">
            <span class="check-row">
                <input type="checkbox" id="encrypt" name="encrypt" value="1" checked>
                <?= $this->t('home.encrypt') ?>
            </span>
            <small class="hint" id="encrypt-hint"><?= $this->t('home.encrypt_hint') ?></small>
            <small class="hint" id="zip-hint" hidden><?= $this->t('home.zip_hint') ?></small>
        </label>
        <label class="field">
            <span><?= $this->t('home.expires') ?></span>
            <select name="expires_in">
                <?php foreach ($options as $seconds => $label): ?>
                    <option value="<?= (int) $seconds ?>" <?= $seconds === $defaultExpiry ? ' selected' : '' ?>>
                        <?= $this->e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span><?= $this->t('home.password') ?></span>
            <input type="password" name="password" minlength="4" maxlength="200" autocomplete="new-password"
                placeholder="<?= $this->t('home.password_none') ?>">
        </label>
        <label class="field wide">
            <span><?= $this->t('home.max_downloads') ?></span>
            <input type="number" name="max_downloads" min="1" max="<?= (int) $maxDownloads ?>" step="1"
                inputmode="numeric" placeholder="<?= $this->t('home.unlimited') ?>">
            <small class="hint"><?= $this->t('home.max_downloads_hint') ?></small>
        </label>
    </div>

    <button class="btn" type="submit" id="submit" disabled><?= $this->t('js.submit_one') ?></button>

    <div id="progress" hidden>
        <div class="bar" id="bar" role="progressbar" aria-label="<?= $this->t('home.progress') ?>" aria-valuemin="0"
            aria-valuemax="100" aria-valuenow="0">
            <span id="bar-fill"></span>
        </div>
        <div class="progress-meta">
            <span id="pct">0%</span>
            <span id="speed"></span>
            <button type="button" class="link" id="cancel"><?= $this->t('js.cancel') ?></button>
        </div>
    </div>

    <p class="error" id="error" role="alert" hidden></p>
</form>

<section class="ticket" id="result" aria-live="polite" hidden>
    <div class="ticket-main">
        <p class="code" id="code"></p>
    </div>
    <div class="ticket-stub">
        <div class="copy-row">
            <input id="share-url" type="text" readonly aria-label="<?= $this->t('home.share_label') ?>">
            <button class="btn btn-small" type="button" id="copy"><?= $this->t('js.copy') ?></button>
        </div>
        <p class="file-meta" id="result-meta"></p>
        <div class="actions">
            <button class="link" type="button" id="again"><?= $this->t('js.again') ?></button>
            <button class="link danger" type="button" id="delete"><?= $this->t('js.delete') ?></button>
        </div>
    </div>
</section>

<details class="api">
    <summary><?= $this->t('home.api') ?></summary>
    <pre><code># <?= $this->t('api.upload') ?>

curl -F file=@foto.jpg -F expires_in=3600 -F password=segreto -F max_downloads=1 <?= $this->e($baseUrl) ?>/api/files

# <?= $this->t('api.multi') ?>

curl -F 'file[]=@foto.jpg' -F 'file[]=@nota.txt' <?= $this->e($baseUrl) ?>/api/files

# <?= $this->t('api.info') ?>

curl <?= $this->e($baseUrl) ?>/api/files/8Kf2a

# <?= $this->t('api.download') ?>

curl -OJ -H "X-File-Password: segreto" <?= $this->e($baseUrl) ?>/api/files/8Kf2a/download

# <?= $this->t('api.delete') ?>

curl -X DELETE -H "X-Delete-Token: ..." <?= $this->e($baseUrl) ?>/api/files/8Kf2a</code></pre>
</details>