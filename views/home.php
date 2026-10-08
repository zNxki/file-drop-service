<section class="intro">
    <h1>Un file, un codice.</h1>
    <p>Carica un file e condividi il codice a cinque caratteri. Dimensione massima
        <?= $this->e($this->bytes($maxSize)) ?>.</p>
</section>

<form id="upload-form" class="card" data-max-size="<?= (int) $maxSize ?>" novalidate>
    <label class="drop" id="drop" for="file">
        <input class="visually-hidden" type="file" id="file" name="file">
        <span class="drop-title" id="drop-title">Trascina qui un file</span>
        <span class="drop-hint" id="drop-hint">oppure fai clic per sceglierlo dal dispositivo</span>
    </label>

    <div class="fields">
        <label class="field">
            <span>Scade dopo</span>
            <select name="expires_in">
                <?php foreach ($options as $seconds => $label): ?>
                    <option value="<?= (int) $seconds ?>" <?= $seconds === $defaultExpiry ? ' selected' : '' ?>>
                        <?= $this->e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Password (facoltativa)</span>
            <input type="password" name="password" minlength="4" maxlength="200" autocomplete="new-password"
                placeholder="Nessuna">
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
            <button class="link" type="button" id="again">Carica un altro file</button>
            <button class="link danger" type="button" id="delete">Elimina ora</button>
        </div>
    </div>
</section>

<details class="api">
    <summary>Usa l'API</summary>
    <pre><code># Carica un file (scadenza in secondi, password facoltativa)
curl -F file=@foto.jpg -F expires_in=3600 -F password=segreto <?= $this->e($baseUrl) ?>/api/files

# Informazioni sul file
curl <?= $this->e($baseUrl) ?>/api/files/8Kf2a

# Scarica (con password)
curl -OJ -H "X-File-Password: segreto" <?= $this->e($baseUrl) ?>/api/files/8Kf2a/download

# Elimina (token restituito dall'upload)
curl -X DELETE -H "X-Delete-Token: ..." <?= $this->e($baseUrl) ?>/api/files/8Kf2a</code></pre>
</details>