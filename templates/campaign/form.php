<header class="page-header">
    <div><p class="eyebrow">Campaigns</p><h1><?= $editing ? 'Edit campaign' : 'New campaign' ?></h1><p>Campaign dates are interpreted as date-only values in the owner timezone.</p></div>
</header>

<?php if (isset($errors['conflict'])): ?>
    <section class="conflict-state" role="alert"><h2>This campaign changed while you were editing</h2><p><?= $escape($errors['conflict']) ?></p><p>Current saved values are shown below. Review them before submitting again.</p></section>
<?php elseif ($errors !== []): ?>
    <div class="validation-summary" role="alert" tabindex="-1" data-validation-summary><h2>Please correct the following</h2><ul>
        <?php foreach ($errors as $field => $message): ?><li><a href="#<?= $escape($field) ?>"><?= $escape($message) ?></a></li><?php endforeach; ?>
    </ul></div>
<?php endif; ?>

<form method="post" action="<?= $escape($actionUrl) ?>">
    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
    <?php if ($editing): ?><input type="hidden" name="version" value="<?= $escape($campaign['version']) ?>"><?php endif; ?>
    <div class="form-field">
        <label for="name">Campaign name <span aria-hidden="true">*</span></label>
        <input id="name" name="name" value="<?= $escape($values['name'] ?? '') ?>" maxlength="120" required<?= isset($errors['name']) ? ' aria-invalid="true" aria-describedby="name-error"' : '' ?>>
        <?php if (isset($errors['name'])): ?><span class="field-error" id="name-error"><?= $escape($errors['name']) ?></span><?php endif; ?>
    </div>
    <div class="field-grid">
        <?php foreach (['start_date' => 'Start date', 'end_date' => 'End date'] as $field => $label): ?>
        <div class="form-field"><label for="<?= $escape($field) ?>"><?= $escape($label) ?> <span aria-hidden="true">*</span></label><input type="date" id="<?= $escape($field) ?>" name="<?= $escape($field) ?>" value="<?= $escape($values[$field] ?? '') ?>" required<?= isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="' . $escape($field) . '-error"' : '' ?>><?php if (isset($errors[$field])): ?><span class="field-error" id="<?= $escape($field) ?>-error"><?= $escape($errors[$field]) ?></span><?php endif; ?></div>
        <?php endforeach; ?>
    </div>
    <fieldset>
        <legend>Targets</legend>
        <p>Response count and response rate are reported without numeric targets.</p>
        <div class="target-grid">
        <?php foreach ($targetDefinitions as $key => $definition): ?>
            <div class="form-field">
                <label for="target_<?= $escape($key) ?>"><?= $escape($definition['label']) ?></label>
                <input type="number" min="0" step="1" id="target_<?= $escape($key) ?>" name="targets[<?= $escape($key) ?>]" value="<?= $escape($values['targets'][$key] ?? $definition['default']) ?>" required<?= isset($errors['target_' . $key]) ? ' aria-invalid="true"' : '' ?>>
                <?php if ($editing && isset($targets[$key])): ?><input type="hidden" name="target_versions[<?= $escape($key) ?>]" value="<?= $escape($targets[$key]['version']) ?>"><?php endif; ?>
                <?php if (isset($errors['target_' . $key])): ?><span class="field-error"><?= $escape($errors['target_' . $key]) ?></span><?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </fieldset>
    <div class="form-actions"><button type="submit"><?= $editing ? 'Save campaign' : 'Create campaign' ?></button><a href="<?= $escape($indexUrl) ?>">Cancel</a></div>
</form>
