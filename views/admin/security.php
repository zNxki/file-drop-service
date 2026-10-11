<?php
$kindLabels = [
    'rate:requests' => 'Richieste',
    'rate:uploads' => 'Upload',
    'rate:downloads' => 'Download',
    'rate:chunks' => 'Blocchi upload',
    'quota:files' => 'Quota file',
    'quota:storage' => 'Quota spazio',
    'quota:daily' => 'Quota giornaliera',
];
$durations = [3600 => '1 ora', 86400 => '24 ore', 604800 => '7 giorni', 2592000 => '30 giorni', 0 => 'Per sempre'];
?>
<section class="admin-head">
    <h1>Sicurezza</h1>
</section>

<section class="card panel" id="limiti">
    <h2>Limiti raggiunti <span class="hint">(ultime <?= (int) $limitHours ?> ore)</span></h2>
    <?php if ($limitEvents === []): ?>
        <p class="empty">Nessun client ha toccato rate limit o quote.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="files">
                <thead>
                    <tr>
                        <th scope="col">Client</th>
                        <th scope="col">Motivo</th>
                        <th scope="col">Rifiuti</th>
                        <th scope="col">Ultimo</th>
                        <th scope="col"><span class="visually-hidden">Azioni</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($limitEvents as $event): ?>
                        <?php
                        $base = explode('/', $event['client'])[0];
                        $isBlocked = \FileDrop\Support\IpRange::matches($base, $blockedRanges);
                        ?>
                        <tr>
                            <td><code><?= $this->e($event['client']) ?></code></td>
                            <td>
                                <?php foreach ($event['kinds'] as $kind => $hits): ?>
                                    <span class="badge"><?= $this->e($kindLabels[$kind] ?? $kind) ?> ·
                                        <?= $this->number($hits) ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td><?= $this->number($event['hits']) ?></td>
                            <td><?= $this->time($event['last_at']) ?></td>
                            <td>
                                <?php if ($isBlocked): ?>
                                    <span class="badge badge-stale">Bloccato</span>
                                <?php elseif ($canManage): ?>
                                    <form class="block-inline" method="post" action="/admin/blocks"
                                        data-confirm="Bloccare <?= $this->e($event['client']) ?>?">
                                        <input type="hidden" name="csrf" value="<?= $this->e($csrf) ?>">
                                        <input type="hidden" name="ip" value="<?= $this->e($event['client']) ?>">
                                        <input type="hidden" name="reason" value="Limiti superati">
                                        <input type="hidden" name="duration" value="86400">
                                        <button class="link danger" type="submit">Blocca 24 h</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <p class="hint">I dati si riferiscono alle richieste rifiutate con 429 e vengono conservati 7 giorni. Gli IPv6 sono
        raggruppati per /64.</p>
</section>

<section class="card panel" id="blocchi">
    <h2>IP bloccati <span class="hint">(<?= $this->number(count($blocks)) ?>)</span></h2>

    <?php if ($canManage): ?>
        <form class="block-form" method="post" action="/admin/blocks">
            <input type="hidden" name="csrf" value="<?= $this->e($csrf) ?>">
            <input type="text" name="ip" required maxlength="64" placeholder="IP o CIDR, es. 203.0.113.7 o 198.51.100.0/24"
                aria-label="Indirizzo IP o intervallo CIDR" autocomplete="off" spellcheck="false">
            <input type="text" name="reason" maxlength="120" placeholder="Motivo (facoltativo)" aria-label="Motivo">
            <select name="duration" aria-label="Durata">
                <?php foreach ($durations as $seconds => $label): ?>
                    <option value="<?= $seconds ?>" <?= $seconds === 86400 ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-small" type="submit">Blocca</button>
        </form>
    <?php endif; ?>

    <?php if ($blocks === []): ?>
        <p class="empty">Nessun indirizzo bloccato.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="files">
                <thead>
                    <tr>
                        <th scope="col">Indirizzo</th>
                        <th scope="col">Motivo</th>
                        <th scope="col">Dal</th>
                        <th scope="col">Scade</th>
                        <th scope="col"><span class="visually-hidden">Azioni</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($blocks as $block): ?>
                        <tr>
                            <td><code><?= $this->e($block['cidr']) ?></code></td>
                            <td><?= $this->e($block['reason']) ?></td>
                            <td><?= $this->time($block['created_at']) ?></td>
                            <td><?= $block['expires_at'] === null ? 'Mai' : $this->time($block['expires_at']) ?></td>
                            <td>
                                <?php if ($canManage): ?>
                                    <form method="post" action="/admin/blocks/delete">
                                        <input type="hidden" name="csrf" value="<?= $this->e($csrf) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $block['id'] ?>">
                                        <button class="link" type="submit">Sblocca</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <p class="hint">Il blocco vale per tutto il sito e per le API. Il tuo indirizzo attuale è
        <code><?= $this->e($ownIp) ?></code>.
    </p>
</section>