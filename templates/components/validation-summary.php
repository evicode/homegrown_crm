<?php if ($errors !== []): ?>
<div class="validation-summary" role="alert" tabindex="-1" data-validation-summary>
    <h2>Please correct the following</h2>
    <ul>
        <?php foreach ($errors as $field => $message): ?>
            <li><a href="#<?= $escape($field) ?>"><?= $escape($message) ?></a></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
