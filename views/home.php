<section class="intro">
    <h1><span class="w" style="--i:0">Uno</span> <span class="w" style="--i:1">o</span> <span class="w" style="--i:2">più</span>
        <span class="w" style="--i:3">file,</span>
        <mark class="swipe"><span class="w" style="--i:4">un</span> <span class="w" style="--i:5">codice.</span></mark>
    </h1>
    <p>Carica uno o più file e condividi il codice a cinque caratteri. Più file si scaricano insieme in un
        unico .zip. Dimensione massima <?= $this->e($this->bytes($maxSize)) ?> in totale, fino a
        <?= (int) $maxFiles ?> file.
    </p>
    <ul class="chips" aria-label="Caratteristiche">
        <li><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10.5" width="16" height="10" rx="2.5" /><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3" /></svg>Cifratura nel browser</li>
        <li><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>Scade da solo</li>
        <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 3 8l9 5 9-5-9-5z" /><path d="m3 13 9 5 9-5" /></svg>Fino a <?= (int) $maxFiles ?> file</li>
    </ul>
</section>

<form id="upload-form" class="card" data-max-size="<?= (int) $maxSize ?>" data-max-files="<?= (int) $maxFiles ?>"
    novalidate>
    <label class="drop" id="drop" for="file">
        <input class="visually-hidden" type="file" id="file" multiple>
        <svg class="drop-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4" /><path d="m7 9 5-5 5 5" /><path d="M4 16v3a1.5 1.5 0 0 0 1.5 1.5h13A1.5 1.5 0 0 0 20 19v-3" /></svg>
        <span class="drop-title" id="drop-title">Trascina qui uno o più file</span>
        <span class="drop-hint" id="drop-hint">oppure fai clic per sceglierli dal dispositivo</span>
    </label>
    <ul class="item-list" id="file-list" aria-label="File selezionati" hidden></ul>

    <div class="fields">
        <label class="field wide check">
            <span class="check-row">
                <input type="checkbox" id="encrypt" name="encrypt" value="1" checked>
                Cifra nel browser
            </span>
            <small class="hint" id="encrypt-hint">Il server non vede mai contenuto e nomi dei file. La chiave sta nel
                link,
                dopo il #: chi perde il link perde il file.</small>
            <small class="hint" id="zip-hint" hidden>Con più file lo zip viene creato nel browser e poi cifrato: chi
                scarica ottiene un unico .zip.</small>
        </label>
        <label class="field">
            <span>Scade dopo</span>
            <select name="expires_in">
                <?php foreach ($options as $seconds => $label): ?>
                    <option value="<?= (int) $seconds ?>" <?= $seconds === $defaultExpiry ? ' selected' : '' ?>>
                        <?= $this->e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Password (facoltativa)</span>
            <input type="password" name="password" minlength="4" maxlength="200" autocomplete="new-password"
                placeholder="Nessuna">
        </label>
        <label class="field wide">
            <span>Download consentiti (facoltativo)</span>
            <input type="number" name="max_downloads" min="1" max="<?= (int) $maxDownloads ?>" step="1"
                inputmode="numeric" placeholder="Illimitati">
            <small class="hint">Dopo l'ultimo download il file si elimina da solo. Metti 1 per un link usa e
                getta.</small>
        </label>
    </div>

    <button class="btn" type="submit" id="submit" disabled>Carica file</button>

    <div id="progress" hidden>
        <div class="bar" id="bar" role="progressbar" aria-label="Avanzamento del caricamento" aria-valuemin="0"
            aria-valuemax="100" aria-valuenow="0">
            <span id="bar-fill"></span>
        </div>
        <div class="progress-meta">
            <span id="pct">0%</span>
            <span id="speed"></span>
            <button type="button" class="link" id="cancel">Annulla</button>
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
            <input id="share-url" type="text" readonly aria-label="Link di condivisione">
            <button class="btn btn-small" type="button" id="copy">Copia link</button>
        </div>
        <p class="file-meta" id="result-meta"></p>
        <div class="actions">
            <button class="link" type="button" id="again">Carica altri file</button>
            <button class="link danger" type="button" id="delete">Elimina ora</button>
        </div>
    </div>
</section>

<details class="api">
    <summary>Usa l'API</summary>
    <pre><code># Carica un file (scadenza in secondi, password e limite di download facoltativi)
curl -F file=@foto.jpg -F expires_in=3600 -F password=segreto -F max_downloads=1 <?= $this->e($baseUrl) ?>/api/files

# Carica più file: si scaricano insieme in un .zip (campo "file[]" ripetuto)
curl -F 'file[]=@foto.jpg' -F 'file[]=@nota.txt' <?= $this->e($baseUrl) ?>/api/files

# Informazioni sul file
curl <?= $this->e($baseUrl) ?>/api/files/8Kf2a

# Scarica (con password)
curl -OJ -H "X-File-Password: segreto" <?= $this->e($baseUrl) ?>/api/files/8Kf2a/download

# Elimina (token restituito dall'upload)
curl -X DELETE -H "X-Delete-Token: ..." <?= $this->e($baseUrl) ?>/api/files/8Kf2a</code></pre>
</details>