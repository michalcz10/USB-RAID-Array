<?php
/** Shared navigation and page frame for the SFTP workspace. */
function workspaceEscape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function workspaceAsset($root, $path) {
    return workspaceEscape($root . $path . '?v=' . filemtime(__DIR__ . '/../' . $path));
}

function renderWorkspaceStart(array $page) {
    $root = $page['root'];
    $active = $page['active'] ?? 'files';
    $guest = $page['guest'] ?? false;
    $username = $_SESSION['uname'] ?? '';
    $admin = !$guest && !empty($_SESSION['admin']);
    $filesUrl = $root . 'content/ftp/index.php';
    $nav = [
        'files' => ['All files', '▱', $filesUrl],
        'server' => ['Server status', '◴', $root . 'content/ftp/serverstat.php'],
        'users' => ['User management', '♙', $root . 'content/adminpanel.php'],
    ];
    ?>
<body class="file-app">
<a class="skip-link" href="#workspace-content">Skip to content</a>
<aside class="sidebar">
    <a class="brand" href="<?= workspaceEscape($guest ? $root . 'index.php' : $filesUrl) ?>"><span class="brand-mark" aria-hidden="true">▤</span><span>USB RAID<span class="brand-caption">SFTP WORKSPACE</span></span></a>
    <div class="eyebrow nav-heading"><?= $guest ? 'Shared workspace' : 'Workspace' ?></div>
    <nav aria-label="Main navigation">
        <?php if ($guest): ?>
            <a class="nav-item active" href="#workspace-content" aria-current="page"><span aria-hidden="true">↗</span> Shared file</a>
        <?php else: ?>
            <?php foreach ($nav as $key => [$label, $icon, $url]): ?>
                <?php if ($key !== 'files' && !$admin) continue; ?>
                <a class="nav-item<?= $active === $key ? ' active' : '' ?>" href="<?= workspaceEscape($url) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><span aria-hidden="true"><?= $icon ?></span> <?= $label ?></a>
            <?php endforeach; ?>
        <?php endif; ?>
    </nav>
    <div class="sidebar-bottom">
        <div class="connection-card"><?php if (!empty($page['connected'])): ?><span class="status-dot"></span><?php endif; ?><?= $guest ? 'Shared access' : (!empty($page['connected']) ? 'Server connected' : 'Remote storage') ?><small><?= $guest ? 'Access through a shared link' : 'Your SFTP workspace' ?></small></div>
        <?php if ($guest): ?>
            <a class="nav-item" href="<?= workspaceEscape($root . 'index.php') ?>">Sign in to your workspace ↗</a>
        <?php else: ?>
            <a class="nav-item<?= $active === 'account' ? ' active' : '' ?>" href="<?= workspaceEscape($root . 'content/changepassword.php') ?>"<?= $active === 'account' ? ' aria-current="page"' : '' ?>>Account settings</a>
            <div class="user-card"><span class="avatar"><?= workspaceEscape(strtoupper(substr($username, 0, 1))) ?></span><span class="user-name"><?= workspaceEscape($username) ?><small><?= $admin ? 'Administrator' : 'Member' ?></small></span><a href="<?= workspaceEscape($root . 'content/logout.php') ?>" title="Sign out" aria-label="Sign out">↗</a></div>
        <?php endif; ?>
    </div>
</aside>
<div class="workspace">
    <header class="topbar"><span><?= $guest ? 'Shared workspace' : 'Workspace' ?><span class="topbar-divider">/</span><strong><?= workspaceEscape($page['section']) ?></strong></span><button id="themeToggle" class="btn btn-sm theme-toggle"><i aria-hidden="true"></i><span id="themeText">Theme</span></button></header>
    <main class="file-main" id="workspace-content" tabindex="-1">
        <div class="page-heading"><div><div class="eyebrow"><?= workspaceEscape($page['eyebrow']) ?></div><h1><?= workspaceEscape($page['title']) ?><span class="heading-dot">.</span></h1><p><?= workspaceEscape($page['description']) ?></p></div>
            <?php if (isset($page['back'])): ?><a class="btn heading-back" href="<?= workspaceEscape($page['back']) ?>">← Back to files</a><?php elseif (!empty($page['connected'])): ?><span class="protocol-badge"><span class="status-dot"></span> SFTP</span><?php endif; ?>
        </div>
        <div class="workspace-content <?= workspaceEscape($page['class'] ?? '') ?>">
    <?php
}

function renderWorkspaceEnd() {
    ?>
        </div>
        <footer class="workspace-footer"><span>USB RAID Array <span aria-hidden="true">·</span> Developed by Michal Sedlák</span><a href="https://github.com/michalcz10/USB-RAID-Array" target="_blank" rel="noopener noreferrer">View on GitHub ↗</a></footer>
    </main>
</div>
    <?php
}
