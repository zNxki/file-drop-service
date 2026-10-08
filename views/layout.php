<!doctype html>
<html lang="it">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= $this->e($title ?? 'File Drop') ?></title>
    <link rel="icon"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%230E1A2B'/%3E%3Crect x='7' y='10' width='18' height='12' rx='2' fill='%23FFD23F'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="/assets/style.css">
</head>

<body>
    <div class="shell">
        <header class="top"><a class="brand" href="/">File Drop</a></header>
        <main><?= $content ?></main>
        <footer class="foot">I file vengono eliminati automaticamente alla scadenza.</footer>
    </div>
    <?php if (!empty($script)): ?>
        <script src="/assets/app.js" defer></script><?php endif; ?>
</body>

</html>