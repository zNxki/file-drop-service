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
</head>

<body>
    <div class="shell<?= !empty($wide) ? ' shell-wide' : '' ?>">
        <header class="top"><a class="brand" href="/">File Drop</a></header>
        <main><?= $content ?></main>
        <footer class="foot" <?= !empty($wide) ? 'hidden' : '' ?>>I file vengono eliminati automaticamente alla scadenza.</footer>
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