<?php if ($pageCount > 1): ?>
<nav class="pagination" aria-label="Pagination">
    <?php if ($previousUrl !== null): ?><a href="<?= $escape($previousUrl) ?>" rel="prev">Previous</a><?php endif; ?>
    <span>Page <?= $escape($currentPage) ?> of <?= $escape($pageCount) ?></span>
    <?php if ($nextUrl !== null): ?><a href="<?= $escape($nextUrl) ?>" rel="next">Next</a><?php endif; ?>
</nav>
<?php endif; ?>
