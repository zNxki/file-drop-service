<!doctype html>
<html lang="it">

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
    <script src="<?= $this->e($this->asset('/assets/js/theme-toggle.js')) ?>"></script>
</head>

<body>
    <div class="shell<?= !empty($wide) ? ' shell-wide' : '' ?>">
        <header class="top">
            <a class="brand" href="/">File Drop</a>
            <button class="theme-toggle" id="theme-toggle" type="button" aria-label="Tema: automatico"
                title="Tema: automatico">
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
        </header>
        <main><?= $content ?></main>
        <footer class="foot" <?= !empty($wide) ? 'hidden' : '' ?>>I file vengono eliminati automaticamente alla scadenza.
        </footer>
    </div>
    <div class="visually-hidden" id="fd-announcer" role="status" aria-live="polite"></div>

    <script src="<?= $this->e($this->asset('/assets/js/router.js')) ?>" defer></script>
    <script src="<?= $this->e($this->asset('/assets/js/ui.js')) ?>" defer></script>
    <script src="<?= $this->e($this->asset('/assets/js/motion.js')) ?>" defer></script>
    <?php foreach ($pageScripts ?? [] as $src): ?>
        <script src="<?= $this->e($this->asset($src)) ?>" defer></script>
    <?php endforeach; ?>
</body>

</html>