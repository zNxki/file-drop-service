<?php
$url = static function (array $extra = []) use ($q, $sort, $ascending): string {
    $params = ['sort' => $sort, 'dir' => $ascending ? 'asc' : 'desc'];
    if ($q !== '') {
        $params['q'] = $q;
    }

    return '/admin?' . http_build_query($extra + $params);
};

$head = function (string $key, string $label) use ($sort, $ascending, $url): string {
    $active = $sort === $key;
    $nextAscending = $active ? !$ascending : $key === 'name';
    $aria = $active ? ($ascending ? 'ascending' : 'descending') : 'none';
    $arrow = $active ? ($ascending ? ' ↑' : ' ↓') : '';

    return sprintf(
        '<th scope="col" aria-sort="%s"><a href="%s">%s%s</a></th>',
        $aria,
        $this->e($url(['sort' => $key, 'dir' => $nextAscending ? 'asc' : 'desc'])),
        $this->e($label),
        $arrow
    );
};

$state = [
    'dir' => $ascending ? 'asc' : 'desc',
    'sort' => $sort,
    'q' => $q,
    'page' => $list['page'],
];

$activeFiles = max(0, $totals['files'] - $totals['stale']);
$chartMax = max(1, ...array_values($perDay));
$chartWidth = 10 * count($perDay);
$sumBase = max(1, $usage['sum']);
$segments = [
    'active' => $usage['active'],
    'stale' => $usage['stale'],
    'other' => $usage['other'],
];
?>
<section class="admin-head">
    <h1>Amministrazione</h1>
    <form method="post" action="/admin/logout">
        <input type="hidden" name="csrf" value="<?= $this->e($csrf) ?>">
        <button class="link" type="submit">Esci</button>
    </form>
</section>

<?php if (!empty($flash)): ?>
    <p class="flash flash-<?= $this->e($flash['type']) ?>" role="status"><?= $this->e($flash['text']) ?></p>
<?php endif; ?>

<section class="stats" aria-label="Riepilogo">
    <div class="card stat">
        <span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" /><path d="M14 3v5h5" /></svg></span>
        <span class="stat-label">File attivi</span>
        <strong class="stat-value"><?= $this->number($activeFiles) ?></strong>
        <span class="stat-note">
            <?php if ($totals['stale'] > 0): ?>
                <?= $this->number($totals['stale']) ?> da eliminare
            <?php else: ?>
                Nessun file da eliminare
            <?php endif; ?>
        </span>
    </div>
    <div class="card stat">
        <span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><ellipse cx="12" cy="6" rx="8" ry="3" /><path d="M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6" /><path d="M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6" /></svg></span>
        <span class="stat-label">Spazio usato</span>
        <strong class="stat-value"><?= $this->e($this->bytes($disk['bytes'])) ?></strong>
        <span class="stat-note">
            <?php if ($disk['free'] !== null && $disk['total'] !== null): ?>
                <?= $this->e($this->bytes($disk['free'])) ?> liberi su <?= $this->e($this->bytes($disk['total'])) ?>
            <?php else: ?>
                <?= $this->number($disk['files']) ?> file su disco
            <?php endif; ?>
        </span>
    </div>
    <div class="card stat">
        <span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 4v11" /><path d="m7 11 5 5 5-5" /><path d="M5 20h14" /></svg></span>
        <span class="stat-label">Download totali</span>
        <strong class="stat-value"><?= $this->number($totals['downloads']) ?></strong>
        <span class="stat-note"><?= $this->number($downloads['day']) ?> nelle ultime 24 ore</span>
    </div>
    <div class="card stat">
        <span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 17l6-6 4 4 8-8" /><path d="M15 7h6v6" /></svg></span>
        <span class="stat-label">Ultimi 7 / 30 giorni</span>
        <strong class="stat-value"><?= $this->number($downloads['week']) ?> /
            <?= $this->number($downloads['month']) ?></strong>
        <span class="stat-note">
            <?php if ($downloads['since'] !== null): ?>
                Registro dal <?= $this->time($downloads['since']) ?>
            <?php else: ?>
                Nessun download registrato
            <?php endif; ?>
        </span>
    </div>
