<header class="page-header">
    <div><p class="eyebrow">Today</p><h1>Campaign workspace</h1><p>See what needs attention and keep your campaign moving.</p></div>
    <?php if ($campaign !== null): ?><a class="button" href="<?= $escape($workUrl) ?>">Open daily work</a><?php endif; ?>
</header>
<div class="dashboard">
<?php if ($campaign === null): ?>
    <section class="empty-state"><h2>Set up your campaign</h2><p>Your daily work queue will appear here after campaign configuration is complete.</p><p><a class="button" href="<?= $escape($campaignSetupUrl) ?>">Configure campaign</a></p></section>
<?php else: ?>
    <section class="dashboard-campaign-card">
        <div><p class="eyebrow">Active campaign</p><h2><?= $escape($campaign['name']) ?></h2><p><?= $escape($campaign['start_date']) ?> — <?= $escape($campaign['end_date']) ?><?php if ($report !== null && $report['campaign']['state'] === 'active'): ?> · Day <?= $escape($report['campaign']['day_number']) ?><?php elseif ($report !== null): ?> · <?= $escape(ucfirst($report['campaign']['state'])) ?><?php endif; ?></p></div>
        <div class="dashboard-campaign-card__links"><a href="<?= $escape($reportUrl) ?>">Campaign report</a><a href="<?= $escape($campaignSetupUrl) ?>">Campaign settings</a></div>
    </section>
<?php endif; ?>
<?php if ($report !== null): ?>
    <?php $primaryRate = $report['rates']['contact_to_conversation']; ?>
    <section class="dashboard-section"><div class="dashboard-section__heading"><div><h2>Today’s priorities</h2><p>Start with overdue actions, then work what is due today.</p></div></div><div class="card-grid"><article><h3>Overdue</h3><p><?= $escape(count($report['queues']['overdue'])) ?></p></article><article><h3>Due today</h3><p><?= $escape(count($report['queues']['today'])) ?></p></article><article><h3>Next seven days</h3><p><?= $escape(count($report['queues']['next_seven'])) ?></p></article><article><h3>Contact → conversation</h3><p><?= $primaryRate['denominator'] === 0 ? '—' : $escape(number_format(100 * $primaryRate['numerator'] / $primaryRate['denominator'], 1)) . '%' ?></p></article></div></section>
    <div class="dashboard-two-column">
        <section class="dashboard-section"><div class="dashboard-section__heading"><div><h2>Pipeline</h2><p>Current value of active and won opportunities.</p></div></div><dl class="record-details"><div><dt>Open value</dt><dd>$<?= $escape($report['pipeline']['open_value']) ?></dd></div><div><dt>Won value</dt><dd>$<?= $escape($report['pipeline']['won_value']) ?></dd></div></dl></section>
        <section class="dashboard-section"><div class="dashboard-section__heading"><div><h2>Ready to contact</h2><p>Prospects that can move forward now.</p></div><a href="<?= $escape($workUrl) ?>">View work</a></div><?php if ($report['queues']['ready'] === []): ?><p class="dashboard-empty">No prospects are waiting.</p><?php else: ?><ul class="dashboard-list"><?php foreach ($report['queues']['ready'] as $row): ?><li><a href="<?= $escape($prospectUrl((int) $row['id'])) ?>"><?= $escape($row['company_name'] ?: $row['contact_name']) ?></a></li><?php endforeach; ?></ul><?php endif; ?></section>
    </div>
    <section class="dashboard-section"><div class="dashboard-section__heading"><div><h2>Campaign target progress</h2><p>How actual work compares with your current campaign targets.</p></div><a href="<?= $escape($reportUrl) ?>">Full report</a></div><div class="table-scroll"><table><thead><tr><th>Metric</th><th>Actual</th><th>Target</th></tr></thead><tbody><?php foreach ($report['targets'] as $target): ?><tr><th scope="row"><?= $escape($target['label']) ?></th><td><?= $escape($target['actual']) ?></td><td><?= $escape($target['target']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
    <div class="dashboard-two-column">
        <section class="dashboard-section"><div class="dashboard-section__heading"><div><h2>Current funnel</h2><p>Where active prospects are in the process.</p></div></div><?php if ($report['funnel'] === []): ?><p class="dashboard-empty">No active prospects yet.</p><?php else: ?><ul class="dashboard-list"><?php foreach ($report['funnel'] as $row): ?><li><span><?= $escape(ucwords(str_replace('_', ' ', $row['status']))) ?></span><strong><?= $escape($row['total']) ?></strong></li><?php endforeach; ?></ul><?php endif; ?></section>
        <section class="dashboard-section"><div class="dashboard-section__heading"><div><h2>Recent activity</h2><p>The latest updates across this campaign.</p></div></div><?php if ($recentActivity === []): ?><p class="dashboard-empty">No activity recorded yet.</p><?php else: ?><ol class="timeline"><?php foreach ($recentActivity as $item): ?><li><strong><?= $escape($item['kind']) ?></strong><span><?= $escape($item['detail']) ?></span><time><?= $escape($item['at_time']) ?></time></li><?php endforeach; ?></ol><?php endif; ?></section>
    </div>
<?php endif; ?>
</div>
