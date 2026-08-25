<header class="page-header">
    <div>
        <p class="eyebrow">Follow-up history</p>
        <h1>Every next action</h1>
        <p>See what is open, completed, or cancelled across the campaign.</p>
    </div>
    <a class="button button--secondary" href="<?= $escape($router->url('work.index')) ?>">Open daily work</a>
</header>

<form class="filter-bar" method="get" action="<?= $escape($indexUrl) ?>">
    <label>Search<input name="q" value="<?= $escape($filters['q']) ?>" placeholder="Person, company, action, or reason"></label>
    <label>Status<select name="status"><?php foreach (['' => 'All statuses', 'open' => 'Open', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $key => $label): ?><option value="<?= $escape($key) ?>"<?= $filters['status'] === $key ? ' selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?></select></label>
    <label>Sort<select name="sort"><?php foreach (['due' => 'Due date', 'status' => 'Status', 'updated' => 'Recently updated'] as $key => $label): ?><option value="<?= $escape($key) ?>"<?= $filters['sort'] === $key ? ' selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?></select></label>
    <label>Direction<select name="direction"><option value="desc"<?= $filters['direction'] === 'desc' ? ' selected' : '' ?>>Newest first</option><option value="asc"<?= $filters['direction'] === 'asc' ? ' selected' : '' ?>>Oldest first</option></select></label>
    <button>Apply</button>
    <a href="<?= $escape($indexUrl) ?>">Clear</a>
</form>

<p><?= $escape($count) ?> result<?= $count === 1 ? '' : 's' ?>.</p>
<?php if ($followUps === []): ?>
    <section class="empty-state"><h2><?= $count === 0 && $filters['q'] !== '' ? 'No matching follow-ups' : 'No follow-ups recorded yet' ?></h2><p>Schedule a next action from a prospect to build the campaign record.</p></section>
<?php else: ?>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Follow-up history"><table>
        <thead><tr><th>Due</th><th>Prospect</th><th>Next action</th><th>Status</th><th>Completed or cancelled</th></tr></thead>
        <tbody><?php foreach ($followUps as $followUp): ?><tr>
            <td><?= $escape($followUp['due_at']) ?></td>
            <th scope="row"><a href="<?= $escape($prospectUrl((int) $followUp['prospect_id'])) ?>"><?= $escape($followUp['company_name'] ?: $followUp['contact_name']) ?></a></th>
            <td><?= $escape($followUp['action']) ?></td>
            <td><?= $escape(ucfirst($followUp['status'])) ?></td>
            <td><?php if ($followUp['completed_at'] !== null): ?><?= $escape($followUp['completed_at']) ?><?php elseif ($followUp['cancelled_at'] !== null): ?><?= $escape($followUp['cancelled_at']) ?><?= $followUp['cancellation_reason'] ? ' — ' . $escape($followUp['cancellation_reason']) : '' ?><?php else: ?>—<?php endif; ?></td>
        </tr><?php endforeach; ?></tbody>
    </table></div>
<?php endif; ?>
<?php if ($pages > 1): ?><nav class="pagination" aria-label="Follow-up history pages"><?php for ($number = 1; $number <= $pages; $number++): ?><a href="<?= $escape($indexUrl . '?' . http_build_query($filters + ['page' => $number])) ?>"<?= $number === $page ? ' aria-current="page"' : '' ?>><?= $escape($number) ?></a><?php endfor; ?></nav><?php endif; ?>
