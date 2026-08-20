<form method="post" action="<?= $escape($actionUrl) ?>" class="confirmation">
    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
    <p><strong><?= $escape($heading) ?></strong></p>
    <p><?= $escape($consequence) ?></p>
    <button class="button button--danger" type="submit"><?= $escape($buttonLabel) ?></button>
</form>
