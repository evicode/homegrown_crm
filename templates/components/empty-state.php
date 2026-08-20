<section class="empty-state">
    <h2><?= $escape($heading) ?></h2>
    <p><?= $escape($message) ?></p>
    <?php if (isset($actionUrl, $actionLabel)): ?><p><a class="button" href="<?= $escape($actionUrl) ?>"><?= $escape($actionLabel) ?></a></p><?php endif; ?>
</section>
