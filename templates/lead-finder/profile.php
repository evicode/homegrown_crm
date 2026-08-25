<header class="page-header">
    <div><p class="eyebrow">Lead Finder</p><h1>Ideal customer profile</h1><p>Define what the agent should look for, then review every result before it becomes a prospect.</p></div>
</header>
<form class="lead-profile-form" method="post" action="<?= $escape($actionUrl) ?>" data-profile-upload-url="<?= $escape($uploadUrl) ?>">
    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
    <input type="hidden" name="version" value="<?= $escape($profile['version']) ?>">
    <p class="lead-profile-form__intro">Type directly, or import a file to prefill one field. You can edit everything before saving.</p>
    <section class="profile-description-card">
        <div class="profile-field-card__header">
            <div><label for="profile-description">In one sentence, describe your ideal customer</label><p>This gives the agent context for its research.</p></div>
            <div class="profile-upload"><label class="button button--secondary">Import file<input class="visually-hidden" type="file" data-profile-upload data-target="profile-description" data-field="description" accept=".txt,.csv,.tsv,.xlsx,.docx"></label><span data-profile-upload-status role="status"></span></div>
        </div>
        <input id="profile-description" name="description" value="<?= $escape($profile['description']) ?>" maxlength="500" placeholder="Example: Independent manufacturers that need custom internal software">
    </section>
    <div class="lead-profile-grid">
        <section class="profile-field-card">
            <div class="profile-field-card__header">
                <div><label for="profile-required-any">Must-have traits</label><p>A lead must match at least one of these.</p></div>
                <div class="profile-upload"><label class="button button--secondary">Import file<input class="visually-hidden" type="file" data-profile-upload data-target="profile-required-any" data-field="required_any" accept=".txt,.csv,.tsv,.xlsx,.docx"></label><span data-profile-upload-status role="status"></span></div>
            </div>
            <textarea id="profile-required-any" name="required_any" rows="7" placeholder="One trait per line&#10;&#10;custom manufacturing&#10;legacy systems&#10;manual production tracking"><?php foreach($profile['required_any'] as $value):?><?= $escape($value) ?>
