<header class="page-header">
    <div>
        <p class="eyebrow">Search</p>
        <h1>Find a relationship</h1>
        <p>Search active companies, contacts, prospects, and opportunities from one place.</p>
    </div>
</header>

<form class="filter-bar" method="get" action="<?= $escape($indexUrl) ?>">
    <label>Search<input name="q" value="<?= $escape($query) ?>" minlength="2" maxlength="120" required autofocus placeholder="Name, company, role, note, or offer"></label>
    <button>Search</button>
</form>

<?php if ($query === ''): ?>
    <section class="empty-state"><h2>Start with a name or phrase</h2><p>Enter at least two characters to search the active CRM.</p></section>
<?php elseif (strlen($query) < 2): ?>
    <section class="validation-summary" role="alert"><h2>Keep typing</h2><p>Search needs at least two characters.</p></section>
<?php else: ?>
    <?php $total = count($results['companies']) + count($results['contacts']) + count($results['prospects']) + count($results['opportunities']) + count($results['follow_ups']) + count($results['interactions']); ?>
    <p><?= $escape($total) ?> result<?= $total === 1 ? '' : 's' ?> for “<?= $escape($query) ?>”. Each group shows up to 10 matches.</p>
    <?php foreach (['companies' => 'Companies', 'contacts' => 'Contacts', 'prospects' => 'Prospects', 'opportunities' => 'Opportunities', 'follow_ups' => 'Follow-ups', 'interactions' => 'Activity'] as $type => $heading): ?>
        <section class="settings-panel">
            <h2><?= $escape($heading) ?></h2>
            <?php if ($results[$type] === []): ?><p class="field-hint">No matches.</p>
            <?php else: ?><ul class="dashboard-list"><?php foreach ($results[$type] as $row): ?>
                <?php if ($type === 'companies'): ?><li><a href="<?= $escape($companyUrl((int) $row['id'])) ?>"><?= $escape($row['name']) ?></a><?= $row['location'] ? ' — ' . $escape($row['location']) : '' ?></li>
                <?php elseif ($type === 'contacts'): ?><li><a href="<?= $escape($contactUrl((int) $row['id'])) ?>"><?= $escape(trim($row['first_name'] . ' ' . $row['last_name']) ?: 'Unnamed contact') ?></a><?= $row['company_name'] ? ' — ' . $escape($row['company_name']) : ' — Independent' ?><?= $row['role'] ? ', ' . $escape($row['role']) : '' ?></li>
                <?php elseif ($type === 'prospects'): ?><li><a href="<?= $escape($prospectUrl((int) $row['id'])) ?>"><?= $escape($row['company_name'] ?: $row['contact_name']) ?></a> — <?= $escape(ucfirst($row['status'])) ?></li>
                <?php elseif ($type === 'opportunities'): ?><li><a href="<?= $escape($opportunityUrl((int) $row['id'])) ?>"><?= $escape($row['company_name'] ?: $row['contact_name']) ?></a> — <?= $escape(ucfirst($row['stage'])) ?>, $<?= $escape($row['value_amount']) ?></li>
                <?php elseif ($type === 'follow_ups'): ?><li><a href="<?= $escape($prospectUrl((int) $row['prospect_id'])) ?>"><?= $escape($row['company_name'] ?: $row['contact_name']) ?></a> — <?= $escape($row['action']) ?> (<?= $escape($row['status']) ?>)</li>
                <?php else: ?><li><a href="<?= $escape($prospectUrl((int) $row['prospect_id'])) ?>"><?= $escape($row['company_name'] ?: $row['contact_name']) ?></a> — <?= $escape($row['summary']) ?></li><?php endif; ?>
            <?php endforeach; ?></ul><?php endif; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
