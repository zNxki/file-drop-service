<?php
use FileDrop\Support\Permissions;

$items = [
    ['overview', 'Panoramica', '/admin', Permissions::STATS_VIEW, '<path d="M4 20V10" /><path d="M10 20V4" /><path d="M16 20v-7" /><path d="M22 20H2" />'],
    ['files', 'File', '/admin/files', Permissions::FILES_VIEW, '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" /><path d="M14 3v5h5" />'],
    ['security', 'Sicurezza', '/admin/security', Permissions::SECURITY_VIEW, '<path d="M12 3l7 3v5c0 4.5-3 8.2-7 10-4-1.8-7-5.5-7-10V6z" />'],
    ['users', 'Account', '/admin/users', Permissions::USERS_MANAGE, '<circle cx="9" cy="8" r="3.5" /><path d="M2.5 20a6.5 6.5 0 0 1 13 0" /><path d="M16 4.5a3.5 3.5 0 0 1 0 7" /><path d="M18 14.5a6.5 6.5 0 0 1 3.5 5.5" />'],
    ['settings', 'Impostazioni', '/admin/settings', null, '<circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z" />'],
];
?>
<div class="panel-layout">
    <aside class="sidebar" aria-label="Menu amministrazione">
        <nav class="sidebar-nav">
            <?php foreach ($items as [$key, $label, $href, $permission, $icon]): ?>
                <?php if ($permission !== null && !$user->can($permission)) {
                    continue;
                } ?>
                <a class="sidebar-link<?= $section === $key ? ' is-active' : '' ?>" href="<?= $href ?>" <?= $section === $key ? 'aria-current="page"' : '' ?>>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><?= $icon ?></svg>
                    <span><?= $this->e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-user">
            <span class="sidebar-name"><?= $this->e($user->username) ?></span>
            <span class="sidebar-role"><?= $user->isAdmin ? 'Amministratore' : 'Account' ?></span>
            <form method="post" action="/admin/logout">
                <input type="hidden" name="csrf" value="<?= $this->e($csrf) ?>">
                <button class="link" type="submit">Esci</button>
            </form>
        </div>
    </aside>
    <div class="panel-main">
        <?php if (!empty($flash)): ?>
            <p class="flash flash-<?= $this->e($flash['type']) ?>" role="status"><?= $this->e($flash['text']) ?></p>
        <?php endif; ?>
        <?= $inner ?>
    </div>
</div>