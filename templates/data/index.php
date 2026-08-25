<header class="page-header">
    <div>
        <p class="eyebrow">Data tools</p>
        <h1>Move campaign data safely</h1>
        <p>Import structured records, or download readable CSV files for reporting and portability.</p>
    </div>
</header>

<div class="data-tools-grid">
    <section class="settings-panel">
        <p class="eyebrow">Import</p>
        <h2>Prospect list</h2>
        <p>Preview a CSV before creating companies, contacts, prospects, and research evidence. Existing records are never silently updated.</p>
        <a class="button" href="<?= $escape($importUrl) ?>">Import prospects</a>
    </section>
    <section class="settings-panel">
        <p class="eyebrow">Import</p>
        <h2>Interaction history</h2>
        <p>Bring historical emails, calls, meetings, LinkedIn activity, and notes into existing prospects.</p>
        <a class="button" href="<?= $escape($router->url('data.interactions.import')) ?>">Import interactions</a>
    </section>
    <section class="settings-panel">
        <p class="eyebrow">Import</p>
        <h2>Follow-ups</h2>
        <p>Schedule next actions for active prospects while keeping the one-open-action rule intact.</p>
        <a class="button" href="<?= $escape($router->url('data.followups.import')) ?>">Import follow-ups</a>
    </section>
    <section class="settings-panel">
        <p class="eyebrow">Import</p>
        <h2>Opportunities</h2>
        <p>Move qualified prospects into the pipeline with their offer, value, and expected close date.</p>
        <a class="button" href="<?= $escape($router->url('data.opportunities.import')) ?>">Import opportunities</a>
    </section>
    <section class="settings-panel">
        <p class="eyebrow">Export</p>
        <h2>Campaign records</h2>
        <p>Download the working data you need for reporting, review, or a controlled migration.</p>
        <a class="button button--secondary" href="<?= $escape($exportUrl) ?>">Choose exports</a>
    </section>
</div>

<section class="settings-panel">
    <h2>Available exports</h2>
    <div class="data-export-list">
        <?php foreach($exports as $key=>$label): ?>
            <span><?= $escape($label) ?></span>
        <?php endforeach; ?>
    </div>
    <p class="field-hint">CSV exports are for portability and reporting. Keep regular database backups separately.</p>
</section>
