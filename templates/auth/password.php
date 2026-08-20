<header class="page-header">
    <div><p class="eyebrow">Account</p>
    <h1>Change password</h1>
    </div>
</header>
    <?php if ($error !== ''): ?><p role="alert"><?= $escape($error) ?></p><?php endif; ?>
    <?php if ($success !== ''): ?><p role="status"><?= $escape($success) ?></p><?php endif; ?>
    <form method="post">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
        <label>Current password <input type="password" name="current_password" autocomplete="current-password" required></label>
        <label>New password <input type="password" name="new_password" minlength="12" autocomplete="new-password" required></label>
        <label>Confirm new password <input type="password" name="new_password_confirmation" minlength="12" autocomplete="new-password" required></label>
        <button type="submit">Change password</button>
    </form>
