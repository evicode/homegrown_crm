<header class="page-header">
    <div><p class="eyebrow">Lead Finder</p><h1>Ideal customer profile</h1><p>Tell the agent what a good lead looks like.</p></div>
</header>
<form method="post" action="<?= $escape($actionUrl) ?>" data-profile-upload-url="<?= $escape($uploadUrl) ?>">
    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
    <input type="hidden" name="version" value="<?= $escape($profile['version']) ?>">
    <label>Profile description
        <input id="profile-description" name="description" value="<?= $escape($profile['description']) ?>" maxlength="500" placeholder="Example: Independent manufacturers that need custom internal software">
        <span class="profile-upload">Fill this field from a file <input type="file" data-profile-upload data-target="profile-description" data-field="description" accept=".txt,.csv,.tsv,.xlsx,.docx"><span data-profile-upload-status role="status"></span></span>
    </label>
    <div class="field-grid lead-profile-grid">
        <label>Must-have traits
            <textarea id="profile-required-any" name="required_any" rows="7" placeholder="Example:&#10;custom manufacturing&#10;legacy systems&#10;manual production tracking"><?php foreach($profile['required_any'] as $value):?><?= $escape($value) ?>
<?php endforeach;?></textarea>
            <span class="field-hint">One trait per line. The lead must match at least one.</span>
            <span class="profile-upload">Fill this field from a file <input type="file" data-profile-upload data-target="profile-required-any" data-field="required_any" accept=".txt,.csv,.tsv,.xlsx,.docx"><span data-profile-upload-status role="status"></span></span>
        </label>
        <label>What the agent should look for
            <textarea id="profile-positive-keywords" name="positive_keywords" rows="7" placeholder="Example:&#10;custom software | 5&#10;manual workflow | 3"><?php foreach($profile['positive_keywords'] as $term=>$weight):?><?= $escape($term) ?> | <?= $escape($weight) ?>
<?php endforeach;?></textarea>
            <span class="field-hint"><strong>How this works:</strong> the agent adds the number when it finds the phrase in a company’s public information. Bigger numbers mean the trait matters more: 1 is a small clue; 20 is decisive. Enter one per line as <em>words to look for | importance (1–20)</em>, for example <code>custom software | 5</code>. The total is compared with your minimum score below.</span>
            <span class="profile-upload">Fill this field from a file <input type="file" data-profile-upload data-target="profile-positive-keywords" data-field="positive_keywords" accept=".txt,.csv,.tsv,.xlsx,.docx"><span data-profile-upload-status role="status"></span></span>
        </label>
        <label>Exclusions
            <textarea id="profile-negative-keywords" name="negative_keywords" rows="7" placeholder="Example:&#10;staffing agency&#10;consumer retail"><?php foreach($profile['negative_keywords'] as $value):?><?= $escape($value) ?>
<?php endforeach;?></textarea>
            <span class="field-hint">One exclusion per line. Any match rejects the candidate.</span>
            <span class="profile-upload">Fill this field from a file <input type="file" data-profile-upload data-target="profile-negative-keywords" data-field="negative_keywords" accept=".txt,.csv,.tsv,.xlsx,.docx"><span data-profile-upload-status role="status"></span></span>
        </label>
        <label>Locations
            <textarea id="profile-preferred-locations" name="preferred_locations" rows="7" placeholder="Example:&#10;Portland, Oregon&#10;Vancouver, Washington"><?php foreach($profile['preferred_locations'] as $value):?><?= $escape($value) ?>
<?php endforeach;?></textarea>
            <span class="field-hint">One location per line. Each matching location adds one point.</span>
            <span class="profile-upload">Fill this field from a file <input type="file" data-profile-upload data-target="profile-preferred-locations" data-field="preferred_locations" accept=".txt,.csv,.tsv,.xlsx,.docx"><span data-profile-upload-status role="status"></span></span>
        </label>
    </div>
    <p class="field-hint">Uploads accept plain text, CSV/TSV, Excel (.xlsx), and Word (.docx), up to 2 MB. The file fills its matching field immediately, then is discarded. You can edit the imported text before choosing Save.</p>
    <div class="field-grid">
        <label>Minimum score<input type="number" name="minimum_score" min="1" max="100" value="<?= $escape($profile['minimum_score']) ?>" required></label>
        <label>Strong-fit score<input type="number" name="strong_fit_score" min="1" max="100" value="<?= $escape($profile['strong_fit_score']) ?>" required></label>
    </div>
    <div class="form-actions"><button>Save ideal customer profile</button><a href="<?= $escape($indexUrl) ?>">Back to Lead Finder</a></div>
</form>
