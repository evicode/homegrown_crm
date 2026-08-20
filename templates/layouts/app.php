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
<header class="site-header">
    <a class="brand" href="<?= $escape($navigation[0]['url']) ?>">Dreamsmith Campaign</a>
    <button class="nav-toggle" type="button" data-nav-toggle aria-controls="primary-navigation" aria-expanded="false">Menu</button>
    <nav id="primary-navigation" class="primary-nav" aria-label="Primary" data-navigation>
        <ul>
            <?php foreach ($navigation as $item): ?>
                <li><a href="<?= $escape($item['url']) ?>"<?= $item['current'] ? ' aria-current="page"' : '' ?>><?= $escape($item['label']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <div class="account-menu">
        <a href="<?= $escape($accountUrl) ?>">Account</a>
        <form class="inline-form" method="post" action="<?= $escape($logoutUrl) ?>">
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
            <button class="link-button" type="submit">Sign out</button>
        </form>
    </div>
</header>
<div class="page-frame">
    <?php foreach ($flashes as $flash): ?>
        <?php $type = in_array($flash['type'], ['success', 'warning', 'error', 'info'], true) ? $flash['type'] : 'info'; ?>
        <div class="flash flash--<?= $escape($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
            <strong><?= $escape(ucfirst($type)) ?>:</strong> <?= $escape($flash['message']) ?>
        </div>
    <?php endforeach; ?>
    <main id="main-content" tabindex="-1"><?= $content ?></main>
</div>
<footer class="site-footer"><small>Private campaign workspace</small></footer>
</body>
</html>
