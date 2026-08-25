<header class="page-header">
    <div>
        <p class="eyebrow">Relationships</p>
        <h1>Contacts</h1>
        <p>People you know, whether they work for a company or are independent.</p>
    </div>
    <a class="button" href="<?= $escape($newUrl) ?>">New contact</a>
</header>

<nav class="tab-list" aria-label="Contact record state">
    <a href="<?= $escape($activeUrl) ?>"<?= !$archived ? ' aria-current="page"' : '' ?>>Active</a>
    <a href="<?= $escape($archivedUrl) ?>"<?= $archived ? ' aria-current="page"' : '' ?>>Archived</a>
</nav>

<form class="filter-bar" method="get" action="<?= $escape($indexUrl) ?>">
    <?php if ($archived): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
    <label>Search<input name="q" value="<?= $escape($filters['q']) ?>"></label>
    <label>Sort<select name="sort"><?php foreach (['name' => 'Name', 'company' => 'Company', 'role' => 'Role', 'updated' => 'Recently updated'] as $key => $label): ?><option value="<?= $escape($key) ?>"<?= $filters['sort'] === $key ? ' selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?></select></label>
    <label>Direction<select name="direction"><option value="asc"<?= $filters['direction'] === 'asc' ? ' selected' : '' ?>>Ascending</option><option value="desc"<?= $filters['direction'] === 'desc' ? ' selected' : '' ?>>Descending</option></select></label>
    <button>Apply</button>
    <a href="<?= $escape($archived ? $archivedUrl : $activeUrl) ?>">Clear</a>
</form>

<p><?= $escape($count) ?> result<?= $count === 1 ? '' : 's' ?>.</p>
<?php if ($contacts === []): ?>
    <section class="empty-state">
        <h2><?= $count === 0 && $filters['q'] !== '' ? 'No matching contacts' : 'No ' . ($archived ? 'archived' : 'active') . ' contacts' ?></h2>
        <p><?= $archived ? 'Archived contacts will appear here.' : 'Add a person directly, or from a company record.' ?></p>
    </section>
<?php else: ?>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Contacts">
        <table>
            <thead><tr><th>Contact</th><th>Company</th><th>Role</th><th>Email</th></tr></thead>
            <tbody><?php foreach ($contacts as $contact): ?><tr>
                <th scope="row"><a href="<?= $escape($editUrl((int) $contact['id'])) ?>"><?= $escape(trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')) ?: 'Unnamed contact') ?></a></th>
                <td><?= $escape($contact['company_name'] ?? 'Independent') ?><?= $contact['company_archived_at'] !== null ? ' (archived)' : '' ?></td>
                <td><?= $escape($contact['role'] ?? '—') ?></td>
                <td><?= $escape($contact['email'] ?? '—') ?></td>
            </tr><?php endforeach; ?></tbody>
        </table>
    </div>
<?php endif; ?>
<?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Contact pages"><?php for ($number = 1; $number <= $pages; $number++): ?><a href="<?= $escape($indexUrl . '?' . http_build_query($filters + ['page' => $number])) ?>"<?= $number === $page ? ' aria-current="page"' : '' ?>><?= $escape($number) ?></a><?php endfor; ?></nav>
<?php endif; ?>
