<?php
$count = db()->query("SELECT COUNT(*) c FROM admins")->fetch()['c'];
if ($count > 0) redirect('index.php?page=login');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if ($u === '' || strlen($p) < 6) {
        $error = 'Enter a username and a password of at least 6 characters.';
    } else {
        $s = db()->prepare("INSERT INTO admins (username, pass_hash) VALUES (?, ?)");
        $s->execute([$u, password_hash($p, PASSWORD_DEFAULT)]);
        $_SESSION['admin_id'] = db()->lastInsertId();
        $_SESSION['admin_name'] = $u;
        flash('Admin account created. Now set your email address in Settings.');
        redirect('index.php?page=settings');
    }
}
?>
<div class="card center-box">
  <h2>First-Time Setup</h2>
  <p><small class="hint">There is no admin account yet. Create a username and password for your login.</small></p>
  <?php if (!empty($error)): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label>Username</label>
    <input name="username" required autofocus>
    <label>Password (at least 6 characters)</label>
    <input type="password" name="password" required>
    <button class="btn" style="width:100%">Create Account</button>
  </form>
</div>