</section>

<section class="card panel">
    <h2>Occupazione dello spazio</h2>
    <svg class="usage" viewBox="0 0 100 4" preserveAspectRatio="none" role="img"
        aria-label="Ripartizione dello spazio occupato">
        <?php $x = 0.0;
        foreach ($segments as $name => $bytes):
            $w = $bytes / $sumBase * 100; ?>
            <rect class="seg seg-<?= $name ?>" x="<?= round($x, 3) ?>" y="0" width="<?= round($w, 3) ?>" height="4"></rect>
            <?php $x += $w; endforeach; ?>
    </svg>
    <ul class="legend">
        <li><span class="swatch seg-active"></span>File attivi
            <strong><?= $this->e($this->bytes($usage['active'])) ?></strong></li>
        <li><span class="swatch seg-stale"></span>Scaduti o esauriti
            <strong><?= $this->e($this->bytes($usage['stale'])) ?></strong></li>
        <li><span class="swatch seg-other"></span>Orfani su disco
            <strong><?= $this->e($this->bytes($usage['other'])) ?></strong></li>
    </ul>
    <form method="post" action="/admin/cleanup" class="inline-form">
        <input type="hidden" name="csrf" value="<?= $this->e($csrf) ?>">
        <?php foreach ($state as $key => $value): ?>
            <input type="hidden" name="<?= $this->e($key) ?>" value="<?= $this->e($value) ?>">
        <?php endforeach; ?>
        <button class="btn btn-small" type="submit" <?= $totals['stale'] === 0 ? 'disabled' : '' ?>>Elimina scaduti ed
            esauriti</button>
    </form>
</section>

<section class="card panel">
    <h2>Download negli ultimi <?= count($perDay) ?> giorni</h2>
    <svg class="chart" viewBox="0 0 <?= $chartWidth ?> 80" preserveAspectRatio="none" role="img"
        aria-label="Download giornalieri">
        <?php $i = 0;
        foreach ($perDay as $day => $n):
            $h = $n === 0 ? 1 : max(2, (int) round($n / $chartMax * 76)); ?>
            <rect class="chart-bar<?= $n === 0 ? ' is-zero' : '' ?>" x="<?= $i * 10 + 1 ?>" y="<?= 80 - $h ?>" width="8"
                height="<?= $h ?>">
                <title><?= $this->e($day) ?>: <?= $this->number($n) ?></title>
            </rect>
            <?php $i++; endforeach; ?>
    </svg>
    <p class="chart-axis">
        <span><?= $this->e(array_key_first($perDay)) ?></span>
        <span>max <?= $this->number(max($perDay)) ?>/giorno</span>
        <span><?= $this->e(array_key_last($perDay)) ?></span>
    </p>
</section>

