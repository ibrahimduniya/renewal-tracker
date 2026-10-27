<?php
session_start();
require __DIR__ . '/includes/helpers.php'; // e(), csrf etc. (without db.php — there is no DB yet)

$cfgFile  = __DIR__ . '/config.local.php';
$lockFile = __DIR__ . '/installed.lock';
$installed = file_exists($cfgFile) && file_exists($lockFile);
$error = '';
$step = 'db';

// Defaults from the main config, for reading the SQLite/MySQL config
$mainCfg = require __DIR__ . '/config.php';

function write_config(string $cfgFile, array $db): void {
    $content = "<?php\n// Created by install.php — do not edit by hand\nreturn " . var_export(['db' => $db], true) . ";\n";
    file_put_contents($cfgFile, $content);
    @chmod($cfgFile, 0600);
}

if ($installed) {
    $step = 'done';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'db') {
    csrf_check();
    $db = [
        'host' => trim($_POST['db_host'] ?? 'localhost') ?: 'localhost',
        'name' => trim($_POST['db_name'] ?? ''),
        'user' => trim($_POST['db_user'] ?? ''),
        'pass' => $_POST['db_pass'] ?? '',
    ];
    if ($db['name'] === '' || $db['user'] === '') {
        $error = 'Database name and user are required.';
    } else {
        try {
            $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            require __DIR__ . '/includes/db.php'; // for init_schema()
            init_schema($pdo);
            write_config($cfgFile, $db);
            $_SESSION['install_db_done'] = true;
            $step = 'admin';
        } catch (Throwable $ex) {
            $error = 'Could not connect to the database. Check the name/user/password, and make sure the user has been added to the database with ALL PRIVILEGES. (' . $ex->getMessage() . ')';
        }
    }
} elseif (file_exists($cfgFile) && !empty($_SESSION['install_db_done'])) {
    $step = 'admin';
}

if (!$installed && $step === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'admin') {
    csrf_check();
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if ($u === '' || strlen($p) < 6) {
        $error = 'Enter a username and a password of at least 6 characters.';
    } else {
        require __DIR__ . '/includes/db.php';
        $pdo = db();
        $cnt = (int)$pdo->query("SELECT COUNT(*) c FROM admins")->fetch()['c'];
        if ($cnt === 0) {
            $s = $pdo->prepare("INSERT INTO admins (username, pass_hash) VALUES (?, ?)");
            $s->execute([$u, password_hash($p, PASSWORD_DEFAULT)]);
        }
        file_put_contents($lockFile, date('c'));
        unset($_SESSION['install_db_done']);
        $_SESSION['admin_id'] = $pdo->query("SELECT id FROM admins ORDER BY id ASC LIMIT 1")->fetch()['id'];
        $_SESSION['admin_name'] = $u;
        $installed = true;
        $step = 'done';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install — Renewal Tracker</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="wrap">
<div class="card center-box" style="max-width:520px">
  <h2>🔔 Renewal Tracker — Install</h2>
  <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

  <?php if ($step === 'db'): ?>
    <p><small class="hint">Step 1 of 2: Enter the MySQL database details you created in cPanel. No file editing is needed — the config is created for you.</small></p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="step" value="db">
      <label>Database Host</label>
      <input name="db_host" value="localhost" required>
      <label>Database Name *</label>
      <input name="db_name" required placeholder="user_renewal">
      <label>Database User *</label>
      <input name="db_user" required placeholder="user_renewal">
      <label>Database Password</label>
      <input type="password" name="db_pass">
      <button class="btn" style="width:100%">Connect &amp; Continue →</button>
    </form>
    <p><small class="hint">Haven't created a database yet? Make one in cPanel → MySQL Databases, add the user to it and grant ALL PRIVILEGES.</small></p>

  <?php elseif ($step === 'admin'): ?>
    <p><small class="hint">Step 2 of 2: Database is ready ✓ Now create your login username and password.</small></p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="step" value="admin">
      <label>Username *</label>
      <input name="username" required autofocus>
      <label>Password (at least 6 characters) *</label>
      <input type="password" name="password" required>
      <button class="btn green" style="width:100%">Finish Install ✓</button>
    </form>

  <?php else: ?>
    <p>✅ <b>Install complete!</b> You are logged in.</p>
    <p>Only two things left to do:</p>
    <ol>
      <li>In <b>Settings</b>, enter your email address (reminders go there) and press "Send Test Mail".</li>
      <li>Add the <b>cron command</b> shown at the bottom of the Settings page to cPanel → Cron Jobs, to run once per day — without it, no reminder mail will be sent.</li>
    </ol>
    <p><a class="btn green" href="index.php?page=settings">Go to Settings →</a> <a class="btn grey" href="index.php">Dashboard</a></p>
    <p><small class="hint">Security: install.php is now locked. To reinstall, delete the installed.lock and config.local.php files from the server.</small></p>
  <?php endif; ?>
</div>
</main>
</body>
</html>
