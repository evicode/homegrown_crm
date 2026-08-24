<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($title) ?> · Dreamsmith Campaign</title>
    <link rel="stylesheet" href="<?= $escape($assetBase) ?>/css/app.css">
</head>
<body class="guest-page">
<a class="skip-link" href="#main-content">Skip to main content</a>
<main id="main-content" class="guest-card" tabindex="-1">
    <a class="guest-brand" href="<?= $escape($assetBase) ?>/../"><span class="brand-mark" aria-hidden="true">D</span><span>Dreamsmith<small>Campaign</small></span></a>
    <?php foreach ($flashes as $flash): ?>
        <div class="flash flash--<?= $escape($flash['type']) ?>" role="status"><?= $escape($flash['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>
</body>
</html>
