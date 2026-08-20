<div class="next-action">
    <strong><?= $escape($action) ?></strong>
    <span><?= $escape($dueLabel) ?></span>
    <?php if ($dueAt !== null): ?><time datetime="<?= $escape($dueAt) ?>"><?= $escape($formattedDueAt) ?></time><?php endif; ?>
</div>
