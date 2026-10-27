<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    set_setting('admin_email', trim($_POST['admin_email'] ?? ''));
    set_setting('from_email', trim($_POST['from_email'] ?? ''));
    set_setting('remind_days', preg_replace('/[^0-9,]/', '', $_POST['remind_days'] ?? '7,3,1,0'));
    if (!empty($_POST['new_password'])) {
        if (strlen($_POST['new_password']) < 6) { $error = 'The new password must be at least 6 characters.'; }
        else {
            $u = db()->prepare("UPDATE admins SET pass_hash = ? WHERE id = ?");
            $u->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $_SESSION['admin_id']]);
        }
    }
    if (empty($error)) { flash('Settings saved.'); redirect('index.php?page=settings'); }
}
$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'your-site.com') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
$cronUrl = $base . '/cron.php?key=' . setting('cron_key');
?>
<div class="card">
  <h3>Settings</h3>
  <?php if (!empty($error)): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label>Reminder emails go to this address *</label>
    <input type="email" name="admin_email" value="<?= e(setting('admin_email')) ?>" placeholder="you@example.com">
    <label>Sender email (From) — leave empty for the server default</label>
    <input type="email" name="from_email" value="<?= e(setting('from_email')) ?>" placeholder="no-reply@your-domain.com">
    <label>Remind how many days before (comma-separated)</label>
    <input name="remind_days" value="<?= e(setting('remind_days', '7,3,1,0')) ?>">
    <small class="hint">Default: 7,3,1,0 — mail is sent 7 days, 3 days and 1 day before expiry, and on the expiry day itself.</small>
    <label style="margin-top:12px">New login password (leave empty to keep the current one)</label>
    <input type="password" name="new_password">
    <button class="btn green">Save</button>
  </form>
</div>
<div class="card">
  <h3>⏰ Cron Setup (required for email reminders)</h3>
  <p>Hit this URL once a day and the system will check dates and send reminder emails:</p>
  <p><code style="word-break:break-all"><?= e($cronUrl) ?></code></p>
  <p><small class="hint">cPanel → Cron Jobs → Once Per Day → Command:<br>
  <code>curl -s "<?= e($cronUrl) ?>" &gt;/dev/null 2&gt;&amp;1</code></small></p>
  <form method="post" action="index.php?page=testmail" onsubmit="return confirm('Send a test mail? Set the reminder email above and save first.')">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <button class="btn">✉️ Send Test Mail</button>
  </form>
</div>
