<form class="filter-bar" method="get" action="<?= $escape($actionUrl) ?>" aria-label="Filters">
    <?php foreach ($filters as $filter): ?>
        <label for="filter-<?= $escape($filter['name']) ?>"><?= $escape($filter['label']) ?>
            <?php if (isset($filter['options'])): ?>
                <select id="filter-<?= $escape($filter['name']) ?>" name="<?= $escape($filter['name']) ?>">
                    <?php foreach ($filter['options'] as $optionValue => $optionLabel): ?><option value="<?= $escape($optionValue) ?>"<?= (string) $filter['value'] === (string) $optionValue ? ' selected' : '' ?>><?= $escape($optionLabel) ?></option><?php endforeach; ?>
                </select>
            <?php else: ?>
                <input id="filter-<?= $escape($filter['name']) ?>" name="<?= $escape($filter['name']) ?>" value="<?= $escape($filter['value']) ?>">
            <?php endif; ?>
        </label>
    <?php endforeach; ?>
    <button type="submit">Apply filters</button>
    <a href="<?= $escape($clearUrl) ?>">Clear all</a>
</form>