<div class="columns">
    <section class="card panel">
        <h2>File più scaricati</h2>
        <?php if ($top === []): ?>
            <p class="empty">Nessun download nel periodo.</p>
        <?php else: ?>
            <ol class="rank">
                <?php foreach ($top as $row): ?>
                    <li>
                        <span class="rank-name"><?= $this->e($row['name']) ?></span>
                        <span class="rank-meta"><?= $this->e($this->bytes($row['size'])) ?></span>
                        <strong><?= $this->number($row['downloads']) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <section class="card panel">
        <h2>Ultimi download</h2>
        <?php if ($recent === []): ?>
            <p class="empty">Nessun download registrato.</p>
        <?php else: ?>
            <ul class="rank">
                <?php foreach ($recent as $row): ?>
                    <li>
                        <span class="rank-name"><?= $this->e($row['name']) ?></span>
                        <span class="rank-meta"><?= $this->e($this->bytes($row['size'])) ?></span>
                        <span><?= $this->time($row['at']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<section class="card panel">
    <h2>File caricati <span class="hint">(<?= $this->number($list['total']) ?>)</span></h2>

    <form class="search" method="get" action="/admin">
        <input type="hidden" name="sort" value="<?= $this->e($sort) ?>">
        <input type="hidden" name="dir" value="<?= $ascending ? 'asc' : 'desc' ?>">
        <input type="search" name="q" value="<?= $this->e($q) ?>" maxlength="100" placeholder="Cerca per nome o codice"
            aria-label="Cerca">
        <button class="btn btn-small" type="submit">Cerca</button>
        <?php if ($q !== ''): ?>
            <a class="link" href="<?= $this->e($url(['q' => ''])) ?>">Azzera</a>
        <?php endif; ?>
    </form>

    <?php if ($list['files'] === []): ?>
        <p class="empty"><?= $q === '' ? 'Nessun file caricato.' : 'Nessun file corrisponde alla ricerca.' ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="files">
                <thead>
                    <tr>
                        <?= $head('name', 'Nome') ?>
                        <th scope="col">Codice</th>
                        <?= $head('size', 'Dimensione') ?>
                        <?= $head('downloads', 'Download') ?>
                        <?= $head('created', 'Caricato') ?>
                        <?= $head('expires', 'Scade') ?>
                        <th scope="col">Stato</th>
                        <th scope="col"><span class="visually-hidden">Azioni</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list['files'] as $file): ?>
                        <tr>
                            <td class="cell-name"><a
                                    href="/d/<?= $this->e($file->code) ?>"><?= $this->e($file->originalName) ?></a></td>
                            <td><code><?= $this->e($file->code) ?></code></td>
                            <td><?= $this->e($this->bytes($file->size)) ?></td>
                            <td>
                                <?= $this->number($file->downloads) ?>        <?php if ($file->maxDownloads !== null): ?> /
                                    <?= $this->number($file->maxDownloads) ?>        <?php endif; ?>
                            </td>
                            <td><?= $this->time($file->createdAt) ?></td>
                            <td><?= $this->time($file->expiresAt) ?></td>
                            <td>
                                <?php if ($file->isExpired($now)): ?>
                                    <span class="badge badge-stale">Scaduto</span>
                                <?php elseif ($file->isExhausted()): ?>
                                    <span class="badge badge-stale">Esaurito</span>
                                <?php else: ?>
                                    <span class="badge badge-ok">Attivo</span>
                                <?php endif; ?>
                                <?php if ($file->isProtected()): ?>
                                    <span class="badge">Password</span>
                                <?php endif; ?>
                                <?php if ($file->encrypted): ?>
                                    <span class="badge">Cifrato</span>
                                <?php endif; ?>
                                <?php if ($file->isBundle()): ?>
                                    <span class="badge"><?= (int) $file->itemCount ?> file</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" action="/admin/files/<?= $this->e($file->code) ?>/delete"
                                    data-confirm="Eliminare «<?= $this->e($file->originalName) ?>»?">
                                    <input type="hidden" name="csrf" value="<?= $this->e($csrf) ?>">
                                    <?php foreach ($state as $key => $value): ?>
                                        <input type="hidden" name="<?= $this->e($key) ?>" value="<?= $this->e($value) ?>">
                                    <?php endforeach; ?>
                                    <button class="link danger" type="submit">Elimina</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($list['pages'] > 1): ?>
            <nav class="pager" aria-label="Pagine">
                <?php if ($list['page'] > 1): ?>
                    <a class="link" href="<?= $this->e($url(['page' => $list['page'] - 1])) ?>">← Precedente</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <span>Pagina <?= $list['page'] ?> di <?= $list['pages'] ?></span>
                <?php if ($list['page'] < $list['pages']): ?>
                    <a class="link" href="<?= $this->e($url(['page' => $list['page'] + 1])) ?>">Successiva →</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

    <p class="hint">Il registro dei download viene conservato per <?= $retentionDays ?> giorni.</p>
</section>