<?php
$fieldType = in_array($type ?? 'text', ['text', 'email', 'url', 'date', 'datetime-local', 'number', 'password', 'textarea', 'select'], true) ? ($type ?? 'text') : 'text';
$fieldError = $error ?? '';
$describedBy = trim(($hint ?? '') !== '' ? $id . '-hint ' : '') . ($fieldError !== '' ? $id . '-error' : '');
?>
<div class="form-field">
    <label for="<?= $escape($id) ?>"><?= $escape($label) ?><?= ($required ?? false) ? ' <span aria-hidden="true">*</span>' : '' ?></label>
    <?php if (($hint ?? '') !== ''): ?><span class="field-hint" id="<?= $escape($id) ?>-hint"><?= $escape($hint) ?></span><?php endif; ?>
    <?php if ($fieldType === 'textarea'): ?>
        <textarea id="<?= $escape($id) ?>" name="<?= $escape($name) ?>"<?= ($required ?? false) ? ' required' : '' ?><?= $describedBy !== '' ? ' aria-describedby="' . $escape($describedBy) . '"' : '' ?><?= $fieldError !== '' ? ' aria-invalid="true"' : '' ?>><?= $escape($value ?? '') ?></textarea>
    <?php elseif ($fieldType === 'select'): ?>
        <select id="<?= $escape($id) ?>" name="<?= $escape($name) ?>"<?= ($required ?? false) ? ' required' : '' ?><?= $describedBy !== '' ? ' aria-describedby="' . $escape($describedBy) . '"' : '' ?><?= $fieldError !== '' ? ' aria-invalid="true"' : '' ?>>
            <?php foreach (($options ?? []) as $optionValue => $optionLabel): ?><option value="<?= $escape($optionValue) ?>"<?= (string) ($value ?? '') === (string) $optionValue ? ' selected' : '' ?>><?= $escape($optionLabel) ?></option><?php endforeach; ?>
        </select>
    <?php else: ?>
        <input type="<?= $escape($fieldType) ?>" id="<?= $escape($id) ?>" name="<?= $escape($name) ?>"<?= $fieldType !== 'password' ? ' value="' . $escape($value ?? '') . '"' : '' ?><?= ($required ?? false) ? ' required' : '' ?><?= isset($maxlength) ? ' maxlength="' . $escape($maxlength) . '"' : '' ?><?= $describedBy !== '' ? ' aria-describedby="' . $escape($describedBy) . '"' : '' ?><?= $fieldError !== '' ? ' aria-invalid="true"' : '' ?>>
    <?php endif; ?>
    <?php if ($fieldError !== ''): ?><span class="field-error" id="<?= $escape($id) ?>-error"><?= $escape($fieldError) ?></span><?php endif; ?>
</div>
