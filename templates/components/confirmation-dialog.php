<button type="button" id="<?= $escape($openerId) ?>"><?= $escape($openLabel) ?></button>
<dialog data-dialog="#<?= $escape($openerId) ?>" aria-labelledby="<?= $escape($dialogId) ?>-heading">
    <h2 id="<?= $escape($dialogId) ?>-heading"><?= $escape($heading) ?></h2>
    <p><?= $escape($consequence) ?></p>
    <form method="post" action="<?= $escape($actionUrl) ?>">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
        <button class="button button--danger" type="submit"><?= $escape($confirmLabel) ?></button>
        <button type="button" data-dialog-close>Cancel</button>
    </form>
</dialog>