<?php endforeach;?></textarea>
            <p class="field-hint">Use one trait per line. A match on any one is required.</p>
        </section>
        <section class="profile-field-card">
            <div class="profile-field-card__header">
                <div><h2 id="profile-positive-keywords-heading">Characteristics to look for</h2><p>Add characteristics that suggest a company may be a good fit.</p></div>
                <div class="profile-upload"><div class="profile-list-actions"><a href="<?= $escape($exportUrl) ?>">Export</a><label>Import<input class="visually-hidden" type="file" data-profile-upload data-target="profile-positive-keywords" data-field="positive_keywords" accept=".txt,.csv,.tsv,.xlsx,.docx"></label></div><span data-profile-upload-status role="status"></span></div>
            </div>
            <div id="profile-positive-keywords" class="profile-priority-list" data-profile-weight-rows aria-labelledby="profile-positive-keywords-heading">
                <div class="profile-priority-row profile-priority-row--head" aria-hidden="true"><span>Characteristic</span><span>Importance</span><span></span></div>
                <?php $priorityRows=array_map(static fn($term,$weight):array=>['term'=>$term,'weight'=>$weight],array_keys($profile['positive_keywords']),array_values($profile['positive_keywords']));if($priorityRows===[])$priorityRows=[['term'=>'','weight'=>1]];foreach($priorityRows as $row): ?>
                    <div class="profile-priority-row" data-profile-weight-row><input name="positive_keyword[]" value="<?= $escape($row['term']) ?>" maxlength="160" aria-label="What to look for" placeholder="Example: manual workflow"><select name="positive_keyword_weight[]" aria-label="Importance"><?php for($priority=$priorityMinimum;$priority<=$priorityMaximum;$priority++): ?><option value="<?= $priority ?>"<?= (int)$row['weight']===$priority?' selected':'' ?>><?= $priority ?></option><?php endfor; ?></select><button class="profile-priority-row__remove" type="button" data-profile-weight-remove aria-label="Remove this item">×</button></div>
                <?php endforeach; ?>
            </div>
            <template data-profile-weight-template><div class="profile-priority-row" data-profile-weight-row><input name="positive_keyword[]" maxlength="160" aria-label="What to look for" placeholder="Example: manual workflow"><select name="positive_keyword_weight[]" aria-label="Importance"><?php for($priority=$priorityMinimum;$priority<=$priorityMaximum;$priority++): ?><option value="<?= $priority ?>"<?= $priority===1?' selected':'' ?>><?= $priority ?></option><?php endfor; ?></select><button class="profile-priority-row__remove" type="button" data-profile-weight-remove aria-label="Remove this item">×</button></div></template>
            <button class="profile-priority-list__add" type="button" data-profile-weight-add>+ Add another characteristic</button>
            <p class="field-hint">Importance runs from <?= $escape($priorityMinimum) ?> (small clue) to <?= $escape($priorityMaximum) ?> (decisive). Use one row for each characteristic. Import and export use <code>characteristic | importance</code>, one item per line.</p>
        </section>
        <section class="profile-field-card">
            <div class="profile-field-card__header">
                <div><label for="profile-negative-keywords">Exclude these</label><p>Any match here rules a company out.</p></div>
                <div class="profile-upload"><label class="button button--secondary">Import file<input class="visually-hidden" type="file" data-profile-upload data-target="profile-negative-keywords" data-field="negative_keywords" accept=".txt,.csv,.tsv,.xlsx,.docx"></label><span data-profile-upload-status role="status"></span></div>
            </div>
            <textarea id="profile-negative-keywords" name="negative_keywords" rows="7" placeholder="One exclusion per line&#10;&#10;staffing agency&#10;consumer retail"><?php foreach($profile['negative_keywords'] as $value):?><?= $escape($value) ?>
<?php endforeach;?></textarea>
            <p class="field-hint">Use one exclusion per line.</p>
        </section>
        <section class="profile-field-card">
            <div class="profile-field-card__header">
                <div><label for="profile-preferred-locations">Preferred locations</label><p>Locations add to a company’s fit score.</p></div>
                <div class="profile-upload"><label class="button button--secondary">Import file<input class="visually-hidden" type="file" data-profile-upload data-target="profile-preferred-locations" data-field="preferred_locations" accept=".txt,.csv,.tsv,.xlsx,.docx"></label><span data-profile-upload-status role="status"></span></div>
            </div>
            <textarea id="profile-preferred-locations" name="preferred_locations" rows="7" placeholder="One location per line&#10;&#10;Portland, Oregon&#10;Vancouver, Washington"><?php foreach($profile['preferred_locations'] as $value):?><?= $escape($value) ?>
<?php endforeach;?></textarea>
            <p class="field-hint">Use one location per line. Each match adds one point.</p>
        </section>
    </div>
    <p class="lead-profile-form__note">Imports accept plain text, CSV/TSV, Excel (.xlsx), and Word (.docx), up to 2 MB. Imported files are discarded after their text fills the field.</p>
    <section class="profile-score-card">
        <div><h2>How strict should the match be?</h2><p>Use the total importance score from “What the agent should look for.”</p></div>
        <div class="field-grid"><label>Minimum score<input type="number" name="minimum_score" min="1" max="100" value="<?= $escape($profile['minimum_score']) ?>" required></label><label>Strong-fit score<input type="number" name="strong_fit_score" min="1" max="100" value="<?= $escape($profile['strong_fit_score']) ?>" required></label></div>
    </section>
    <div class="form-actions"><button>Save ideal customer profile</button><a href="<?= $escape($indexUrl) ?>">Back to Lead Finder</a></div>
</form>
