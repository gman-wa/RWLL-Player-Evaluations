<?php /** @var bool $failed */ ?>
<form class="message" method="post" action="/admin">
    <h2>Admin</h2>
    <label class="field">
        <span class="label">PIN</span>
        <input type="password" name="pin" autocomplete="current-password" required autofocus>
    </label>
    <?php if ($failed): ?><p class="form-error">That PIN didn't match.</p><?php endif ?>
    <p><button type="submit" class="button">Sign in</button></p>
</form>
