<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $s = db()->prepare("SELECT * FROM admins WHERE username = ?");
    $s->execute([trim($_POST['username'] ?? '')]);
    $admin = $s->fetch();
    if ($admin && password_verify($_POST['password'] ?? '', $admin['pass_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['username'];
        redirect('index.php?page=dashboard');
    }
    $error = 'Wrong username or password.';
}
?>
<div class="card center-box">
  <h2>Login</h2>
  <?php if (!empty($error)): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label>Username</label>
    <input name="username" required autofocus>
    <label>Password</label>
    <input type="password" name="password" required>
    <button class="btn" style="width:100%">Log In</button>
  </form>
</div>
