<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($title) ?> · Dreamsmith Campaign</title>
    <link rel="stylesheet" href="<?= $escape($assetBase) ?>/css/app.css">
    <script type="module" src="<?= $escape($assetBase) ?>/js/app.js"></script>
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>
<div class="app-shell">
    <aside class="app-sidebar">
        <div class="sidebar-brand"><a class="brand" href="<?= $escape($navigation[0]['url']) ?>"><span class="brand-mark" aria-hidden="true">D</span><span>Dreamsmith<small>Campaign</small></span></a><button class="nav-toggle" type="button" data-nav-toggle aria-controls="primary-navigation" aria-expanded="false">Menu</button></div>
        <nav id="primary-navigation" class="primary-nav" aria-label="Primary" data-navigation>
            <?php $group = null; foreach ($navigation as $item): ?>
                <?php if ($group !== $item['group']): $group = $item['group']; ?><p class="nav-group-label"><?= $escape($group) ?></p><?php endif; ?>
                <a href="<?= $escape($item['url']) ?>"<?= $item['current'] ? ' aria-current="page"' : '' ?>><span class="nav-dot" aria-hidden="true"></span><?= $escape($item['label']) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-account"><a href="<?= $escape($accountUrl) ?>">Account settings</a><form method="post" action="<?= $escape($logoutUrl) ?>"><input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>"><button class="link-button" type="submit">Sign out</button></form></div>
    </aside>
    <div class="app-content">
        <header class="app-topbar"><p>Private campaign workspace</p><a href="<?= $escape($accountUrl) ?>" class="topbar-account">Account</a></header>
        <div class="page-frame">
            <?php foreach ($flashes as $flash): ?>
                <?php $type = in_array($flash['type'], ['success', 'warning', 'error', 'info'], true) ? $flash['type'] : 'info'; ?>
                <div class="flash flash--<?= $escape($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>"><strong><?= $escape(ucfirst($type)) ?></strong><span><?= $escape($flash['message']) ?></span></div>
            <?php endforeach; ?>
            <main id="main-content" tabindex="-1"><?= $content ?></main>
        </div>
        <footer class="site-footer"><small>Dreamsmith Campaign · private workspace</small></footer>
    </div>
</div>
</body>
</html>
