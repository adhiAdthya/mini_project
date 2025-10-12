<h1>Login</h1>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<form action="<?= BASE_URL ?>/login" method="post" class="form">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($token) ?>" />
    <div class="form-row">
        <label>Email</label>
        <input type="email" name="email" required />
    </div>
    <div class="form-row">
        <label>Password</label>
        <input type="password" name="password" required />
    </div>
    <div class="form-row">
        <button class="btn" type="submit">Login</button>
    </div>
</form>
