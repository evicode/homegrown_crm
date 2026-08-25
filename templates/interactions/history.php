<header class="page-header">
    <div>
        <p class="eyebrow">Activity history</p>
        <h1>Every recorded conversation</h1>
        <p>Review calls, messages, meetings, and notes across the whole campaign.</p>
    </div>
</header>

<form class="filter-bar" method="get" action="<?= $escape($indexUrl) ?>">
    <label>Search<input name="q" value="<?= $escape($filters['q']) ?>" placeholder="Person, company, note, or outcome"></label>
    <label>Sort<select name="sort"><?php foreach (['occurred' => 'When it happened', 'type' => 'Type', 'outcome' => 'Outcome'] as $key => $label): ?><option value="<?= $escape($key) ?>"<?= $filters['sort'] === $key ? ' selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?></select></label>
    <label>Direction<select name="direction"><option value="desc"<?= $filters['direction'] === 'desc' ? ' selected' : '' ?>>Newest first</option><option value="asc"<?= $filters['direction'] === 'asc' ? ' selected' : '' ?>>Oldest first</option></select></label>
    <button>Apply</button>
    <a href="<?= $escape($indexUrl) ?>">Clear</a>
</form>

<p><?= $escape($count) ?> result<?= $count === 1 ? '' : 's' ?>.</p>
<?php if ($interactions === []): ?>
    <section class="empty-state"><h2><?= $count === 0 && $filters['q'] !== '' ? 'No matching activity' : 'No activity recorded yet' ?></h2><p>Record an interaction from a prospect to create the campaign history.</p></section>
<?php else: ?>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Activity history">
        <table>
            <thead><tr><th>When</th><th>Prospect</th><th>Activity</th><th>Outcome</th><th>Notes</th></tr></thead>
            <tbody><?php foreach ($interactions as $interaction): ?><tr<?= $interaction['voided_at'] !== null ? ' class="is-voided"' : '' ?>>
                <td><?= $escape($interaction['occurred_at']) ?></td>
                <th scope="row"><a href="<?= $escape($prospectUrl((int) $interaction['prospect_id'])) ?>"><?= $escape($interaction['company_name'] ?: $interaction['contact_name']) ?></a></th>
                <td><?= $escape($types[$interaction['type']] ?? $interaction['type']) ?></td>
                <td><?= $escape($outcomes[$interaction['outcome']]['label'] ?? $interaction['outcome']) ?><?= $interaction['voided_at'] !== null ? ' (corrected)' : '' ?></td>
                <td><?= $escape($interaction['summary']) ?></td>
            </tr><?php endforeach; ?></tbody>
        </table>
    </div>
<?php endif; ?>
<?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Activity history pages"><?php for ($number = 1; $number <= $pages; $number++): ?><a href="<?= $escape($indexUrl . '?' . http_build_query($filters + ['page' => $number])) ?>"<?= $number === $page ? ' aria-current="page"' : '' ?>><?= $escape($number) ?></a><?php endfor; ?></nav>
<?php endif; ?>
