<?php
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
    <h1>Panoramica</h1>
</section>

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
    <?php if ($canCleanup): ?>
        <form method="post" action="/admin/cleanup" class="inline-form">
            <input type="hidden" name="csrf" value="<?= $this->e($csrf) ?>">
            <input type="hidden" name="return" value="overview">
            <button class="btn btn-small" type="submit" <?= $totals['stale'] === 0 ? 'disabled' : '' ?>>Elimina scaduti ed
                esauriti</button>
        </form>
    <?php endif; ?>
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
