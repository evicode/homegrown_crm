<header class="page-header">
    <div><p class="eyebrow">Configuration</p><h1>Campaigns</h1><p>Choose the one campaign that drives daily work and reporting.</p></div>
    <a class="button" href="<?= $escape($newUrl) ?>">New campaign</a>
</header>

<?php if ($campaigns === []): ?>
    <section class="empty-state"><h2>No campaigns yet</h2><p>Create the campaign window and targets before adding prospects.</p><p><a class="button" href="<?= $escape($newUrl) ?>">Create campaign</a></p></section>
<?php else: ?>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Campaigns">
        <table>
            <thead><tr><th scope="col">Campaign</th><th scope="col">Dates</th><th scope="col">State</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
            <tbody>
            <?php foreach ($campaigns as $campaign): ?>
                <tr>
                    <th scope="row"><a href="<?= $escape($editUrl((int) $campaign['id'])) ?>"><?= $escape($campaign['name']) ?></a></th>
                    <td><?= $escape($campaign['start_date']) ?> through <?= $escape($campaign['end_date']) ?></td>
                    <td><?= (int) $campaign['is_active'] === 1 ? '<span class="status-badge status-badge--won">Active</span>' : 'Inactive' ?></td>
                    <td>
                        <?php if ((int) $campaign['is_active'] !== 1): ?>
                        <form method="post" action="<?= $escape($activateUrl((int) $campaign['id'])) ?>">
                            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                            <input type="hidden" name="settings_version" value="<?= $escape($settings['version']) ?>">
                            <button type="submit">Activate</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<section class="settings-panel" aria-labelledby="timezone-heading">
    <h2 id="timezone-heading">Owner timezone</h2>
    <p>Dates, due-state boundaries, and reports use this timezone. Stored UTC event timestamps are not rewritten.</p>
    <form method="post" action="<?= $escape($timezoneUrl) ?>">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
        <input type="hidden" name="settings_version" value="<?= $escape($settings['version']) ?>">
        <label for="timezone">Timezone
            <select id="timezone" name="timezone" required>
                <?php foreach ($timezones as $timezone): ?><option value="<?= $escape($timezone) ?>"<?= $timezone === $settings['owner_timezone'] ? ' selected' : '' ?>><?= $escape($timezone) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label class="checkbox-field"><input type="checkbox" name="confirm_timezone" value="1" required> I understand that due and reporting boundaries may move.</label>
        <button type="submit">Update timezone</button>
    </form>
</section>
