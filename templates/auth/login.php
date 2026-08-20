<h1>Sign in</h1>
<?php if ($error !== ''): ?><p class="field-error" role="alert"><?= $escape($error) ?></p><?php endif; ?>
    <form method="post" action="<?= $escape($loginUrl) ?>">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
        <input type="hidden" name="return" value="<?= $escape($return) ?>">
        <label>Email <input type="email" name="email" value="<?= $escape($email) ?>" maxlength="254" autocomplete="username" required></label>
        <label>Password <input type="password" name="password" autocomplete="current-password" required></label>
        <button type="submit">Sign in</button>
    </form>
