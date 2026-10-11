<?php
$url = static function (array $extra = []) use ($q, $sort, $ascending): string {
    $params = ['sort' => $sort, 'dir' => $ascending ? 'asc' : 'desc'];
    if ($q !== '') {
        $params['q'] = $q;
    }

    return '/admin/files?' . http_build_query($extra + $params);
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
?>
<section class="admin-head">
    <h1>File</h1>
    <?php if ($canDelete): ?>
        <form method="post" action="/admin/cleanup" class="inline-form">
            <input type="hidden" name="csrf" value="<?= $this->e($csrf) ?>">
            <input type="hidden" name="return" value="files">
            <?php foreach ($state as $key => $value): ?>
                <input type="hidden" name="<?= $this->e($key) ?>" value="<?= $this->e($value) ?>">
            <?php endforeach; ?>
            <button class="btn btn-small" type="submit" <?= $totals['stale'] === 0 ? 'disabled' : '' ?>>Elimina scaduti ed
                esauriti (<?= $this->number($totals['stale']) ?>)</button>
        </form>
    <?php endif; ?>
</section>

<section class="card panel">
    <h2>File caricati <span class="hint">(<?= $this->number($list['total']) ?>)</span></h2>

    <form class="search" method="get" action="/admin/files">
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
                                <?= $this->number($file->downloads) ?>         <?php if ($file->maxDownloads !== null): ?> /
                                    <?= $this->number($file->maxDownloads) ?>         <?php endif; ?>
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
                                <?php if ($canDelete): ?>
                                    <form method="post" action="/admin/files/<?= $this->e($file->code) ?>/delete"
                                        data-confirm="Eliminare «<?= $this->e($file->originalName) ?>»?">
                                        <input type="hidden" name="csrf" value="<?= $this->e($csrf) ?>">
                                        <?php foreach ($state as $key => $value): ?>
                                            <input type="hidden" name="<?= $this->e($key) ?>" value="<?= $this->e($value) ?>">
                                        <?php endforeach; ?>
                                        <button class="link danger" type="submit">Elimina</button>
                                    </form>
                                <?php endif; ?>
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