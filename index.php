<?php
session_start();
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/helpers.php';

$page = $_GET['page'] ?? 'dashboard';
// If the app is not installed yet, send to the installer (no manual config needed)
if (!file_exists(__DIR__ . '/config.local.php')) redirect('install.php');
$pdo = db();

// First run: if there is no admin yet, go to setup
$adminCount = (int)$pdo->query("SELECT COUNT(*) c FROM admins")->fetch()['c'];
if ($adminCount === 0 && $page !== 'setup') redirect('index.php?page=setup');

// RDAP JSON endpoint (must be logged in)
if ($page === 'rdap') {
    header('Content-Type: application/json');
    if (empty($_SESSION['admin_id'])) { echo json_encode(['expiry' => null]); exit; }
    echo json_encode(['expiry' => rdap_expiry($_GET['domain'] ?? '')]);
    exit;
}

$public = ['login', 'setup'];
if (empty($_SESSION['admin_id']) && !in_array($page, $public, true)) redirect('index.php?page=login');

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($page, ['renew', 'delete', 'testmail'], true)) {
    csrf_check();
    if ($page === 'renew') {
        $kind = ($_POST['kind'] ?? 'domain') === 'hosting' ? 'hosting' : 'domain';
        $col = $kind === 'hosting' ? 'hosting_expiry' : 'domain_expiry';
        $s = $pdo->prepare("SELECT * FROM services WHERE id = ?");
        $s->execute([(int)$_POST['id']]);
        if ($svc = $s->fetch()) {
            if (!empty($svc[$col])) {
                $base = max(strtotime($svc[$col]), strtotime('today'));
                $new = date('Y-m-d', strtotime('+1 year', $base));
                $pdo->prepare("UPDATE services SET $col = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$new, $svc['id']]);
                // Clear this kind's old reminder logs so reminders fire again for the new date
                $pdo->prepare("DELETE FROM reminder_log WHERE service_id = ? AND kind = ?")->execute([$svc['id'], $kind]);
                flash($svc['name'] . ' — ' . ($kind === 'hosting' ? 'Hosting' : 'Domain') . ' renewed, new expiry: ' . fmt_date($new));
            }
        }
        redirect('index.php?page=services');
    }
    if ($page === 'delete') {
        $pdo->prepare("DELETE FROM services WHERE id = ?")->execute([(int)$_POST['id']]);
        $pdo->prepare("DELETE FROM reminder_log WHERE service_id = ?")->execute([(int)$_POST['id']]);
        flash('Deleted.');
        redirect('index.php?page=services');
    }
    if ($page === 'testmail') {
        $to = setting('admin_email');
        if ($to === '') { flash('Set the reminder email address in Settings first.'); }
        else {
            $ok = send_mail($to, 'Renewal Tracker — Test Mail', "This is a test mail.\nYour reminder system is working correctly. Reminders will arrive at this address 7/3/1/0 days before an expiry date.");
            flash($ok ? 'Test mail sent — check your inbox (and spam folder).' : 'Failed to send mail — check the server mail setup.');
        }
        redirect('index.php?page=settings');
    }
}

if ($page === 'logout') { session_destroy(); redirect('index.php?page=login'); }

$allowed = ['dashboard', 'services', 'service_form', 'view', 'settings', 'login', 'setup'];
if (!in_array($page, $allowed, true)) $page = 'dashboard';

require __DIR__ . '/includes/header.php';
require __DIR__ . '/pages/' . $page . '.php';
require __DIR__ . '/includes/footer.php';
