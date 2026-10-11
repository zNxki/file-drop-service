<!doctype html>
<html lang="<?= $this->e($this->lang()) ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <?php if (!empty($noindex)): ?>
        <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
    <title><?= $this->e($title ?? 'File Drop') ?></title>
    <link rel="icon"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%230E1A2B'/%3E%3Crect x='7' y='10' width='18' height='12' rx='2' fill='%23FFD23F'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="<?= $this->e($this->asset('/assets/style/style.css')) ?>">
    <link rel="stylesheet" href="<?= $this->e($this->asset('/assets/style/motion.css')) ?>">
    <link rel="stylesheet" href="<?= $this->e($this->asset('/assets/style/theme.css')) ?>">
    <link rel="stylesheet" href="<?= $this->e($this->asset('/assets/style/router.css')) ?>">
    <link rel="stylesheet" href="<?= $this->e($this->asset('/assets/style/admin.css')) ?>">
    <link rel="stylesheet" href="<?= $this->e($this->asset('/assets/style/toggle.css')) ?>">
    <link rel="stylesheet" href="<?= $this->e($this->asset('/assets/style/lang-picker.css')) ?>">
    <?php if ($this->localized() && empty($noindex)): ?>
        <?php foreach ($this->locales() as $code => $info): ?>
            <link rel="alternate" hreflang="<?= $this->e($code) ?>" href="<?= $this->e($this->alternate($code)) ?>">
        <?php endforeach; ?>
        <link rel="alternate" hreflang="x-default" href="<?= $this->e($this->alternate($this->defaultLocale())) ?>">
    <?php endif; ?>
    <script type="application/json" id="fd-i18n"><?= $this->i18nJson() ?></script>
    <script src="<?= $this->e($this->asset('/assets/js/i18n.js')) ?>"></script>
    <script src="<?= $this->e($this->asset('/assets/js/theme-toggle.js')) ?>"></script>
</head>

<body>
    <div class="shell<?= !empty($wide) ? ' shell-wide' : '' ?>">
        <header class="top">
            <a class="brand" href="<?= $this->e($this->url()) ?>">File Drop</a>
            <div class="top-tools">
                <?php if ($this->localized()): ?>
                    <?php $current = $this->locales()[$this->lang()]; ?>
                    <div class="lp" id="lang-picker" data-lang-picker>
                        <button class="lp-trigger" type="button" aria-haspopup="listbox" aria-expanded="false"
                            aria-controls="lp-panel" title="<?= $this->e($current['name']) ?>"
                            aria-label="<?= $this->t('layout.language') ?>: <?= $this->e($current['name']) ?>">
                            <span class="lp-flag"><?= $this->flag($current) ?></span>
                            <span class="lp-code"><?= $this->e(strtoupper($this->lang())) ?></span>
                            <svg class="lp-chev" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M6 9l6 6 6-6" />
                            </svg>
                        </button>
                        <div class="lp-backdrop" data-lp-close></div>
                        <div class="lp-panel" id="lp-panel" role="dialog" aria-label="<?= $this->t('layout.language') ?>">
                            <div class="lp-grab" aria-hidden="true"></div>
                            <label class="lp-search">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="11" cy="11" r="6.5" />
                                    <path d="M16 16l4.5 4.5" />
                                </svg>
                                <input type="text" role="combobox" aria-expanded="true" aria-controls="lp-list"
                                    aria-autocomplete="list" autocomplete="off" autocapitalize="off" spellcheck="false"
                                    placeholder="<?= $this->t('layout.language.search') ?>"
                                    aria-label="<?= $this->t('layout.language.search') ?>">
                                <button class="lp-clear" type="button" tabindex="-1" aria-hidden="true">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M7 7l10 10M17 7L7 17" />
                                    </svg>
                                </button>
                            </label>
                            <div class="lp-scroll">
                                <span class="lp-hl" aria-hidden="true"></span>
                                <ul class="lp-list" id="lp-list" role="listbox"
                                    aria-label="<?= $this->t('layout.language') ?>">
                                    <?php foreach ($this->locales() as $code => $info): ?>
                                        <?php $isCurrent = $code === $this->lang(); ?>
                                        <li role="presentation">
                                            <a class="lp-opt" role="option" id="lp-opt-<?= $this->e($code) ?>"
                                                href="<?= $this->e($this->switchUrl($code)) ?>" lang="<?= $this->e($code) ?>"
                                                hreflang="<?= $this->e($code) ?>" data-lang="<?= $this->e($code) ?>"
                                                data-no-router
                                                data-keywords="<?= $this->e($info['name'] . ' ' . ($info['english'] ?? '') . ' ' . $code . ' ' . $info['tag']) ?>"
                                                tabindex="-1" aria-selected="<?= $isCurrent ? 'true' : 'false' ?>">
                                                <span class="lp-flag"><?= $this->flag($info) ?></span>
                                                <span class="lp-names">
                                                    <span class="lp-name"><?= $this->e($info['name']) ?></span>
                                                    <?php if (!empty($info['english']) && $info['english'] !== $info['name']): ?>
                                                        <span class="lp-sub"><?= $this->e($info['english']) ?></span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="lp-tag"><?= $this->e(strtoupper($code)) ?></span>
                                                <svg class="lp-check" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M5 12.5l4.5 4.5L19 7.5" />
                                                </svg>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <p class="lp-empty" role="status" hidden><?= $this->t('layout.language.empty') ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <button class="theme-toggle" id="theme-toggle" type="button"
                    aria-label="<?= $this->t('js.theme.auto') ?>" title="<?= $this->t('js.theme.auto') ?>">
                    <svg class="tt-auto" viewBox="0 0 24 24" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="12" rx="2" />
                        <path d="M8 20h8M12 16v4" />
                    </svg>
                    <svg class="tt-light" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="4" />
                        <path
                            d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
                    </svg>
                    <svg class="tt-dark" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z" />
                    </svg>
                </button>
            </div>
        </header>
        <main><?= $content ?></main>
        <footer class="foot" <?= !empty($wide) ? 'hidden' : '' ?>><?= $this->t('layout.footer') ?>
        </footer>
    </div>
    <div class="visually-hidden" id="fd-announcer" role="status" aria-live="polite"></div>

    <script src="<?= $this->e($this->asset('/assets/js/router.js')) ?>" defer></script>
    <script src="<?= $this->e($this->asset('/assets/js/ui.js')) ?>" defer></script>
    <script src="<?= $this->e($this->asset('/assets/js/lang-picker.js')) ?>" defer></script>
    <script src="<?= $this->e($this->asset('/assets/js/motion.js')) ?>" defer></script>
    <?php foreach ($pageScripts ?? [] as $src): ?>
        <script src="<?= $this->e($this->asset($src)) ?>" defer></script>
    <?php endforeach; ?>
</body>

</html>